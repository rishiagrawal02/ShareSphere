import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../auth/AuthContext';
import { api, ApiError } from '../../api/client';
import { PageHeader } from '../../components/PageHeader';
import { StatusBadge } from '../../components/StatusBadge';
import { LoadingState } from '../../components/LoadingState';
import { ErrorState } from '../../components/ErrorState';
import { EmptyState } from '../../components/EmptyState';
import {
  Package,
  HeartHandshake,
  Clock,
  CheckCircle2,
  Inbox,
  PlusCircle,
  ArrowRight,
  TrendingUp,
  MapPin,
  Calendar,
  Layers,
  Sparkles,
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

  const loadDashboardData = async () => {
    setLoading(true);
    setError(null);
    try {
      const [dashRes, donRes] = await Promise.all([
        api.get('/api/dashboard'),
        api.get('/api/donations?scope=mine&limit=5'),
      ]);

      if (dashRes?.data) {
        setMetrics((prev) => ({
          ...prev,
          ...dashRes.data,
        }));
      }

      if (donRes?.data) {
        setRecentDonations(donRes.data);
      }
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err);
      } else {
        setError(new ApiError(500, 'LOAD_FAILED', 'Failed to load donor dashboard data.'));
      }
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadDashboardData();
  }, []);

  if (loading) {
    return <LoadingState message="Loading your donor dashboard & listings..." />;
  }

  if (error) {
    return <ErrorState error={error} onRetry={loadDashboardData} />;
  }

  const statCards = [
    {
      label: 'Total Donations',
      value: metrics.total_donations,
      icon: Package,
      color: 'from-blue-600/20 to-indigo-600/10 border-blue-500/30 text-blue-400',
      description: 'All items posted',
    },
    {
      label: 'Active Listings',
      value: metrics.active_donations,
      icon: Layers,
      color: 'from-emerald-600/20 to-teal-600/10 border-emerald-500/30 text-emerald-400',
      description: 'Ready for NGO match',
    },
    {
      label: 'Requests Received',
      value: metrics.requests_received,
      icon: Inbox,
      color: 'from-amber-600/20 to-orange-600/10 border-amber-500/30 text-amber-400',
      description: `${metrics.requests_accepted} accepted so far`,
      alert: metrics.requests_received > 0,
    },
    {
      label: 'Scheduled Pickups',
      value: metrics.scheduled_pickups,
      icon: Clock,
      color: 'from-purple-600/20 to-pink-600/10 border-purple-500/30 text-purple-400',
      description: 'Handovers in progress',
    },
    {
      label: 'Completed Handovers',
      value: metrics.completed_handovers,
      icon: CheckCircle2,
      color: 'from-teal-600/20 to-emerald-600/10 border-teal-500/30 text-teal-400',
      description: 'Direct community impact',
    },
  ];

  return (
    <div className="space-y-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 animate-fadeIn">
      {/* Page Header */}
      <PageHeader
        title={`Welcome back, ${user?.name || 'Donor'}!`}
        subtitle="Manage your surplus item listings, track incoming NGO requests, and review successful handovers."
        action={
          <Link
            to="/donor/donations/new"
            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-semibold text-sm shadow-lg shadow-emerald-950/50 hover:shadow-emerald-900/50 transition-all cursor-pointer"
          >
            <PlusCircle size={18} />
            <span>Post New Donation</span>
          </Link>
        }
      />

      {/* Pending Requests Notification Banner */}
      {metrics.requests_received > 0 && (
        <div className="bg-gradient-to-r from-amber-950/60 to-orange-950/40 border border-amber-800/80 rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-xl">
          <div className="flex items-center gap-3.5">
            <div className="w-10 h-10 rounded-xl bg-amber-600/20 border border-amber-500/30 flex items-center justify-center text-amber-400 shrink-0">
              <Inbox size={22} />
            </div>
            <div>
              <h4 className="text-sm font-bold text-white">
                You have {metrics.requests_received} {metrics.requests_received === 1 ? 'incoming request' : 'incoming requests'} from verified NGOs
              </h4>
              <p className="text-xs text-amber-300/80 mt-0.5">
                Review request quantities and accept them to unlock secure OTP pickup coordination.
              </p>
            </div>
          </div>
          <Link
            to="/donor/requests"
            className="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 transition-colors shrink-0"
          >
            <span>Review Requests</span>
            <ArrowRight size={14} />
          </Link>
        </div>
      )}

      {/* Metric Cards Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        {statCards.map((card, idx) => {
          const Icon = card.icon;
          return (
            <div
              key={idx}
              className={`bg-slate-900/80 border ${card.color} rounded-2xl p-5 shadow-lg backdrop-blur-sm relative overflow-hidden transition-all duration-200 hover:-translate-y-0.5`}
            >
              <div className="flex items-center justify-between gap-2 mb-3">
                <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                  {card.label}
                </span>
                <div className={`p-2 rounded-xl bg-slate-950/60 border border-slate-800/80 ${card.color}`}>
                  <Icon size={18} />
                </div>
              </div>
              <div className="text-3xl font-extrabold text-white tracking-tight mb-1">
                {card.value}
              </div>
              <p className="text-xs text-slate-400">
                {card.description}
              </p>
            </div>
          );
        })}
      </div>

      {/* Quick Action Hub */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Link
          to="/donor/donations/new"
          className="bg-slate-900/70 hover:bg-slate-900 border border-slate-800 hover:border-emerald-500/50 rounded-2xl p-5 flex items-start gap-4 transition-all group"
        >
          <div className="p-3 rounded-xl bg-emerald-950/60 border border-emerald-800/80 text-emerald-400 group-hover:scale-110 transition-transform">
            <PlusCircle size={22} />
          </div>
          <div>
            <h4 className="text-sm font-bold text-white group-hover:text-emerald-400 transition-colors">
              Donate Items
            </h4>
            <p className="text-xs text-slate-400 mt-1">
              Create a listing with photos, condition, category, and your approximate pickup location.
            </p>
          </div>
        </Link>

        <Link
          to="/donor/donations"
          className="bg-slate-900/70 hover:bg-slate-900 border border-slate-800 hover:border-blue-500/50 rounded-2xl p-5 flex items-start gap-4 transition-all group"
        >
          <div className="p-3 rounded-xl bg-blue-950/60 border border-blue-800/80 text-blue-400 group-hover:scale-110 transition-transform">
            <Package size={22} />
          </div>
          <div>
            <h4 className="text-sm font-bold text-white group-hover:text-blue-400 transition-colors">
              My Donations
            </h4>
            <p className="text-xs text-slate-400 mt-1">
              Track remaining inventory quantities, inspect status timelines, and view matched NGOs.
            </p>
          </div>
        </Link>

        <Link
          to="/donor/requests"
          className="bg-slate-900/70 hover:bg-slate-900 border border-slate-800 hover:border-amber-500/50 rounded-2xl p-5 flex items-start gap-4 transition-all group"
        >
          <div className="p-3 rounded-xl bg-amber-950/60 border border-amber-800/80 text-amber-400 group-hover:scale-110 transition-transform">
            <Inbox size={22} />
          </div>
          <div>
            <h4 className="text-sm font-bold text-white group-hover:text-amber-400 transition-colors">
              Requests & Handovers
            </h4>
            <p className="text-xs text-slate-400 mt-1">
              Review NGO claim requests, accept handovers, and verify 6-digit handover OTPs.
            </p>
          </div>
        </Link>
      </div>

      {/* Recent Donations Section */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <div>
            <h3 className="text-lg font-bold text-white">Recent Donation Listings</h3>
            <p className="text-xs text-slate-400">Your most recently created community listings</p>
          </div>
          <Link
            to="/donor/donations"
            className="text-xs font-semibold text-emerald-400 hover:text-emerald-300 flex items-center gap-1 transition-colors"
          >
            <span>View All Donations ({metrics.total_donations})</span>
            <ArrowRight size={14} />
          </Link>
        </div>

        {recentDonations.length === 0 ? (
          <EmptyState
            icon={Package}
            title="No donations listed yet"
            description="You have not created any donation listings yet. Share your surplus items with verified local NGOs today!"
            actionLabel="Create Your First Donation"
            onAction={() => window.location.assign('/donor/donations/new')}
          />
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            {recentDonations.map((item) => {
              const mainImageId = item.images?.[0]?.id || (Array.isArray(item.image_ids) ? item.image_ids[0] : null);
              const imageUrl = mainImageId ? `/api/media/donation-images/${mainImageId}` : null;
              const availableQty = item.available_quantity ?? item.total_quantity ?? 0;
              const totalQty = item.total_quantity ?? 0;

              return (
                <div
                  key={item.id}
                  className="bg-slate-900/80 border border-slate-800 hover:border-slate-700 rounded-2xl overflow-hidden shadow-lg transition-all duration-200 flex flex-col justify-between group"
                >
                  <div>
                    {/* Thumbnail Image Banner */}
                    <div className="h-44 w-full bg-slate-950 relative overflow-hidden flex items-center justify-center">
                      {imageUrl ? (
                        <img
                          src={imageUrl}
                          alt={item.title}
                          className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                          onError={(e) => {
                            e.currentTarget.style.display = 'none';
                          }}
                        />
                      ) : (
                        <div className="flex flex-col items-center gap-2 text-slate-600">
                          <Package size={36} />
                          <span className="text-[11px]">No image attached</span>
                        </div>
                      )}
                      <div className="absolute top-3 right-3">
                        <StatusBadge status={item.status} size="sm" />
                      </div>
                      <div className="absolute bottom-3 left-3 px-2.5 py-1 rounded-lg bg-slate-950/80 backdrop-blur-md border border-slate-800 text-[11px] font-semibold text-slate-200 capitalize">
                        {item.category_name || 'General Item'}
                      </div>
                    </div>

                    {/* Listing Content */}
                    <div className="p-5 space-y-3">
                      <h4 className="text-base font-bold text-white group-hover:text-emerald-400 transition-colors line-clamp-1">
                        {item.title}
                      </h4>
                      <p className="text-xs text-slate-400 line-clamp-2 leading-relaxed">
                        {item.description || 'No description provided.'}
                      </p>

                      {/* Quantity & Condition metrics */}
                      <div className="bg-slate-950/60 rounded-xl p-3 border border-slate-800/80 grid grid-cols-2 gap-2 text-xs">
                        <div>
                          <span className="text-slate-500 block text-[10px] uppercase font-semibold">Available</span>
                          <span className="font-bold text-emerald-400">
                            {availableQty} / {totalQty} units
                          </span>
                        </div>
                        <div>
                          <span className="text-slate-500 block text-[10px] uppercase font-semibold">Condition</span>
                          <span className="font-semibold text-slate-200 capitalize">
                            {(item.condition || 'good').replace('_', ' ')}
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Actions Footer */}
                  <div className="p-5 pt-0 border-t border-slate-800/60 mt-auto flex items-center justify-between gap-2">
                    <Link
                      to={`/donor/donations/${item.id}`}
                      className="text-xs font-semibold text-slate-300 hover:text-white py-2"
                    >
                      View Details
                    </Link>
                    <Link
                      to={`/donor/donations/${item.id}/matches`}
                      className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-950/60 hover:bg-emerald-900/60 border border-emerald-800/80 text-emerald-400 text-xs font-semibold transition-colors"
                    >
                      <Sparkles size={13} />
                      <span>Find Matches</span>
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
