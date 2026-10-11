import React from 'react';
import { render } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { axe, toHaveNoViolations } from 'jest-axe';
import { LoadingState } from './LoadingState';
import { ErrorState } from './ErrorState';
import { EmptyState } from './EmptyState';
import { ErrorBoundary } from './ErrorBoundary';
import { StatusBadge } from './StatusBadge';
import { PageHeader } from './PageHeader';
import { Pagination } from './Pagination';
import { ConfirmDialog } from './ConfirmDialog';

expect.extend(toHaveNoViolations);

/**
 * T-17.2: WCAG 2.1 AA Accessibility Tests (Phase 17)
 *
 * Verifies that all shared UI primitives pass axe-core WCAG 2.1 AA checks:
 * - Correct ARIA roles (role="alert", role="status", role="dialog")
 * - Keyboard focus targets with focus-ring classes
 * - Screen-reader-only text where icons carry meaning
 * - Proper heading hierarchy within components
 */
describe('T-17.2: WCAG 2.1 AA — Shared UI Primitives', () => {
  const consoleError = console.error;
  beforeEach(() => { console.error = vi.fn(); });
  afterEach(() => { console.error = consoleError; });

  // ---------- LoadingState ----------
  describe('LoadingState', () => {
    it('inline variant has no axe violations', async () => {
      const { container } = render(<LoadingState message="Loading your data..." />);
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('has role=status for screen readers', () => {
      const { getByRole } = render(<LoadingState message="Loading..." />);
      const status = getByRole('status');
      expect(status).toBeInTheDocument();
    });

    it('includes aria-live=polite so assistive tech is notified', () => {
      const { getByRole } = render(<LoadingState message="Loading..." />);
      expect(getByRole('status')).toHaveAttribute('aria-live', 'polite');
    });
  });

  // ---------- ErrorState ----------
  describe('ErrorState', () => {
    it('with message string has no axe violations', async () => {
      const { container } = render(
        <ErrorState message="Could not load donations." onRetry={() => {}} />
      );
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('with ApiError object has no axe violations', async () => {
      const err = { code: 'NOT_FOUND', message: 'Resource not found', name: 'ApiError' };
      const { container } = render(<ErrorState error={err} onRetry={() => {}} />);
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('has role=alert for immediate screen-reader announcement', () => {
      const { getByRole } = render(<ErrorState message="Error occurred." />);
      expect(getByRole('alert')).toBeInTheDocument();
    });

    it('retry button is keyboard accessible and labeled', () => {
      const { getByRole } = render(<ErrorState message="Error" onRetry={() => {}} />);
      const btn = getByRole('button', { name: /Try Again/i });
      expect(btn).toBeInTheDocument();
      expect(btn).toHaveAttribute('type', 'button');
    });

    it('surfaces requestId from ApiError automatically', () => {
      const err = { code: 'INTERNAL_ERROR', message: 'Server error', name: 'ApiError', requestId: 'req_abc123' };
      const { getByText } = render(<ErrorState error={err} />);
      expect(getByText(/req_abc123/)).toBeInTheDocument();
    });
  });

  // ---------- EmptyState ----------
  describe('EmptyState', () => {
    it('basic empty state has no axe violations', async () => {
      const { container } = render(
        <EmptyState title="No donations yet" description="Post your first donation to get started." />
      );
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('with action button has no axe violations', async () => {
      const { container } = render(
        <EmptyState
          title="No items"
          description="Nothing here."
          actionText="Add Item"
          onAction={() => {}}
        />
      );
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('action button is keyboard accessible', () => {
      const { getByRole } = render(
        <EmptyState title="Empty" description="Desc" actionText="Add Item" onAction={() => {}} />
      );
      expect(getByRole('button', { name: /Add Item/i })).toBeInTheDocument();
    });
  });

  // ---------- StatusBadge ----------
  describe('StatusBadge', () => {
    const statuses = ['active', 'draft', 'cancelled', 'completed', 'pending', 'rejected', 'verified'];

    statuses.forEach((status) => {
      it(`status="${status}" has no axe violations`, async () => {
        const { container } = render(<StatusBadge status={status} />);
        const results = await axe(container);
        expect(results).toHaveNoViolations();
      });
    });
  });

  // ---------- PageHeader ----------
  describe('PageHeader', () => {
    it('with title only has no axe violations', async () => {
      const { container } = render(<PageHeader title="My Donations" />);
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('with title and subtitle has no axe violations', async () => {
      const { container } = render(
        <PageHeader title="Admin Dashboard" subtitle="Manage system-wide operations and oversight." />
      );
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });
  });

  // ---------- Pagination ----------
  describe('Pagination', () => {
    it('single page has no axe violations', async () => {
      const { container } = render(
        <Pagination currentPage={1} totalPages={1} onPageChange={() => {}} />
      );
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('multi-page has no axe violations', async () => {
      const { container } = render(
        <Pagination currentPage={3} totalPages={10} onPageChange={() => {}} />
      );
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });
  });

  // ---------- ConfirmDialog ----------
  describe('ConfirmDialog', () => {
    it('open confirm dialog has no axe violations', async () => {
      const { container } = render(
        <ConfirmDialog
          open={true}
          title="Delete donation?"
          message="This action cannot be undone."
          confirmLabel="Delete"
          onConfirm={() => {}}
          onCancel={() => {}}
        />
      );
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('closed confirm dialog renders nothing visible', () => {
      const { container } = render(
        <ConfirmDialog
          open={false}
          title="Delete?"
          message="Sure?"
          onConfirm={() => {}}
          onCancel={() => {}}
        />
      );
      expect(container.querySelector('[role=dialog]')).toBeNull();
    });
  });

  // ---------- ErrorBoundary ----------
  describe('ErrorBoundary — fallback UI', () => {
    it('error fallback UI has no axe violations', async () => {
      const Bomb = () => { throw new Error('Render error'); };
      const { container } = render(
        <ErrorBoundary>
          <Bomb />
        </ErrorBoundary>
      );
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });
  });
});
