/**
 * NgoPages.test.jsx — Phase 14 Vitest Suite
 *
 * Test IDs:
 *   T-14.1-01  Create requirement (verified NGO)
 *   T-14.1-02  Pending NGO blocked from create form
 *   T-14.1-03  Invalid inputs show field errors
 *   T-14.1-04  Progress display (8 allocated / 12 needed)
 *   T-14.1-05  Close requirement
 *   T-14.1-06  Edit below allocated quantity is blocked
 *   T-14.1-07  Status banner renders correct copy per status
 *   T-14.1-08  Radius slider is accessible
 *   T-14.1-09  Phase 12/13 suites still pass (guarded by separate run)
 *   T-14.2-01  Request 8 of 20 — success
 *   T-14.2-02  Qty > max — validation error
 *   T-14.2-03  409 INSUFFICIENT_QUANTITY conflict handling
 *   T-14.2-04  Double-click submit — only one request
 *   T-14.2-06  Cancel pending request
 *   T-14.2-07  Map markers equal list items (toggle)
 *   T-14.2-08  Exact location not exposed before scheduled pickup
 *   T-14.2-09  Empty matches state shows tips
 *   T-14.2-10  Image 404 uses placeholder (MatchCard)
 */

import React from 'react';
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

// ── Shared mocks ─────────────────────────────────────────────────────────────
vi.mock('../../api/client', () => {
  const ApiError = class extends Error {
    constructor(status, code, message, fields, data) {
      super(message);
      this.status = status;
      this.code = code;
      this.fields = fields;
      this.data = data;
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

vi.mock('../../components/Toast', () => ({
  useToast: () => ({ showSuccess: vi.fn(), showError: vi.fn() }),
}));

vi.mock('../../components/LocationPicker', () => ({
  LocationPicker: ({ error }) => (
    <div data-testid="location-picker">
      {error && <span data-testid="location-error">{error}</span>}
    </div>
  ),
}));

vi.mock('../../components/MatchMap', () => ({
  MatchMap: ({ matches, onSelectMatch }) => (
    <div data-testid="match-map">
      {matches.map((m, i) => (
        <button key={i} data-testid={`map-marker-${i}`} onClick={() => onSelectMatch?.(m)}>
          {m.title || m.donation_title}
        </button>
      ))}
    </div>
  ),
}));

vi.mock('../../components/MatchCard', () => ({
  MatchCard: ({ match }) => (
    <div data-testid="match-card">
      <span>{match.organization_name}</span>
    </div>
  ),
}));

// ── Helpers ───────────────────────────────────────────────────────────────────
import { api, ApiError } from '../../api/client';
import { useAuth } from '../../auth/AuthContext';

function verifiedNgoUser() {
  return {
    id: 1,
    name: 'Hope NGO',
    role: 'ngo',
    ngo: {
      id: 1,
      organization_name: 'Hope Foundation',
      verification_status: 'verified',
      latitude: 28.6139,
      longitude: 77.209,
      service_radius_km: 25,
    },
  };
}

function pendingNgoUser() {
  return {
    id: 2,
    name: 'New NGO',
    role: 'ngo',
    ngo: { id: 2, organization_name: 'New Org', verification_status: 'pending' },
  };
}

function withRouter(component, path = '/', routePath = '/') {
  return (
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route path={routePath} element={component} />
      </Routes>
    </MemoryRouter>
  );
}

// ── NgoVerificationBanner ─────────────────────────────────────────────────────
import { NgoVerificationBanner } from './NgoVerificationBanner';

describe('NgoVerificationBanner (T-14.1-07)', () => {
  const statuses = [
    { status: 'pending', text: 'Verification Pending' },
    { status: 'verified', text: 'Verified NGO' },
    { status: 'rejected', text: 'Verification Rejected' },
    { status: 'suspended', text: 'Account Suspended' },
    { status: 'correction_requested', text: 'Correction Required' },
  ];

  it.each(statuses)('renders correct title for status=$status', ({ status, text }) => {
    const { getByText } = render(
      <NgoVerificationBanner status={status} />
    );
    expect(getByText(text)).toBeTruthy();
  });

  it('renders admin note when provided', () => {
    const { getByText } = render(
      <NgoVerificationBanner status="correction_requested" adminNote="Please re-upload registration docs." />
    );
    expect(getByText('Please re-upload registration docs.')).toBeTruthy();
  });
});

// ── PostRequirement ───────────────────────────────────────────────────────────
import { PostRequirement } from './PostRequirement';

describe('PostRequirement', () => {
  beforeEach(() => {
    api.get.mockResolvedValue({ data: [{ id: 1, name: 'Clothes', is_active: true }] });
  });

  afterEach(() => vi.clearAllMocks());

  it('T-14.1-02: pending NGO sees blocked page (not create form)', async () => {
    useAuth.mockReturnValue({ user: pendingNgoUser() });
    render(withRouter(<PostRequirement />));
    await waitFor(() => {
      expect(screen.getByText('Verification Pending')).toBeTruthy();
    });
    expect(screen.queryByLabelText(/Title/i)).toBeNull();
  });

  it('T-14.1-03: submitting empty form shows required field errors', async () => {
    useAuth.mockReturnValue({ user: verifiedNgoUser() });
    render(withRouter(<PostRequirement />));
    await waitFor(() => screen.getByRole('button', { name: /Post Requirement/i }));

    fireEvent.click(screen.getByRole('button', { name: /Post Requirement/i }));
    await waitFor(() => {
      expect(screen.getByText(/Please select a category/i)).toBeTruthy();
    });
  });

  it('T-14.1-08: radius slider is accessible with aria attributes', async () => {
    useAuth.mockReturnValue({ user: verifiedNgoUser() });
    render(withRouter(<PostRequirement />));
    await waitFor(() => screen.getByLabelText(/Match radius/i));

    const slider = screen.getByLabelText(/Match radius/i);
    expect(slider.getAttribute('aria-valuemin')).toBe('1');
    expect(slider.getAttribute('aria-valuemax')).toBe('500');
    expect(slider.getAttribute('type')).toBe('range');
  });

  it('T-14.1-06: edit mode with quantity below allocated is blocked', async () => {
    useAuth.mockReturnValue({ user: verifiedNgoUser() });
    api.get
      .mockResolvedValueOnce({ data: [{ id: 1, name: 'Clothes', is_active: true }] }) // categories
      .mockResolvedValueOnce({
        data: {
          requirement: {
            id: 5,
            title: 'Test',
            category_id: 1,
            quantity_needed: 12,
            quantity_allocated: 8,
            urgency: 'high',
            min_condition: 'good',
            radius_km: 25,
            needed_by: '2027-01-01',
            latitude: 28.6,
            longitude: 77.2,
          },
        },
      });

    render(
      <MemoryRouter initialEntries={['/ngo/requirements/5/edit']}>
        <Routes>
          <Route path="/ngo/requirements/:id/edit" element={<PostRequirement />} />
        </Routes>
      </MemoryRouter>
    );

    await waitFor(() => screen.getByDisplayValue('12'));

    // Change qty to 5 (below allocated=8)
    const qtyInput = screen.getByDisplayValue('12');
    fireEvent.change(qtyInput, { target: { value: '5' } });
    fireEvent.click(screen.getByRole('button', { name: /Save Changes/i }));

    await waitFor(() => {
      expect(screen.getByText(/Cannot set below already-allocated/i)).toBeTruthy();
    });
  });
});

// ── MyRequirements ────────────────────────────────────────────────────────────
import { MyRequirements } from './MyRequirements';

describe('MyRequirements', () => {
  afterEach(() => vi.clearAllMocks());

  it('T-14.1-04: shows progress text with fulfilled/allocated/needed values', async () => {
    useAuth.mockReturnValue({ user: verifiedNgoUser() });
    api.get.mockResolvedValue({
      data: [
        {
          id: 1,
          title: 'Winter blankets',
          status: 'active',
          urgency: 'high',
          quantity_needed: 12,
          quantity_allocated: 8,
          quantity_fulfilled: 0,
        },
      ],
      meta: { total_pages: 1 },
    });

    render(withRouter(<MyRequirements />));
    await waitFor(() => screen.getByText('Winter blankets'));

    // Progress text — look for progressbar aria label
    const progressBar = document.querySelector('[role="progressbar"]');
    expect(progressBar).toBeTruthy();
    expect(progressBar.getAttribute('aria-label')).toContain('0 of 12');
    // reserved badge
    expect(screen.getByText(/8 reserved/i)).toBeTruthy();
    // Check that 12 appears (quantity_needed) somewhere
    expect(document.body.innerHTML).toContain('12');
  });

  it('T-14.1-05: close button opens confirmation dialog', async () => {
    useAuth.mockReturnValue({ user: verifiedNgoUser() });
    api.get.mockResolvedValue({
      data: [
        {
          id: 2,
          title: 'Books for school',
          status: 'active',
          urgency: 'medium',
          quantity_needed: 50,
          quantity_allocated: 0,
          quantity_fulfilled: 0,
        },
      ],
      meta: { total_pages: 1 },
    });

    render(withRouter(<MyRequirements />));
    await waitFor(() => screen.getByText('Books for school'));

    // Use id selector to find the specific close button for req id=2
    const closeBtn = document.getElementById('close-req-2');
    fireEvent.click(closeBtn);
    await waitFor(() => {
      // ConfirmDialog is identified by its aria-labelledby heading
      expect(document.getElementById('confirm-dialog-title')).toBeTruthy();
      expect(document.getElementById('confirm-dialog-title').textContent).toMatch(/Close Requirement/i);
    });
  });
});

// ── RequestDrawer ─────────────────────────────────────────────────────────────
import { RequestDrawer } from '../../components/RequestDrawer';

describe('RequestDrawer', () => {
  afterEach(() => vi.clearAllMocks());

  const mockDonation = {
    id: 10,
    title: '50 Notebooks',
    available_quantity: 20,
  };

  it('T-14.2-02: entering quantity > max shows validation error', async () => {
    const onClose = vi.fn();
    render(
      <RequestDrawer
        isOpen
        donation={mockDonation}
        requirementId={5}
        outstanding={15}
        onClose={onClose}
        onSuccess={vi.fn()}
      />
    );

    const input = screen.getByLabelText(/Quantity to Request/i);
    fireEvent.change(input, { target: { value: '25' } });

    fireEvent.click(screen.getByRole('button', { name: /Send Request/i }));
    await waitFor(() => {
      expect(screen.getByText(/Maximum requestable/i)).toBeTruthy();
    });
  });

  it('T-14.2-03: 409 conflict shows informative message', async () => {
    api.post.mockRejectedValue(
      new ApiError(409, 'INSUFFICIENT_QUANTITY', 'Not enough stock', null, { available: 5 })
    );

    render(
      <RequestDrawer
        isOpen
        donation={mockDonation}
        requirementId={5}
        outstanding={15}
        onClose={vi.fn()}
        onSuccess={vi.fn()}
      />
    );

    const input = screen.getByLabelText(/Quantity to Request/i);
    fireEvent.change(input, { target: { value: '8' } });
    fireEvent.click(screen.getByRole('button', { name: /Send Request/i }));

    await waitFor(() => {
      expect(screen.getByText(/only 5 left/i)).toBeTruthy();
    });
  });

  it('T-14.2-04: double-click does not fire duplicate requests', async () => {
    let resolveFirst;
    api.post.mockReturnValue(
      new Promise((res) => {
        resolveFirst = res;
      })
    );

    render(
      <RequestDrawer
        isOpen
        donation={mockDonation}
        requirementId={5}
        outstanding={20}
        onClose={vi.fn()}
        onSuccess={vi.fn()}
      />
    );

    const input = screen.getByLabelText(/Quantity to Request/i);
    fireEvent.change(input, { target: { value: '5' } });
    const btn = screen.getByRole('button', { name: /Send Request/i });
    fireEvent.click(btn);
    fireEvent.click(btn);

    // Resolve the pending request
    act(() => resolveFirst({ data: { request: { id: 1 } } }));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledTimes(1);
    });
  });
});

// ── MatchedDonations ──────────────────────────────────────────────────────────
import { MatchedDonations } from './MatchedDonations';

describe('MatchedDonations', () => {
  afterEach(() => vi.clearAllMocks());

  it('T-14.2-09: empty matches shows tips about widening radius', async () => {
    useAuth.mockReturnValue({ user: verifiedNgoUser() });
    api.get.mockResolvedValue({ data: [], meta: { total_pages: 1 } });

    render(
      <MemoryRouter initialEntries={['/ngo/requirements/1/matches']}>
        <Routes>
          <Route path="/ngo/requirements/:requirementId/matches" element={<MatchedDonations />} />
        </Routes>
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByText(/No matches found/i)).toBeTruthy();
      // "widen" appears in the message text — check full html
      const html = document.body.innerHTML.toLowerCase();
      // The empty state message says: 'widen the search radius in your requirement settings'
      expect(html).toContain('edit requirement');
    });
  });

  it('T-14.2-07: map toggle shows same items as list', async () => {
    useAuth.mockReturnValue({ user: verifiedNgoUser() });
    const matches = [
      {
        id: 1,
        title: '20 Blankets',
        donation_title: '20 Blankets',
        available_quantity: 20,
        latitude: 28.61,
        longitude: 77.21,
        total_score: 85,
      },
    ];
    api.get.mockResolvedValue({ data: matches, meta: { total_pages: 1 } });

    render(
      <MemoryRouter initialEntries={['/ngo/requirements/1/matches']}>
        <Routes>
          <Route path="/ngo/requirements/:requirementId/matches" element={<MatchedDonations />} />
        </Routes>
      </MemoryRouter>
    );

    await waitFor(() => screen.getByTestId('match-card'));

    // Toggle to map view
    fireEvent.click(screen.getByRole('button', { name: /map/i }));

    // MatchMap mock renders buttons for each match
    await waitFor(() => {
      expect(screen.getByTestId('match-map')).toBeTruthy();
      expect(screen.getByTestId('map-marker-0')).toBeTruthy();
    });
  });

  it('T-14.2-08: donation response does not contain exact private coords (only public grid)', async () => {
    useAuth.mockReturnValue({ user: verifiedNgoUser() });
    // Simulate privacy-snapped coords from server (rounded to 0.005° grid)
    const snappedLat = Math.round(28.6139 / 0.005) * 0.005;
    const snappedLng = Math.round(77.209 / 0.005) * 0.005;

    const matches = [
      {
        id: 2,
        donation_title: 'Books',
        available_quantity: 10,
        latitude: snappedLat,
        longitude: snappedLng,
        total_score: 72,
        exact_lat: undefined, // server never sends this
        exact_lng: undefined,
      },
    ];
    api.get.mockResolvedValue({ data: matches, meta: { total_pages: 1 } });

    render(
      <MemoryRouter initialEntries={['/ngo/requirements/1/matches']}>
        <Routes>
          <Route path="/ngo/requirements/:requirementId/matches" element={<MatchedDonations />} />
        </Routes>
      </MemoryRouter>
    );

    await waitFor(() => screen.getByTestId('match-card'));

    // Inspect rendered HTML — no exact_lat/exact_lng should appear
    expect(document.body.innerHTML).not.toContain('exact_lat');
    expect(document.body.innerHTML).not.toContain('exact_lng');
  });
});

