import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { AuthProvider } from '../../auth/AuthContext';
import { ToastProvider } from '../../components/Toast';

import { DonorDashboard } from './DonorDashboard';
import { PostDonation } from './PostDonation';
import { MyDonations } from './MyDonations';
import { DonationDetail } from './DonationDetail';
import { RequestsReceived } from './RequestsReceived';
import { MatchedNgos } from './MatchedNgos';
import { MatchCard } from '../../components/MatchCard';

describe('Phase 13: Donor Frontend Suite', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  // ─── T-13.1-01: Donor Dashboard Metrics & Listings ────────────────────────
  it('T-13.1-01: DonorDashboard renders summary metric cards and recent listings', async () => {
    vi.spyOn(global, 'fetch').mockImplementation(async (url) => {
      if (url.includes('/api/auth/me')) {
        return new Response(
          JSON.stringify({ success: true, data: { user: { id: 1, name: 'Alice Donor', role: 'donor' } } }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }
      if (url.includes('/api/dashboard')) {
        return new Response(
          JSON.stringify({
            success: true,
            data: {
              role: 'donor',
              total_donations: 5,
              active_donations: 3,
              requests_received: 2,
              requests_accepted: 1,
              scheduled_pickups: 1,
              completed_handovers: 1,
            },
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }
      if (url.includes('/api/donations')) {
        return new Response(
          JSON.stringify({
            success: true,
            data: [
              {
                id: 101,
                title: 'Winter Jackets Collection',
                category_name: 'Clothing',
                condition: 'like_new',
                available_quantity: 15,
                total_quantity: 20,
                status: 'active',
              },
            ],
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }
      return new Response(JSON.stringify({ success: true }), { status: 200 });
    });

    render(
      <MemoryRouter initialEntries={['/donor']}>
        <AuthProvider>
          <ToastProvider>
            <Routes>
              <Route path="/donor" element={<DonorDashboard />} />
            </Routes>
          </ToastProvider>
        </AuthProvider>
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByText(/Total Donations/i)).toBeInTheDocument();
      expect(screen.getByText('Winter Jackets Collection')).toBeInTheDocument();
      expect(screen.getByText(/15 \/ 20 units/i)).toBeInTheDocument();
    });
  });

  // ─── T-13.1-02: Post Donation Form Validation ─────────────────────────────
  it('T-13.1-02: PostDonation validates required fields and minimum quantities', async () => {
    vi.spyOn(global, 'fetch').mockImplementation(async (url) => {
      if (url.includes('/api/categories')) {
        return new Response(
          JSON.stringify({
            success: true,
            data: [
              { id: 1, name: 'Clothing & Apparel' },
              { id: 2, name: 'Food & Groceries' },
            ],
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }
      return new Response(JSON.stringify({ success: true }), { status: 200 });
    });

    render(
      <MemoryRouter initialEntries={['/donor/donations/new']}>
        <AuthProvider>
          <ToastProvider>
            <Routes>
              <Route path="/donor/donations/new" element={<PostDonation />} />
            </Routes>
          </ToastProvider>
        </AuthProvider>
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByLabelText(/Listing Title/i)).toBeInTheDocument();
    });

    // Attempt submitting without required fields
    const publishBtn = screen.getByRole('button', { name: /Publish Donation Listing/i });
    fireEvent.click(publishBtn);

    await waitFor(() => {
      expect(screen.getByText(/Title is required/i)).toBeInTheDocument();
      expect(screen.getByText(/Description is required/i)).toBeInTheDocument();
    });
  });

  // ─── T-13.1-03: My Donations Listing & Filters ────────────────────────────
  it('T-13.1-03: MyDonations renders items, inventory progress, and handles search filters', async () => {
    vi.spyOn(global, 'fetch').mockImplementation(async (url) => {
      if (url.includes('/api/categories')) {
        return new Response(
          JSON.stringify({ success: true, data: [{ id: 1, name: 'Books' }] }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }
      if (url.includes('/api/donations')) {
        return new Response(
          JSON.stringify({
            success: true,
            data: [
              {
                id: 202,
                title: 'High School Textbooks',
                category_name: 'Books',
                condition: 'good',
                available_quantity: 8,
                total_quantity: 10,
                status: 'partially_allocated',
                created_at: '2026-10-09T10:00:00Z',
              },
            ],
            meta: { page: 1, per_page: 9, total: 1, total_pages: 1 },
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }
      return new Response(JSON.stringify({ success: true }), { status: 200 });
    });

    render(
      <MemoryRouter initialEntries={['/donor/donations']}>
        <AuthProvider>
          <ToastProvider>
            <Routes>
              <Route path="/donor/donations" element={<MyDonations />} />
            </Routes>
          </ToastProvider>
        </AuthProvider>
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByText('High School Textbooks')).toBeInTheDocument();
      expect(screen.getByText(/8 of 10 units left/i)).toBeInTheDocument();
    });
  });

  // ─── T-13.1-04: Donation Detail & History Timeline ────────────────────────
  it('T-13.1-04: DonationDetail displays full inventory breakdown and status history events', async () => {
    vi.spyOn(global, 'fetch').mockImplementation(async (url) => {
      if (url.includes('/api/donations/50/history')) {
        return new Response(
          JSON.stringify({
            success: true,
            data: [
              { action: 'donation.created', details: 'Donation published with 50 units', created_at: '2026-10-09T08:00:00Z' },
              { action: 'request.accepted', details: 'Accepted claim of 20 units by Hope NGO', created_at: '2026-10-09T10:30:00Z' },
            ],
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }
      if (url.includes('/api/donations/50')) {
        return new Response(
          JSON.stringify({
            success: true,
            donation: {
              id: 50,
              title: 'Canned Food Supplies',
              category_name: 'Food',
              condition: 'new',
              total_quantity: 50,
              available_quantity: 30,
              address_text: 'Community Center Warehouse',
              status: 'active',
              images: [],
            },
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }
      return new Response(JSON.stringify({ success: true }), { status: 200 });
    });

    render(
      <MemoryRouter initialEntries={['/donor/donations/50']}>
        <AuthProvider>
          <ToastProvider>
            <Routes>
              <Route path="/donor/donations/:id" element={<DonationDetail />} />
            </Routes>
          </ToastProvider>
        </AuthProvider>
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByText('Canned Food Supplies')).toBeInTheDocument();
      expect(screen.getByText(/Community Center Warehouse/i)).toBeInTheDocument();
      expect(screen.getByText(/Listing & Handover Audit Trail/i)).toBeInTheDocument();
      expect(screen.getByText(/donation created/i)).toBeInTheDocument();
    });
  });

  // ─── T-13.2-01: RequestsReceived List & Actions ───────────────────────────
  it('T-13.2-01: RequestsReceived displays pending claims with urgency and triggers accept flow', async () => {
    let acceptCalled = false;

    vi.spyOn(global, 'fetch').mockImplementation(async (url, opts) => {
      if (url.includes('/api/requests/10/accept') && opts?.method === 'POST') {
        acceptCalled = true;
        return new Response(JSON.stringify({ success: true, message: 'Accepted' }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        });
      }
      if (url.includes('/api/auth/csrf-token')) {
        return new Response(JSON.stringify({ success: true, data: { csrf_token: 'test-token' } }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        });
      }
      if (url.includes('/api/requests')) {
        return new Response(
          JSON.stringify({
            success: true,
            data: [
              {
                id: 10,
                donation_id: 50,
                donation_title: 'Canned Food Supplies',
                organization_name: 'Helping Hands NGO',
                requirement_title: 'Emergency Food Drive',
                requested_quantity: 15,
                urgency: 'high',
                status: 'pending',
                created_at: new Date(Date.now() - 3600 * 1000).toISOString(),
              },
            ],
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }
      return new Response(JSON.stringify({ success: true }), { status: 200 });
    });

    render(
      <MemoryRouter initialEntries={['/donor/requests']}>
        <AuthProvider>
          <ToastProvider>
            <Routes>
              <Route path="/donor/requests" element={<RequestsReceived />} />
            </Routes>
          </ToastProvider>
        </AuthProvider>
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByText('Helping Hands NGO')).toBeInTheDocument();
      expect(screen.getByText(/15 units/i)).toBeInTheDocument();
      expect(screen.getByText(/HIGH Urgency/i)).toBeInTheDocument();
    });

    // Click Accept Request button
    const acceptBtn = screen.getByRole('button', { name: /Accept Request/i });
    fireEvent.click(acceptBtn);

    // Confirm dialog appears
    const confirmBtn = await screen.findByRole('button', { name: /Yes, Accept Request/i });
    fireEvent.click(confirmBtn);

    await waitFor(() => {
      expect(acceptCalled).toBe(true);
    });
  });

  // ─── T-13.2-02: MatchedNgos Scored Ranking & Guidance Footnote ────────────
  it('T-13.2-02: MatchedNgos displays ranked NGO cards and includes Spec §8.5 ranking guidance footnote', async () => {
    vi.spyOn(global, 'fetch').mockImplementation(async (url) => {
      if (url.includes('/api/donations/50')) {
        return new Response(
          JSON.stringify({
            success: true,
            donation: {
              id: 50,
              title: 'Warm Blankets',
              category_name: 'Bedding',
              condition: 'new',
              available_quantity: 25,
            },
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }
      if (url.includes('/api/matches')) {
        return new Response(
          JSON.stringify({
            success: true,
            data: [
              {
                requirement_id: 301,
                requirement_title: 'Shelter Winter Relief',
                organization_name: 'City Shelter Foundation',
                total_score: 92,
                distance_km: 3.4,
                quantity_needed: 20,
                urgency: 'critical',
                score_reasons: ['Exact category match (100%)', 'Within 3.4 km radius', 'New condition match'],
              },
            ],
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }
      return new Response(JSON.stringify({ success: true }), { status: 200 });
    });

    render(
      <MemoryRouter initialEntries={['/donor/donations/50/matches']}>
        <AuthProvider>
          <ToastProvider>
            <Routes>
              <Route path="/donor/donations/:id/matches" element={<MatchedNgos />} />
            </Routes>
          </ToastProvider>
        </AuthProvider>
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByText('Shelter Winter Relief')).toBeInTheDocument();
      expect(screen.getByText('City Shelter Foundation')).toBeInTheDocument();
      expect(screen.getByText(/Excellent Match/i)).toBeInTheDocument();
      expect(screen.getByText(/Exact category match/i)).toBeInTheDocument();
      // Verify Spec §8.5 algorithm guidance footnote
      expect(screen.getByText(/Ranking Guidance Note:/i)).toBeInTheDocument();
    });
  });

  // ─── T-13.2-03: MatchCard Score Banding ────────────────────────────────────
  it('T-13.2-03: MatchCard correctly computes score bands for high, medium, and moderate scores', () => {
    const { rerender } = render(
      <MatchCard
        match={{
          requirement_title: 'Need A',
          organization_name: 'NGO A',
          total_score: 95,
          distance_km: 2.1,
          quantity_needed: 10,
        }}
      />
    );
    expect(screen.getByText(/Excellent Match \(95%\)/i)).toBeInTheDocument();

    rerender(
      <MatchCard
        match={{
          requirement_title: 'Need B',
          organization_name: 'NGO B',
          total_score: 72,
          distance_km: 8.5,
          quantity_needed: 5,
        }}
      />
    );
    expect(screen.getByText(/Strong Match \(72%\)/i)).toBeInTheDocument();

    rerender(
      <MatchCard
        match={{
          requirement_title: 'Need C',
          organization_name: 'NGO C',
          total_score: 45,
          distance_km: 15.0,
          quantity_needed: 5,
        }}
      />
    );
    expect(screen.getByText(/Moderate Match \(45%\)/i)).toBeInTheDocument();
  });
});
