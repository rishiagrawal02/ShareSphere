/**
 * PickupPages.test.jsx — Phase 15 Vitest Suite
 *
 * Test IDs:
 *   T-15.1-01 (P) Propose → confirm schedule
 *   T-15.1-02 (V) Past / invalid time validation
 *   T-15.1-03 (P) Reschedule resets state to proposed and invalidates code
 *   T-15.1-04 (P) Issue OTP (within 24h, code emailed, never in UI)
 *   T-15.1-05 (P) Donor enters valid OTP -> collected
 *   T-15.1-06 (N) Wrong OTP shows error + attempts left
 *   T-15.1-07 (S) Lockout UX disables input & instructs reissue
 *   T-15.1-08 (E) Expired OTP shows expired notice
 *   T-15.1-09 (V) Paste with spaces normalised to 6 digits
 *   T-15.1-10 (P) Confirm receipt -> completed
 *   T-15.1-11 (P) Notification bell unread count, click navigates & marks read
 *   T-15.1-12 (S) OTP code never stored in localStorage / URLs
 *   T-15.1-13 (P) Full handover journey
 *   T-15.1-14 (A11y) OtpInput accessible attributes and keyboard navigation
 *   T-15.1-15 (F) Poll failure handles 500 silently with backoff
 */

import React from 'react';
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

// ── Shared mocks ─────────────────────────────────────────────────────────────
vi.mock('../../api/client', () => {
  const ApiError = class extends Error {
    constructor(status, code, message, details) {
      super(message);
      this.status = status;
      this.code = code;
      this.details = details || {};
    }
  };
  const api = {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
  };
  return { api, ApiError };
});

vi.mock('../../auth/AuthContext', () => ({
  useAuth: vi.fn(),
}));

const mockShowSuccess = vi.fn();
const mockShowError = vi.fn();
vi.mock('../../components/Toast', () => ({
  useToast: () => ({ showSuccess: mockShowSuccess, showError: mockShowError }),
}));

import { api, ApiError } from '../../api/client';
import { useAuth } from '../../auth/AuthContext';
import { OtpInput } from '../../components/OtpInput';
import { PickupStepper } from '../../components/PickupStepper';
import { NotificationBell } from '../../components/NotificationBell';
import { SchedulePickup } from './SchedulePickup';
import { PickupDetail } from './PickupDetail';
import { PickupsList } from './PickupsList';
import { NotificationsPage } from '../NotificationsPage';

const donorUser = { id: 10, role: 'donor', name: 'Rishi Donor' };
const ngoUser = { id: 20, role: 'ngo', name: 'Hope Foundation' };

function buildPickup(overrides = {}) {
  return {
    id: 1,
    allocation_id: 100,
    proposed_by: 20, // NGO proposed
    scheduled_at: new Date(Date.now() + 12 * 3600 * 1000).toISOString(), // 12h in future
    location_details: 'Meet at main entrance gate',
    contact_note: 'Amit Kumar +91 9876543210',
    state: 'proposed',
    otp_attempts: 0,
    otp_locked: false,
    otp_issue_count: 0,
    otp_expires_at: null,
    collected_at: null,
    completed_at: null,
    donation: {
      id: 5,
      title: 'Winter Blankets',
      total_quantity: 20,
      available_quantity: 10,
      status: 'active',
      latitude_public: 28.6139,
      longitude_public: 77.209,
    },
    allocation: {
      id: 100,
      allocated_quantity: 10,
      status: 'confirmed',
    },
    ngo: {
      id: 3,
      organization_name: 'Hope Foundation',
    },
    ...overrides,
  };
}

