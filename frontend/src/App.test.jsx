import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { App } from './App';
import { api, ApiError, clearCsrfToken } from './api/client';
import { StatusBadge } from './components/StatusBadge';
import { FormField } from './components/FormField';
import { EmptyState } from './components/EmptyState';
import { ErrorState } from './components/ErrorState';
import { ConfirmDialog } from './components/ConfirmDialog';

describe('Phase 12: Frontend Foundation & Public Suite', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    clearCsrfToken();
    window.history.pushState({}, 'Test', '/');
  });

  // ─── T-12.1-01 & T-12.1-02: API Client & CSRF Management ─────────────────
  it('T-12.1-01: API client attaches CSRF token on POST requests', async () => {
    const fetchSpy = vi.spyOn(global, 'fetch').mockImplementation(async (url, opts) => {
      if (url.includes('/api/auth/csrf-token')) {
        return new Response(JSON.stringify({ success: true, data: { csrf_token: 'test-csrf-token-123' } }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        });
      }
      return new Response(JSON.stringify({ success: true, data: { ok: true } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      });
    });

    await api.post('/api/test-endpoint', { foo: 'bar' });

    expect(fetchSpy).toHaveBeenCalled();
    const stateChangingCall = fetchSpy.mock.calls.find((call) => call[0] === '/api/test-endpoint');
    expect(stateChangingCall).toBeDefined();
    expect(stateChangingCall[1].headers.get('X-CSRF-Token')).toBe('test-csrf-token-123');
  });

  it('T-12.1-02: API client retries once on CSRF_FAILED with refreshed token', async () => {
    let csrfCallCount = 0;
    let postCallCount = 0;

    vi.spyOn(global, 'fetch').mockImplementation(async (url) => {
      if (url.includes('/api/auth/csrf-token')) {
        csrfCallCount += 1;
        return new Response(
          JSON.stringify({ success: true, data: { csrf_token: `token-v${csrfCallCount}` } }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }
      if (url.includes('/api/test-post')) {
        postCallCount += 1;
        if (postCallCount === 1) {
          return new Response(
            JSON.stringify({ success: false, error: { code: 'CSRF_FAILED', message: 'Token mismatch' } }),
            { status: 403, headers: { 'Content-Type': 'application/json' } }
          );
        }
        return new Response(JSON.stringify({ success: true, data: { success: true } }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        });
      }
      return new Response(JSON.stringify({ success: true }), { status: 200 });
    });

    const res = await api.post('/api/test-post', { data: 1 });
    expect(res.data.success).toBe(true);
    expect(postCallCount).toBe(2);
  });

  it('T-12.1-03: API client normalizes 422 error envelopes into ApiError with field errors', async () => {
    vi.spyOn(global, 'fetch').mockImplementation(async () => {
      return new Response(
        JSON.stringify({
          success: false,
          error: {
            code: 'VALIDATION_FAILED',
            message: 'Invalid parameters',
            fields: { email: 'Email is required', quantity: 'Must be at least 1' },
          },
          request_id: 'req_123456',
        }),
        { status: 422, headers: { 'Content-Type': 'application/json' } }
      );
    });

    try {
      await api.get('/api/invalid-resource');
      expect.fail('Should have thrown ApiError');
    } catch (err) {
      expect(err).toBeInstanceOf(ApiError);
      expect(err.status).toBe(422);
      expect(err.code).toBe('VALIDATION_FAILED');
      expect(err.fields.email).toBe('Email is required');
      expect(err.fields.quantity).toBe('Must be at least 1');
      expect(err.requestId).toBe('req_123456');
    }
  });

  it('T-12.1-04: API client dispatches unauthorized event on 401', async () => {
    const unauthorizedListener = vi.fn();
    window.addEventListener('sharesphere:unauthorized', unauthorizedListener);

    vi.spyOn(global, 'fetch').mockImplementation(async () => {
      return new Response(JSON.stringify({ success: false, error: { code: 'SESSION_EXPIRED' } }), {
        status: 401,
        headers: { 'Content-Type': 'application/json' },
      });
    });

    try {
      await api.get('/api/protected-resource');
    } catch {
      // Expected
    }

    expect(unauthorizedListener).toHaveBeenCalled();
    window.removeEventListener('sharesphere:unauthorized', unauthorizedListener);
  });

  // ─── T-12.1-08: StatusBadge Accessibility ────────────────────────────────
  it('T-12.1-08: StatusBadge renders both icon and text label (never color alone)', () => {
    const statuses = ['active', 'pending', 'verified', 'rejected', 'scheduled', 'collected'];

    statuses.forEach((status) => {
      const { container, unmount } = render(<StatusBadge status={status} />);
      expect(container.querySelector('svg')).toBeInTheDocument();
      expect(container.textContent).toBeTruthy();
      unmount();
    });
  });

  // ─── FormField & Accessible Error Rendering ──────────────────────────────
  it('FormField links label, hint, and error with aria-describedby', () => {
    render(
      <FormField id="test-input" label="Test Label" hint="Helpful hint" error="Validation error message">
        <input type="text" />
      </FormField>
    );

    const input = screen.getByLabelText(/Test Label/i);
    expect(input).toBeInTheDocument();
    expect(input.getAttribute('aria-invalid')).toBe('true');
    expect(input.getAttribute('aria-describedby')).toContain('test-input-hint');
    expect(input.getAttribute('aria-describedby')).toContain('test-input-error');
    expect(screen.getByText('Helpful hint')).toBeInTheDocument();
    expect(screen.getByText('Validation error message')).toBeInTheDocument();
  });

  // ─── ConfirmDialog Component ─────────────────────────────────────────────
  it('ConfirmDialog triggers confirm and cancel callbacks', () => {
    const onConfirm = vi.fn();
    const onCancel = vi.fn();

    const { rerender } = render(
      <ConfirmDialog
        isOpen={true}
        title="Delete Item"
        message="Are you sure you want to delete this listing?"
        onConfirm={onConfirm}
        onCancel={onCancel}
      />
    );

    expect(screen.getByText('Delete Item')).toBeInTheDocument();
    expect(screen.getByText('Are you sure you want to delete this listing?')).toBeInTheDocument();

    fireEvent.click(screen.getByText('Confirm'));
    expect(onConfirm).toHaveBeenCalledTimes(1);

    fireEvent.click(screen.getByText('Cancel'));
    expect(onCancel).toHaveBeenCalledTimes(1);

    rerender(
      <ConfirmDialog
        isOpen={false}
        title="Delete Item"
        message="Are you sure?"
        onConfirm={onConfirm}
        onCancel={onCancel}
      />
    );
    expect(screen.queryByText('Delete Item')).not.toBeInTheDocument();
  });

  // ─── Public Landing & Navigation Rendering ───────────────────────────────
  it('Renders public landing page with header and key CTA buttons', async () => {
    vi.spyOn(global, 'fetch').mockImplementation(async (url) => {
      if (url.includes('/api/auth/me')) {
        return new Response(JSON.stringify({ success: false }), {
          status: 401,
          headers: { 'Content-Type': 'application/json' },
        });
      }
      return new Response(JSON.stringify({ success: true, data: [] }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      });
    });

    render(<App />);

    expect(screen.getByRole('heading', { name: /Connecting Community Surplus with/i })).toBeInTheDocument();
    expect(screen.getByText(/Donate Surplus Goods/i)).toBeInTheDocument();
    expect(screen.getByText(/Register as NGO/i)).toBeInTheDocument();
  });
});
