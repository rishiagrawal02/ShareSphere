import { render, screen, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import App from './App';

describe('App Smoke Test', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders landing header', async () => {
    global.fetch = vi.fn().mockImplementation(() =>
      Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ success: true, data: { db: true, time: '2026-10-09T00:00:00Z' } }),
      })
    );

    render(<App />);
    expect(screen.getByRole('heading', { name: /sharesphere/i, level: 1 })).toBeInTheDocument();
    await waitFor(() => {
      expect(screen.getByText('ok')).toBeInTheDocument();
    });
  });

  it('shows API: ok when health check succeeds', async () => {
    global.fetch = vi.fn().mockImplementation(() =>
      Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ success: true, data: { db: true, time: '2026-10-09T00:00:00Z' } }),
      })
    );

    render(<App />);
    await waitFor(() => {
      expect(screen.getByText('ok')).toBeInTheDocument();
    });
  });

  it('shows API: down when health check fails', async () => {
    global.fetch = vi.fn().mockImplementation(() =>
      Promise.reject(new Error('Network error'))
    );

    render(<App />);
    await waitFor(() => {
      expect(screen.getByText('down')).toBeInTheDocument();
    });
  });
});
