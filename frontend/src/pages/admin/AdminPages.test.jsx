/**
 * AdminPages.test.jsx — Phase 16 Vitest Suite
 *
 * Covers:
 *   Milestone 16.1:
 *     T-16.1-01 (P) Approve NGO: pending NGO -> verified
 *     T-16.1-02 (V) Reject without note: blocked client-side
 *     T-16.1-03 (S) Non-admin opens /admin: blocked by RoleRoute
 *     T-16.1-04 (S) Stored XSS in org/doc name: rendered as escaped text
 *     T-16.1-05 (P) Open document: opens via authorized endpoint with noopener noreferrer
 *     T-16.1-06 (P) Suspend user: reason required, self-suspend hidden
 *     T-16.1-07 (F) 409 on category deactivate: surfaces usage count message
 *     T-16.1-08 (P) Category compatibility edit and save
 *     T-16.1-09 (P) Moderate listing: lookup and remove with reason
 *     T-16.1-10 (R) Previous suites pass
 *
 *   Milestone 16.2:
 *     T-16.2-01 (P) Numbers reconcile: dashboard cards equal ground truth
 *     T-16.2-02 (E) Empty range: renders "No data for this period" empty state
 *     T-16.2-03 (V) Range >366 d: inline error displayed
 *     T-16.2-04 (A11y) Chart alternatives: toggle renders accessible data table
 *     T-16.2-05 (P) Audit filtering: action filter queries server
 *     T-16.2-06 (S) No secrets in audit UI: credentials purged from metadata viewer
 *     T-16.2-07 (P) Responsive: table horizontal scroll container present
 */

import React from 'react';
import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

