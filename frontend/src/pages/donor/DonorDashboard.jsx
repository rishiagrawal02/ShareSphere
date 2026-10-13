import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../auth/AuthContext';
import { api, ApiError } from '../../api/client';
import { StatusBadge } from '../../components/StatusBadge';
import { LoadingState } from '../../components/LoadingState';
import { ErrorState } from '../../components/ErrorState';
import { EmptyState } from '../../components/EmptyState';
import {
  Package,
  CheckCircle2,
  Inbox,
  PlusCircle,
  ArrowRight,
  Clock,
  Layers,
  Sparkles,
  Eye,
} from 'lucide-react';

export function DonorDashboard() {
  const { user } = useAuth();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [metrics, setMetrics] = useState({
    total_donations: 0,
    active_donations: 0,
    requests_received: 0,
    requests_accepted: 0,
    scheduled_pickups: 0,
    completed_handovers: 0,
  });
  const [recentDonations, setRecentDonations] = useState([]);

  const load = async () => {
    setLoading(true);
    setError(null);
    try {
      const [dashRes, donRes] = await Promise.all([
        api.get('/api/dashboard'),
        api.get('/api/donations?scope=mine&limit=4'),
      ]);
      if (dashRes?.data) setMetrics(prev => ({ ...prev, ...dashRes.data }));
      if (donRes?.data) setRecentDonations(donRes.data);
    } catch (err) {
      setError(err instanceof ApiError ? err : new ApiError(500, 'LOAD_FAILED', 'Failed to load dashboard.'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  if (loading) return <LoadingState message="Loading your dashboard…" />;
  if (error) return <ErrorState error={error} onRetry={load} />;

  const statCards = [
    {
      label: 'Post Donations',
      value: metrics.total_donations,
      icon: Package,
      iconBg: '#eef6f1',
      iconColor: '#3a7c52',
      to: '/donor/donations/new',
    },
    {
      label: 'My Donations',
      value: metrics.total_donations,
      icon: Layers,
      iconBg: '#eff6ff',
      iconColor: '#3b82f6',
      to: '/donor/donations',
    },
    {
      label: 'Requests Received',
      value: metrics.requests_received,
      icon: Inbox,
      iconBg: '#fffbeb',
      iconColor: '#f59e0b',
      to: '/donor/requests',
    },
    {
      label: 'Scheduled Pickups',
      value: metrics.scheduled_pickups,
      icon: Clock,
      iconBg: '#f5f3ff',
      iconColor: '#8b5cf6',
      to: '/donor/pickups',
    },
  ];

  const bigStats = [
    { value: metrics.total_donations, label: 'Total\nDonations', color: '#3a7c52' },
    { value: metrics.requests_accepted, label: 'Accepted', color: '#3b82f6' },
    { value: metrics.scheduled_pickups, label: 'Scheduled', color: '#f59e0b' },
    { value: metrics.completed_handovers, label: 'Completed', color: '#10b981' },
  ];

  return (
    <div className="ss-fade-in" style={{ maxWidth: 1100 }}>
      {/* Page header */}
      <div style={{ marginBottom: '1.5rem', display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: '1rem', flexWrap: 'wrap' }}>
        <div>
          <h1 className="ss-page-title">Donor Dashboard</h1>
          <p style={{ fontSize: '.8125rem', color: 'var(--ss-muted)', marginTop: '.25rem' }}>
            Welcome back, <strong>{user?.name}</strong>
          </p>
        </div>
        <Link to="/donor/donations/new" className="ss-btn ss-btn-primary">
          <PlusCircle size={15} />
          Post Donation
        </Link>
      </div>

      {/* Big stat banner */}
      <div className="ss-card" style={{ padding: '1.25rem 1.5rem', marginBottom: '1.25rem', display: 'flex', alignItems: 'center', gap: '2rem', flexWrap: 'wrap' }}>
        <div style={{ flex: 1, minWidth: 140 }}>
          <div style={{ fontSize: '.75rem', color: 'var(--ss-muted)', fontWeight: 600, marginBottom: '.2rem' }}>Welcome back,</div>
          <div style={{ fontSize: '1rem', fontWeight: 700, color: 'var(--ss-text)' }}>{user?.name}</div>
        </div>
        <div style={{ display: 'flex', gap: '2rem', flexWrap: 'wrap' }}>
          {bigStats.map((s, i) => (
            <div key={i} style={{ textAlign: 'center' }}>
              <div style={{ fontSize: '1.625rem', fontWeight: 800, color: s.color, lineHeight: 1 }}>{s.value}</div>
              <div style={{ fontSize: '.6875rem', color: 'var(--ss-muted)', whiteSpace: 'pre', textAlign: 'center', marginTop: '.15rem' }}>{s.label}</div>
            </div>
          ))}
        </div>
      </div>

      {/* Quick action cards */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(200px,1fr))', gap: '.875rem', marginBottom: '1.5rem' }}>
        {statCards.map((card, i) => {
          const Icon = card.icon;
          return (
            <Link
              key={i}
              to={card.to}
              style={{ textDecoration: 'none' }}
            >
              <div className="ss-card" style={{ padding: '1.125rem', display: 'flex', alignItems: 'center', gap: '.875rem', transition: 'all .16s' }}
                onMouseEnter={e => { e.currentTarget.style.borderColor = 'var(--ss-green-border)'; e.currentTarget.style.boxShadow = 'var(--shadow-md)'; }}
                onMouseLeave={e => { e.currentTarget.style.borderColor = 'var(--ss-border)'; e.currentTarget.style.boxShadow = ''; }}
              >
                <div style={{ width: 42, height: 42, background: card.iconBg, borderRadius: 10, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                  <Icon size={20} style={{ color: card.iconColor }} />
                </div>
                <div>
                  <div style={{ fontSize: '.75rem', color: 'var(--ss-muted)', fontWeight: 600 }}>{card.label}</div>
                  <div style={{ fontSize: '1.375rem', fontWeight: 800, color: 'var(--ss-text)', lineHeight: 1.2 }}>{card.value}</div>
                </div>
              </div>
            </Link>
          );
        })}
      </div>

      {/* Recent donations */}
      <div className="ss-card" style={{ padding: '1.25rem 1.5rem' }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '1rem' }}>
          <h2 className="ss-section-title">Recent Donations</h2>
          <Link to="/donor/donations" style={{ fontSize: '.8125rem', fontWeight: 600, color: 'var(--ss-green)', textDecoration: 'none', display: 'flex', alignItems: 'center', gap: '.3rem' }}>
            View All <ArrowRight size={14} />
          </Link>
        </div>

        {recentDonations.length === 0 ? (
          <div className="ss-empty">
            <Package size={36} className="ss-empty-icon" />
            <div className="ss-empty-title">No donations yet</div>
            <div className="ss-empty-desc">Start by posting your first donation!</div>
            <Link to="/donor/donations/new" className="ss-btn ss-btn-primary ss-btn-sm" style={{ marginTop: '.5rem' }}>
              <PlusCircle size={14} /> Post Donation
            </Link>
          </div>
        ) : (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '.75rem' }}>
            {recentDonations.map(item => {
              const mainImageId = item.images?.[0]?.id || (Array.isArray(item.image_ids) ? item.image_ids[0] : null);
              const imageUrl = mainImageId ? `/api/media/donation-images/${mainImageId}` : null;
              const qty = item.available_quantity ?? item.total_quantity ?? 0;

              return (
                <div key={item.id} className="ss-item-card" style={{ display: 'flex', alignItems: 'center', gap: '.875rem', padding: '.875rem 1rem' }}>
                  {/* Thumbnail */}
                  {imageUrl ? (
                    <img src={imageUrl} alt={item.title} className="ss-item-thumb"
                      onError={e => { e.currentTarget.style.display = 'none'; }} />
                  ) : (
                    <div className="ss-item-thumb-placeholder"><Package size={24} /></div>
                  )}

                  {/* Info */}
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontWeight: 600, fontSize: '.875rem', color: 'var(--ss-text)', marginBottom: '.2rem', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                      {item.title}
                    </div>
                    <div style={{ fontSize: '.75rem', color: 'var(--ss-muted)' }}>
                      {qty} items · {item.category_name || 'General'} · <span style={{ textTransform: 'capitalize' }}>{(item.condition || 'good').replace('_', ' ')}</span>
                    </div>
                  </div>

                  {/* Status + action */}
                  <div style={{ display: 'flex', alignItems: 'center', gap: '.75rem', flexShrink: 0 }}>
                    <StatusBadge status={item.status} size="sm" />
                    <Link
                      to={`/donor/donations/${item.id}`}
                      className="ss-btn ss-btn-secondary ss-btn-sm"
                    >
                      <Eye size={13} /> View Details
                    </Link>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
}