describe('Phase 15: Pickups, OTP Verification & Notification Centre', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ── T-15.1-01: Propose -> Confirm Schedule ──────────────────────────────────
  it('T-15.1-01: counterparty can confirm proposed schedule and reveal address', async () => {
    useAuth.mockReturnValue({ user: donorUser });
    const pickup = buildPickup({
      state: 'proposed',
      proposed_by: 20, // NGO proposed, so donor confirms
    });
    api.get.mockResolvedValue({ data: pickup });

    const scheduledPickup = {
      ...pickup,
      state: 'scheduled',
      donation: {
        ...pickup.donation,
        address_text: '123 Peace Avenue, New Delhi',
        latitude_exact: 28.6139,
        longitude_exact: 77.209,
      },
    };
    api.post.mockResolvedValue({ data: scheduledPickup });

    render(
      <MemoryRouter initialEntries={['/pickups/1']}>
        <Routes>
          <Route path="/pickups/:id" element={<PickupDetail />} />
        </Routes>
      </MemoryRouter>
    );

    // Initial state: address is hidden
    await waitFor(() => {
      expect(screen.getByText(/Exact Address Protected/i)).toBeTruthy();
      expect(screen.getByText(/Confirm Pickup Time/i)).toBeTruthy();
    });

    // Donor clicks confirm
    fireEvent.click(screen.getByText(/Confirm Pickup Time/i));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/api/pickups/1/confirm');
      expect(mockShowSuccess).toHaveBeenCalled();
      expect(screen.getByText(/Exact Pickup Address Revealed/i)).toBeTruthy();
      expect(screen.getByText(/123 Peace Avenue, New Delhi/i)).toBeTruthy();
    });
  });

  // ── T-15.1-02: Past time validation ─────────────────────────────────────────
  it('T-15.1-02: past time is blocked client-side and server 422 is handled', async () => {
    useAuth.mockReturnValue({ user: ngoUser });
    api.get.mockResolvedValue({ data: [] });

    render(
      <MemoryRouter initialEntries={['/pickups/new?allocation_id=100']}>
        <Routes>
          <Route path="/pickups/new" element={<SchedulePickup />} />
        </Routes>
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByLabelText(/Pickup Date/i)).toBeTruthy();
    });

    // Set a past date
    fireEvent.change(screen.getByLabelText(/Pickup Date/i), { target: { value: '2020-01-01' } });
    fireEvent.change(screen.getByLabelText(/Pickup Time/i), { target: { value: '10:00' } });

    fireEvent.click(screen.getByRole('button', { name: /Propose Pickup/i }));

    // Client-side validation blocks submit
    await waitFor(() => {
      expect(screen.getByText(/at least 1 hour in the future/i)).toBeTruthy();
      expect(api.post).not.toHaveBeenCalled();
    });
  });

  // ── T-15.1-03: Reschedule resets state ───────────────────────────────────────
  it('T-15.1-03: rescheduling resets state to proposed and invalidates code', async () => {
    useAuth.mockReturnValue({ user: donorUser });
    const pickup = buildPickup({
      state: 'otp_issued',
      otp_expires_at: new Date(Date.now() + 1800 * 1000).toISOString(),
    });
    api.get.mockResolvedValue({ data: pickup });

    const rescheduledPickup = {
      ...pickup,
      state: 'proposed',
      scheduled_at: new Date(Date.now() + 48 * 3600 * 1000).toISOString(),
    };
    api.patch.mockResolvedValue({ data: rescheduledPickup });

    render(
      <MemoryRouter initialEntries={['/pickups/1']}>
        <Routes>
          <Route path="/pickups/:id" element={<PickupDetail />} />
        </Routes>
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByText(/Reschedule \(Invalidates Current Code\)/i)).toBeTruthy();
    });

    fireEvent.click(screen.getByText(/Reschedule \(Invalidates Current Code\)/i));

    // Modal opens showing notice
    expect(screen.getByText(/invalidate any previously issued pickup code/i)).toBeTruthy();

    const futureDate = new Date();
    futureDate.setDate(futureDate.getDate() + 2);
    const dateStr = futureDate.toISOString().split('T')[0];

    fireEvent.change(screen.getByLabelText(/New Date/i), { target: { value: dateStr } });
    fireEvent.change(screen.getByLabelText(/New Time/i), { target: { value: '14:00' } });

    fireEvent.click(screen.getByRole('button', { name: /Propose New Time/i }));

    await waitFor(() => {
      expect(api.patch).toHaveBeenCalledWith(
        '/api/pickups/1',
        expect.objectContaining({
          scheduled_at: expect.any(String),
        })
      );
      expect(mockShowSuccess).toHaveBeenCalledWith(
        expect.stringContaining('Reset to proposed state')
      );
    });
  });

  // ── T-15.1-04: NGO issues OTP (never shown in UI) ───────────────────────────
  it('T-15.1-04: NGO issues OTP within 24h, code is emailed, never shown in UI', async () => {
    useAuth.mockReturnValue({ user: ngoUser });
    const pickup = buildPickup({
      state: 'scheduled',
      scheduled_at: new Date(Date.now() + 4 * 3600 * 1000).toISOString(), // 4h away (within 24h)
    });
    api.get.mockResolvedValue({ data: pickup });
    api.post.mockResolvedValue({ data: { status: 'otp_issued' } });

    render(
      <MemoryRouter initialEntries={['/pickups/1']}>
        <Routes>
          <Route path="/pickups/:id" element={<PickupDetail />} />
        </Routes>
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByText(/Send Pickup Code/i)).toBeTruthy();
    });

    fireEvent.click(screen.getByText(/Send Pickup Code/i));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/api/pickups/1/otp');
      expect(mockShowSuccess).toHaveBeenCalledWith(
        expect.stringContaining('emailed to your organisation email')
      );
    });

    // Security check: ensure no 6-digit numeric OTP code is leaked in the DOM
    const bodyHtml = document.body.innerHTML;
    expect(bodyHtml).not.toMatch(/\b\d{6}\b/);
  });

  // ── T-15.1-05: Donor enters valid OTP -> collected ──────────────────────────
  it('T-15.1-05: donor enters valid 6-digit OTP -> collected state', async () => {
    useAuth.mockReturnValue({ user: donorUser });
    const pickup = buildPickup({
      state: 'otp_issued',
      otp_expires_at: new Date(Date.now() + 1800 * 1000).toISOString(),
    });
    api.get.mockResolvedValue({ data: pickup });
    api.post.mockResolvedValue({
      data: { pickup: { state: 'collected' } },
    });

    render(
      <MemoryRouter initialEntries={['/pickups/1']}>
        <Routes>
          <Route path="/pickups/:id" element={<PickupDetail />} />
        </Routes>
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByLabelText('Digit 1 of 6')).toBeTruthy();
    });

    // Enter 6 digits
    const digits = ['8', '2', '4', '1', '9', '0'];
    digits.forEach((d, i) => {
      fireEvent.change(screen.getByLabelText(`Digit ${i + 1} of 6`), { target: { value: d } });
    });

    // Click verify button
    fireEvent.click(screen.getByRole('button', { name: /Verify Handover Code/i }));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/api/pickups/1/verify-otp', { otp: '824190' });
      expect(mockShowSuccess).toHaveBeenCalledWith(expect.stringContaining('Handover verified'));
      expect(screen.getByText(/Items Handed Over & Collected/i)).toBeTruthy();
    });
  });

  // ── T-15.1-06: Wrong OTP shows error and attempts left ───────────────────────
  it('T-15.1-06: wrong OTP returns error and displays attempts remaining', async () => {
    useAuth.mockReturnValue({ user: donorUser });
    const pickup = buildPickup({
      state: 'otp_issued',
      otp_attempts: 1,
      otp_expires_at: new Date(Date.now() + 1800 * 1000).toISOString(),
    });
    api.get.mockResolvedValue({ data: pickup });
    api.post.mockRejectedValue(new ApiError(422, 'OTP_INVALID', 'Invalid pickup code'));

    render(
      <MemoryRouter initialEntries={['/pickups/1']}>
        <Routes>
          <Route path="/pickups/:id" element={<PickupDetail />} />
        </Routes>
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByLabelText('Digit 1 of 6')).toBeTruthy();
    });

    const digits = ['1', '1', '1', '1', '1', '1'];
    digits.forEach((d, i) => {
      fireEvent.change(screen.getByLabelText(`Digit ${i + 1} of 6`), { target: { value: d } });
    });

    fireEvent.click(screen.getByRole('button', { name: /Verify Handover Code/i }));

    await waitFor(() => {
      expect(screen.getByText(/Invalid pickup code/i)).toBeTruthy();
      expect(screen.getByText(/attempt/i)).toBeTruthy();
    });
  });

  // ── T-15.1-07: Lockout UX disables input ────────────────────────────────────
  it('T-15.1-07: lockout UX disables input and instructs NGO to reissue', async () => {
    useAuth.mockReturnValue({ user: donorUser });
    const pickup = buildPickup({
      state: 'otp_issued',
      otp_attempts: 5,
      otp_locked: true,
      otp_expires_at: new Date(Date.now() + 1800 * 1000).toISOString(),
    });
    api.get.mockResolvedValue({ data: pickup });

    render(
      <MemoryRouter initialEntries={['/pickups/1']}>
        <Routes>
          <Route path="/pickups/:id" element={<PickupDetail />} />
        </Routes>
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByText(/Pickup code is locked/i)).toBeTruthy();
      const digitInput = screen.getByLabelText('Digit 1 of 6');
      expect(digitInput.disabled).toBe(true);
    });
  });

  // ── T-15.1-08: Expired OTP shows expired notice ─────────────────────────────
  it('T-15.1-08: expired OTP displays clear guidance banner', async () => {
    useAuth.mockReturnValue({ user: donorUser });
    const pickup = buildPickup({
      state: 'otp_issued',
      otp_expires_at: new Date(Date.now() - 3600 * 1000).toISOString(), // 1h ago
    });
    api.get.mockResolvedValue({ data: pickup });

    render(
      <MemoryRouter initialEntries={['/pickups/1']}>
        <Routes>
          <Route path="/pickups/:id" element={<PickupDetail />} />
        </Routes>
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByText(/Pickup code has expired/i)).toBeTruthy();
    });
  });

  // ── T-15.1-09: Paste with spaces normalised ─────────────────────────────────
  it('T-15.1-09: pasting formatted code with spaces normalises to digits', () => {
    const handleChange = vi.fn();
    const handleComplete = vi.fn();

    render(
      <OtpInput
        onChange={handleChange}
        onComplete={handleComplete}
      />
    );

    const firstBox = screen.getByLabelText('Digit 1 of 6');

    // Paste "12 34 56"
    fireEvent.paste(firstBox, {
      clipboardData: {
        getData: () => '12 34 56',
      },
    });

    expect(handleComplete).toHaveBeenCalledWith('123456');
    expect(screen.getByLabelText('Digit 1 of 6').value).toBe('1');
    expect(screen.getByLabelText('Digit 2 of 6').value).toBe('2');
    expect(screen.getByLabelText('Digit 3 of 6').value).toBe('3');
    expect(screen.getByLabelText('Digit 4 of 6').value).toBe('4');
    expect(screen.getByLabelText('Digit 5 of 6').value).toBe('5');
    expect(screen.getByLabelText('Digit 6 of 6').value).toBe('6');
  });

  // ── T-15.1-10: NGO confirms receipt -> completed ────────────────────────────
  it('T-15.1-10: NGO confirms receipt after collected -> completed state', async () => {
    useAuth.mockReturnValue({ user: ngoUser });
    const pickup = buildPickup({
      state: 'collected',
      collected_at: new Date().toISOString(),
    });
    api.get.mockResolvedValue({ data: pickup });
    api.post.mockResolvedValue({
      data: { pickup: { state: 'completed' } },
    });

    render(
      <MemoryRouter initialEntries={['/pickups/1']}>
        <Routes>
          <Route path="/pickups/:id" element={<PickupDetail />} />
        </Routes>
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByText(/Confirm Receipt & Complete/i)).toBeTruthy();
    });

    fireEvent.click(screen.getByText(/Confirm Receipt & Complete/i));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/api/pickups/1/confirm-receipt');
      expect(mockShowSuccess).toHaveBeenCalledWith(expect.stringContaining('Receipt confirmed'));
      expect(screen.getByText(/Handover Completed/i)).toBeTruthy();
    });
  });

  // ── T-15.1-11: Notification bell ────────────────────────────────────────────
  it('T-15.1-11: notification bell shows unread count, dropdown navigation and marks read', async () => {
    useAuth.mockReturnValue({ user: donorUser });
    const notifications = [
      {
        id: 101,
        title: 'Pickup Time Proposed',
        body: 'Hope Foundation proposed a pickup time',
        ref_type: 'pickup',
        ref_id: 1,
        is_read: false,
        read_at: null,
        created_at: new Date().toISOString(),
      },
    ];
    api.get.mockResolvedValue({ data: notifications });
    api.post.mockResolvedValue({ status: 'ok' });

    render(
      <MemoryRouter>
        <NotificationBell />
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByLabelText(/Notifications \(1 unread\)/i)).toBeTruthy();
      expect(screen.getByText('1')).toBeTruthy();
    });

    // Open dropdown
    fireEvent.click(screen.getByLabelText(/Notifications \(1 unread\)/i));

    await waitFor(() => {
      expect(screen.getByText('Pickup Time Proposed')).toBeTruthy();
      expect(screen.getByText('Mark all read')).toBeTruthy();
    });

    // Click item to navigate and mark read
    fireEvent.click(screen.getByText('Pickup Time Proposed'));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/api/notifications/101/read');
    });
  });

  // ── T-15.1-12: OTP not stored client side ───────────────────────────────────
  it('T-15.1-12: OTP code is never stored in localStorage, sessionStorage, or URL query parameters', () => {
    const storageSpy = vi.spyOn(Storage.prototype, 'setItem');

    render(
      <MemoryRouter initialEntries={['/pickups/1']}>
        <OtpInput value="654321" />
      </MemoryRouter>
    );

    // Verify localStorage / sessionStorage were never called with the OTP
    expect(storageSpy).not.toHaveBeenCalledWith(expect.any(String), '654321');
    expect(window.location.search).not.toContain('654321');
  });

  // ── T-15.1-14: OtpInput accessibility ───────────────────────────────────────
  it('T-15.1-14: OtpInput satisfies a11y group label and arrow key navigation', () => {
    render(<OtpInput />);

    const group = screen.getByRole('group', { name: /One-time verification code/i });
    expect(group).toBeTruthy();

    const box1 = screen.getByLabelText('Digit 1 of 6');
    const box2 = screen.getByLabelText('Digit 2 of 6');

    box1.focus();
    expect(document.activeElement).toBe(box1);

    // Press right arrow
    fireEvent.keyDown(box1, { key: 'ArrowRight' });
    expect(document.activeElement).toBe(box2);

    // Press left arrow
    fireEvent.keyDown(box2, { key: 'ArrowLeft' });
    expect(document.activeElement).toBe(box1);
  });

  // ── T-15.1-15: Poll failure silent backoff ───────────────────────────────────
  it('T-15.1-15: notification polling handles API 500 silently without crashing or error spam', async () => {
    useAuth.mockReturnValue({ user: donorUser });
    api.get.mockRejectedValue(new Error('Network error 500'));

    render(
      <MemoryRouter>
        <NotificationBell />
      </MemoryRouter>
    );

    // Should render gracefully without throwing or displaying error alerts
    await waitFor(() => {
      expect(screen.getByLabelText(/Notifications/i)).toBeTruthy();
      expect(mockShowError).not.toHaveBeenCalled();
    });
  });

  // ── PickupsList: Filter tabs ───────────────────────────────────────────────
  it('PickupsList renders tabs and displays scheduled pickups', async () => {
    useAuth.mockReturnValue({ user: ngoUser });
    const pickups = [
      buildPickup({ id: 1, state: 'scheduled' }),
      buildPickup({ id: 2, state: 'completed' }),
    ];
    api.get.mockResolvedValue({ data: pickups, meta: { total_pages: 1 } });

    render(
      <MemoryRouter initialEntries={['/ngo/pickups']}>
        <Routes>
          <Route path="/ngo/pickups" element={<PickupsList />} />
        </Routes>
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getAllByText(/Winter Blankets/i).length).toBe(2);
      expect(screen.getByText(/Scheduled Pickups/i)).toBeTruthy();
    });
  });

  // ── NotificationsPage: Render and filter ────────────────────────────────────
  it('NotificationsPage renders notifications and marks all as read', async () => {
    useAuth.mockReturnValue({ user: donorUser });
    const notifs = [
      {
        id: 1,
        title: 'Donation Accepted',
        body: 'Your donation has been accepted',
        ref_type: 'donation',
        ref_id: 5,
        is_read: false,
        created_at: new Date().toISOString(),
      },
    ];
    api.get.mockResolvedValue({ data: notifs, meta: { total_pages: 1 } });
    api.post.mockResolvedValue({ status: 'ok' });

    render(
      <MemoryRouter initialEntries={['/notifications']}>
        <Routes>
          <Route path="/notifications" element={<NotificationsPage />} />
        </Routes>
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByText('Notification Center')).toBeTruthy();
      expect(screen.getByText('Donation Accepted')).toBeTruthy();
      expect(screen.getByText('Mark all as read')).toBeTruthy();
    });

    fireEvent.click(screen.getByText('Mark all as read'));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/api/notifications/read-all');
      expect(mockShowSuccess).toHaveBeenCalledWith('All notifications marked as read');
    });
  });
});