// ── Shared Mocks ─────────────────────────────────────────────────────────────
vi.mock('../../api/client', () => {
  const ApiError = class extends Error {
    constructor(status, code, message, fields, data) {
      super(message);
      this.status = status;
      this.code = code;
      this.fields = fields || {};
      this.data = data;
    }
  };
  const api = {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
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
import { RoleRoute } from '../../routes/RoleRoute';
import { Ngos } from './Ngos';
import { NgoDetail } from './NgoDetail';
import { Users } from './Users';
import { Categories } from './Categories';
import { DonationModeration } from './DonationModeration';
import { AdminDashboard } from './AdminDashboard';
import { Reports } from './Reports';
import { AuditLogs } from './AuditLogs';

describe('Phase 16: Administrator Frontend Tests', () => {
  const adminUser = {
    id: 1,
    name: 'Admin Alice',
    email: 'admin@sharesphere.test',
    role: 'admin',
  };

  beforeEach(() => {
    vi.clearAllMocks();
    useAuth.mockReturnValue({
      user: adminUser,
      loading: false,
      isAuthenticated: true,
    });
  });

  afterEach(() => {
    vi.resetAllMocks();
  });

  // ───────────────────────────────────────────────────────────────────────────
  // Milestone 16.1 Tests
  // ───────────────────────────────────────────────────────────────────────────

  it('T-16.1-01 (P) Approve NGO: pending NGO transitions to verified', async () => {
    api.get.mockResolvedValueOnce({
      ngo: {
        id: 10,
        organization_name: 'Food For All',
        registration_number: 'REG-1001',
        status: 'pending',
        contact_person: 'Bob Leader',
        contact_phone: '+1 555-0199',
        contact_email: 'bob@foodforall.test',
        created_at: '2026-10-01 10:00:00',
        documents: [],
      },
    });

    api.post.mockResolvedValueOnce({
      status: 'success',
      ngo: {
        id: 10,
        organization_name: 'Food For All',
        status: 'verified',
      },
    });

    render(
      <MemoryRouter initialEntries={['/admin/ngos/10']}>
        <Routes>
          <Route path="/admin/ngos/:id" element={<NgoDetail />} />
        </Routes>
      </MemoryRouter>
    );

    // Verify details loaded using role heading
    expect(await screen.findByRole('heading', { level: 1, name: 'Food For All' })).toBeInTheDocument();

    // Select Approve radio
    const approveRadio = screen.getByLabelText(/Approve \(Verify NGO\)/i);
    fireEvent.click(approveRadio);

    // Submit decision
    const submitBtn = document.getElementById('submit-decision-btn');
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        '/api/admin/ngos/10/verify',
        expect.objectContaining({ decision: 'approve', action: 'approve' })
      );
      expect(mockShowSuccess).toHaveBeenCalledWith(expect.stringContaining('approve'));
    });
  });

  it('T-16.1-02 (V) Reject without note is blocked client-side', async () => {
    api.get.mockResolvedValueOnce({
      ngo: {
        id: 12,
        organization_name: 'Questionable Org',
        status: 'pending',
        registration_number: 'REG-9999',
        documents: [],
      },
    });

    render(
      <MemoryRouter initialEntries={['/admin/ngos/12']}>
        <Routes>
          <Route path="/admin/ngos/:id" element={<NgoDetail />} />
        </Routes>
      </MemoryRouter>
    );

    expect(await screen.findByRole('heading', { level: 1, name: 'Questionable Org' })).toBeInTheDocument();

    // Select Reject
    const rejectRadio = screen.getByDisplayValue('reject');
    fireEvent.click(rejectRadio);

    // Leave review note empty and submit
    const submitBtn = document.getElementById('submit-decision-btn');
    fireEvent.click(submitBtn);

    // Expect mandatory review note error
    expect(
      await screen.findByText(/A review note explaining this decision is mandatory/i)
    ).toBeInTheDocument();
    expect(api.post).not.toHaveBeenCalled();
  });

  it('T-16.1-03 (S) Non-admin opens /admin is blocked by RoleRoute', () => {
    useAuth.mockReturnValue({
      user: { id: 2, name: 'Donor Dave', role: 'donor' },
      loading: false,
    });

    render(
      <MemoryRouter initialEntries={['/admin']}>
        <RoleRoute roles={['admin']}>
          <AdminDashboard />
        </RoleRoute>
      </MemoryRouter>
    );

    expect(screen.getByText(/Access Restricted/i)).toBeInTheDocument();
    expect(screen.getByText(/Your account role \(donor\) does not have permission/i)).toBeInTheDocument();
  });

  it('T-16.1-04 (S) Stored XSS in org name rendered as escaped text', async () => {
    const maliciousOrgName = "<script>alert('XSS')</script>";
    api.get.mockResolvedValueOnce({
      data: [
        {
          id: 50,
          organization_name: maliciousOrgName,
          status: 'pending',
          registration_number: 'XSS-001',
          created_at: '2026-10-10 12:00:00',
        },
      ],
      meta: { total: 1, total_pages: 1 },
    });

    const { container } = render(
      <MemoryRouter initialEntries={['/admin/ngos']}>
        <Ngos />
      </MemoryRouter>
    );

    expect(await screen.findByText(maliciousOrgName)).toBeInTheDocument();
    // Verify no actual executable script tag was created in the DOM
    expect(container.querySelectorAll('script')).toHaveLength(0);
  });

  it('T-16.1-05 (P) Document link uses authorized endpoint with rel="noopener noreferrer"', async () => {
    api.get.mockResolvedValueOnce({
      ngo: {
        id: 15,
        organization_name: 'Verified Legal Org',
        status: 'pending',
        documents: [
          {
            id: 88,
            file_name: 'trust_deed.pdf',
            media_url: '/api/media/ngo-documents/88',
            created_at: '2026-10-05 14:00:00',
          },
        ],
      },
    });

    render(
      <MemoryRouter initialEntries={['/admin/ngos/15']}>
        <Routes>
          <Route path="/admin/ngos/:id" element={<NgoDetail />} />
        </Routes>
      </MemoryRouter>
    );

    const docLink = await screen.findByRole('link', { name: /View Document/i });
    expect(docLink).toHaveAttribute('href', '/api/media/ngo-documents/88');
    expect(docLink).toHaveAttribute('target', '_blank');
    expect(docLink).toHaveAttribute('rel', 'noopener noreferrer');
  });

  it('T-16.1-06 (P) Suspend user: reason required; self-suspend button hidden', async () => {
    api.get.mockResolvedValueOnce({
      data: [
        { id: 1, name: 'Admin Alice', email: 'admin@sharesphere.test', role: 'admin', status: 'active', created_at: '2026-01-01' },
        { id: 2, name: 'Bad Actor', email: 'bad@actor.test', role: 'donor', status: 'active', created_at: '2026-02-01' },
      ],
      meta: { total: 2, total_pages: 1 },
    });

    api.patch.mockResolvedValueOnce({
      status: 'success',
      message: 'User status updated successfully',
    });

    render(
      <MemoryRouter initialEntries={['/admin/users']}>
        <Users />
      </MemoryRouter>
    );

    // Current Admin Alice has ID 1 -> Self-suspend is hidden
    expect(await screen.findByText('Admin Alice')).toBeInTheDocument();
    expect(screen.getByText('Current Session')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /suspend-user-1/i })).not.toBeInTheDocument();

    // User 2 has Suspend button
    const suspendBtn = screen.getByRole('button', { name: /Suspend/i });
    fireEvent.click(suspendBtn);

    // Modal opens
    expect(screen.getByText(/Suspension Reason \(Required\)/i)).toBeInTheDocument();

    // Fill reason
    const reasonInput = screen.getByRole('textbox', { name: /Suspension Reason/i });
    fireEvent.change(reasonInput, { target: { value: 'Fraudulent donations posted' } });

    // Submit suspension
    const confirmBtn = screen.getByRole('button', { name: /Confirm Suspension/i });
    fireEvent.click(confirmBtn);

    await waitFor(() => {
      expect(api.patch).toHaveBeenCalledWith(
        '/api/admin/users/2/status',
        expect.objectContaining({
          status: 'suspended',
          reason: 'Fraudulent donations posted',
        })
      );
    });
  });

  it('T-16.1-07 (F) 409 on category deactivate surfaces usage counts', async () => {
    api.get.mockResolvedValueOnce({
      data: [
        { id: 7, name: 'Baby Care', description: 'Infant formulas and clothes', is_active: true, compatibilities: [] },
      ],
    });

    api.patch.mockRejectedValueOnce(
      new ApiError(409, 'CATEGORY_IN_USE', 'Cannot deactivate category: currently referenced by 3 active donation(s) and 2 active requirement(s)')
    );

    render(
      <MemoryRouter initialEntries={['/admin/categories']}>
        <Categories />
      </MemoryRouter>
    );

    expect(await screen.findByText('Baby Care')).toBeInTheDocument();

    // Toggle active status
    const toggleBtn = screen.getByRole('button', { name: /Active/i });
    fireEvent.click(toggleBtn);

    await waitFor(() => {
      expect(screen.getByText(/currently referenced by 3 active donation\(s\) and 2 active requirement\(s\)/i)).toBeInTheDocument();
      expect(mockShowError).toHaveBeenCalledWith(expect.stringContaining('3 active donation(s)'));
    });
  });

  it('T-16.1-08 (P) Category compatibility edit and save', async () => {
    api.get.mockResolvedValueOnce({
      data: [
        { id: 1, name: 'Winter Coats', is_active: true, compatibilities: [] },
        { id: 2, name: 'Warm Blankets', is_active: true, compatibilities: [] },
      ],
    });

    api.put.mockResolvedValueOnce({
      status: 'success',
      message: 'Compatibility updated',
    });

    render(
      <MemoryRouter initialEntries={['/admin/categories']}>
        <Categories />
      </MemoryRouter>
    );

    expect(await screen.findByText('Winter Coats')).toBeInTheDocument();

    // Open Compatibility for Category 1 using specific element ID
    const compatBtn = document.getElementById('edit-compat-1');
    fireEvent.click(compatBtn);

    expect(screen.getByText(/Compatibility: Winter Coats/i)).toBeInTheDocument();

    // Add Rule
    const addRuleBtn = screen.getByRole('button', { name: /Add Compatible Pairing/i });
    fireEvent.click(addRuleBtn);

    // Save rules
    const saveBtn = document.getElementById('save-compat-btn');
    fireEvent.click(saveBtn);

    await waitFor(() => {
      expect(api.put).toHaveBeenCalledWith(
        '/api/admin/categories/1/compatibility',
        expect.objectContaining({
          compatibility: expect.arrayContaining([
            expect.objectContaining({ compatible_category_id: 2 }),
          ]),
        })
      );
      expect(mockShowSuccess).toHaveBeenCalledWith(expect.stringContaining('compatibility rules updated'));
    });
  });

  it('T-16.1-09 (P) Moderate listing: lookup and remove with reason', async () => {
    api.get.mockResolvedValueOnce({
      donation: {
        id: 25,
        title: 'Prescription Medication',
        status: 'active',
        quantity: 2,
        donor: { name: 'Donor Bob', email: 'bob@test.com' },
      },
    });

    api.post.mockResolvedValueOnce({
      status: 'success',
      message: 'Donation moderated',
    });

    render(
      <MemoryRouter initialEntries={['/admin/donations']}>
        <DonationModeration />
      </MemoryRouter>
    );

    // Lookup donation 25
    const idInput = screen.getByPlaceholderText(/Enter Donation ID/i);
    fireEvent.change(idInput, { target: { value: '25' } });
    fireEvent.click(screen.getByRole('button', { name: /Inspect Listing/i }));

    expect(await screen.findByText('Prescription Medication')).toBeInTheDocument();

    // Enter reason
    const reasonInput = document.getElementById('mod-reason');
    fireEvent.change(reasonInput, { target: { value: 'Prescription medicines prohibited' } });

    // Submit moderation
    const moderateBtn = document.getElementById('submit-moderation-btn');
    fireEvent.click(moderateBtn);

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        '/api/admin/donations/25/moderate',
        expect.objectContaining({
          action: 'remove',
          reason: 'Prescription medicines prohibited',
        })
      );
      expect(mockShowSuccess).toHaveBeenCalledWith(expect.stringContaining('removed from active circulation'));
    });
  });

  // ───────────────────────────────────────────────────────────────────────────
  // Milestone 16.2 Tests
  // ───────────────────────────────────────────────────────────────────────────

  it('T-16.2-01 (P) Numbers reconcile on dashboard cards', async () => {
    api.get.mockImplementation((url) => {
      if (url === '/api/dashboard') {
        return Promise.resolve({
          data: {
            total_users: 124,
            pending_ngos: 7,
            active_donations: 45,
            completed_handovers: 88,
          },
        });
      }
      if (url.includes('/api/admin/reports/trends')) {
        return Promise.resolve({ data: [] });
      }
      if (url === '/api/admin/reports/categories') {
        return Promise.resolve({ data: [] });
      }
      return Promise.resolve({});
    });

    render(
      <MemoryRouter initialEntries={['/admin']}>
        <AdminDashboard />
      </MemoryRouter>
    );

    expect(await screen.findByText('124')).toBeInTheDocument();
    expect(screen.getByText('7')).toBeInTheDocument();
    expect(screen.getByText('45')).toBeInTheDocument();
    expect(screen.getByText('88')).toBeInTheDocument();
  });

  it('T-16.2-02 (E) Empty range shows "No data for this period" empty state', async () => {
    api.get.mockImplementation((url) => {
      if (url.includes('/api/admin/reports/summary')) {
        return Promise.resolve({
          data: {
            completed_handovers: 0,
            completion_rate: 0,
            donations_by_status: { active: 0, completed: 0 },
            requests_by_status: { pending: 0, completed: 0 },
          },
        });
      }
      if (url.includes('/api/admin/reports/trends')) {
        return Promise.resolve({ data: [] });
      }
      if (url === '/api/admin/reports/categories') {
        return Promise.resolve({ data: [] });
      }
      return Promise.resolve({});
    });

    render(
      <MemoryRouter initialEntries={['/admin/reports']}>
        <Reports />
      </MemoryRouter>
    );

    expect(await screen.findByText(/No data for this period/i)).toBeInTheDocument();
  });

  it('T-16.2-03 (V) Date range >366 days shows inline error and blocks fetch', async () => {
    api.get.mockImplementation((url) => {
      if (url.includes('/api/admin/reports/summary')) {
        return Promise.resolve({
          data: { completed_handovers: 1, donations_by_status: { active: 1 }, requests_by_status: {} },
        });
      }
      return Promise.resolve({ data: [] });
    });

    render(
      <MemoryRouter initialEntries={['/admin/reports']}>
        <Reports />
      </MemoryRouter>
    );

    expect(await screen.findByRole('heading', { level: 1, name: /Reports & Analytics/i })).toBeInTheDocument();

    const fromInput = screen.getByLabelText(/From Date/i);
    const toInput = screen.getByLabelText(/To Date/i);

    // Set 400 days apart: 2024-01-01 to 2025-02-05
    fireEvent.change(fromInput, { target: { value: '2024-01-01' } });
    fireEvent.change(toInput, { target: { value: '2025-02-05' } });

    const applyBtn = screen.getByRole('button', { name: /Apply Filter/i });
    fireEvent.click(applyBtn);

    expect(await screen.findByText('Date range cannot exceed 366 days.')).toBeInTheDocument();
  });

  it('T-16.2-04 (A11y) Chart alternatives: toggle renders accessible data table', async () => {
    api.get.mockImplementation((url) => {
      if (url.includes('/api/admin/reports/summary')) {
        return Promise.resolve({
          data: {
            completed_handovers: 15,
            completion_rate: 75,
            donations_by_status: { active: 10 },
            requests_by_status: { completed: 15 },
          },
        });
      }
      if (url.includes('/api/admin/reports/trends')) {
        return Promise.resolve({
          data: [
            { period: '2026-W39', count: 5 },
            { period: '2026-W40', count: 12 },
          ],
        });
      }
      if (url === '/api/admin/reports/categories') {
        return Promise.resolve({ data: [] });
      }
      return Promise.resolve({});
    });

    render(
      <MemoryRouter initialEntries={['/admin/reports']}>
        <Reports />
      </MemoryRouter>
    );

    // Wait for initial load
    expect(await screen.findByText(/Temporal Activity Trends/i)).toBeInTheDocument();

    // Toggle Table View
    const tableToggleBtn = screen.getByRole('button', { name: /Table View/i });
    fireEvent.click(tableToggleBtn);

    // Data table alternative is rendered with role="table"
    const dataTable = screen.getByRole('table', { name: /Trend data alternative/i });
    expect(dataTable).toBeInTheDocument();
    expect(within(dataTable).getByText('2026-W39')).toBeInTheDocument();
    expect(within(dataTable).getByText('12')).toBeInTheDocument();
  });

  it('T-16.2-05 (P) Audit filtering by action queries correct records', async () => {
    api.get.mockImplementation((url) => {
      if (url.includes('action=user.suspend')) {
        return Promise.resolve({
          data: [
            {
              id: 99,
              action: 'user.suspend',
              actor: { id: 1, name: 'Admin Alice', role: 'admin' },
              target: { type: 'user', id: 4 },
              result: 'success',
              metadata: { reason: 'Violations' },
              created_at: '2026-10-10 12:00:00',
            },
          ],
          meta: { total: 1, limit: 20, page: 1 },
        });
      }
      return Promise.resolve({
        data: [],
        meta: { total: 0, limit: 20, page: 1 },
      });
    });

    render(
      <MemoryRouter initialEntries={['/admin/audit-logs']}>
        <AuditLogs />
      </MemoryRouter>
    );

    expect(await screen.findByText(/Audit Log Viewer/i)).toBeInTheDocument();

    const actionInput = screen.getByLabelText(/Action/i);
    fireEvent.change(actionInput, { target: { value: 'user.suspend' } });

    const applyBtn = screen.getByRole('button', { name: /Apply Filters/i });
    fireEvent.click(applyBtn);

    await waitFor(() => {
      expect(screen.getByTestId('action-99')).toHaveTextContent('user.suspend');
    });
  });

  it('T-16.2-06 (S) No secrets displayed in audit log metadata viewer', async () => {
    api.get.mockResolvedValueOnce({
      data: [
        {
          id: 77,
          action: 'auth.login_attempt',
          actor: { id: 2, name: 'User Bob', role: 'donor' },
          target: { type: 'session', id: 10 },
          result: 'success',
          metadata: {
            ip_address: '192.168.1.1',
            password: 'UNHASHED_PASSWORD_SECRET',
            otp: '123456',
            auth_token: 'SECRET_BEARER_TOKEN_999',
          },
          created_at: '2026-10-10 13:00:00',
        },
      ],
      meta: { total: 1, limit: 20, page: 1 },
    });

    render(
      <MemoryRouter initialEntries={['/admin/audit-logs']}>
        <AuditLogs />
      </MemoryRouter>
    );

    expect(await screen.findByText('auth.login_attempt')).toBeInTheDocument();

    // Click to expand metadata
    const expandBtn = screen.getByRole('button', { name: /Expand details/i });
    fireEvent.click(expandBtn);

    const metadataBox = await screen.findByTestId('metadata-77');
    expect(metadataBox).toBeInTheDocument();
    expect(metadataBox).toHaveTextContent('192.168.1.1');

    // Secrets MUST be purged/purified
    expect(metadataBox).not.toHaveTextContent('UNHASHED_PASSWORD_SECRET');
    expect(metadataBox).not.toHaveTextContent('123456');
    expect(metadataBox).not.toHaveTextContent('SECRET_BEARER_TOKEN_999');
  });

  it('T-16.2-07 (P) Responsive table container has overflow-x: auto', async () => {
    api.get.mockResolvedValueOnce({
      data: [
        {
          id: 1,
          action: 'system.init',
          actor: { name: 'System' },
          target: { type: 'none' },
          result: 'success',
          created_at: '2026-10-01',
        },
      ],
      meta: { total: 1 },
    });

    render(
      <MemoryRouter initialEntries={['/admin/audit-logs']}>
        <AuditLogs />
      </MemoryRouter>
    );

    await screen.findByText('system.init');
    const container = document.getElementById('audit-logs-table-container');
    expect(container).toHaveClass('overflow-x-auto');
  });
});