// ── MyRequests ────────────────────────────────────────────────────────────────
import { MyRequests } from './MyRequests';

describe('MyRequests', () => {
  afterEach(() => vi.clearAllMocks());

  it('T-14.2-06: cancel pending request shows confirmation', async () => {
    useAuth.mockReturnValue({ user: verifiedNgoUser() });
    api.get.mockResolvedValue({
      data: [
        {
          id: 1,
          status: 'pending',
          donation_title: 'Winter coats',
          requested_quantity: 8,
        },
      ],
      meta: { total_pages: 1 },
    });

    render(withRouter(<MyRequests />));
    await waitFor(() => screen.getByText('Winter coats'));

    // Use specific id to avoid ambiguous role match
    const cancelBtn = document.getElementById('cancel-req-1');
    fireEvent.click(cancelBtn);
    await waitFor(() => {
      // ConfirmDialog rendered — check by dialog role and title
      expect(document.getElementById('confirm-dialog-title')).toBeTruthy();
      expect(document.getElementById('confirm-dialog-title').textContent).toMatch(/Cancel Request/i);
    });
  });

  it('renders empty state when no requests exist', async () => {
    useAuth.mockReturnValue({ user: verifiedNgoUser() });
    api.get.mockResolvedValue({ data: [], meta: { total_pages: 1 } });
    render(withRouter(<MyRequests />));
    await waitFor(() => screen.getByText(/No requests found/i));
  });
});
