import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../auth/AuthContext';
import { api, ApiError } from '../../api/client';
import { PageHeader } from '../../components/PageHeader';
import { StatusBadge } from '../../components/StatusBadge';
import { LoadingState } from '../../components/LoadingState';
import { ErrorState } from '../../components/ErrorState';
import { EmptyState } from '../../components/EmptyState';
import { NgoVerificationBanner } from './NgoVerificationBanner';
import {
  ClipboardList,
  HeartHandshake,
  Clock,
  CheckCircle2,
  Package,
  PlusCircle,
  ArrowRight,
  MapPin,
  Calendar,
  TrendingUp,
  AlertTriangle,
} from 'lucide-react';

function MetricCard({ label, value, icon: Icon, color, to }) {
  const inner = (
    <div
      className={`bg-slate-900 border rounded-2xl p-5 flex items-start gap-4 transition-all group hover:border-${color}-500/40 hover:shadow-lg hover:shadow-${color}-950/30 ${
        to ? 'cursor-pointer' : ''
      } border-slate-800`}
    >
      <div
        className={`w-11 h-11 rounded-xl flex items-center justify-center shrink-0 bg-${color}-500/10 text-${color}-400 group-hover:scale-105 transition-transform`}
      >
        <Icon size={22} aria-hidden="true" />
      </div>
      <div>
        <p className={`text-2xl font-bold text-${color}-400 leading-none mb-1`}>{value}</p>
        <p className="text-xs text-slate-400">{label}</p>
      </div>
    </div>
  );
  return to ? <Link to={to}>{inner}</Link> : inner;
}

function UrgencyBadge({ urgency }) {
  const map = {
    critical: 'bg-red-500/15 text-red-400 border-red-500/30',
    high: 'bg-amber-500/15 text-amber-400 border-amber-500/30',
    medium: 'bg-blue-500/15 text-blue-400 border-blue-500/30',
    low: 'bg-slate-500/15 text-slate-400 border-slate-500/30',
  };
  const label = urgency ? urgency.charAt(0).toUpperCase() + urgency.slice(1) : 'Normal';
  return (
    <span
      className={`inline-flex items-center text-[10px] font-semibold px-2 py-0.5 rounded-full border ${
        map[urgency] || map.low
      }`}
    >
      {label}
    </span>
  );
}

export function NgoDashboard() {
  const { user } = useAuth();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [metrics, setMetrics] = useState({
    active_requirements: 0,
    matched_donations: 0,
    pending_requests: 0,
    scheduled_pickups: 0,
    completed_handovers: 0,
  });
  const [recentRequirements, setRecentRequirements] = useState([]);

  const ngoData = user?.ngo || {};
  const verificationStatus = ngoData.verification_status || 'pending';
  const isVerified = verificationStatus === 'verified';

  const loadData = async () => {
    setLoading(true);
    setError(null);
    try {
      const [dashRes, reqRes] = await Promise.all([
        api.get('/api/dashboard'),
        isVerified ? api.get('/api/requirements?scope=mine&limit=5') : Promise.resolve(null),
      ]);

      if (dashRes?.data) {
        setMetrics((prev) => ({ ...prev, ...dashRes.data }));
      }
      if (reqRes?.data) {
        setRecentRequirements(reqRes.data);
      }
    } catch (err) {
      setError(
        err instanceof ApiError
          ? err
          : new ApiError(500, 'LOAD_FAILED', 'Failed to load NGO dashboard data.')
      );
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  if (loading) return <LoadingState message="Loading your NGO dashboard…" />;
  if (error) return <ErrorState error={error} onRetry={loadData} />;

  return (
    <div className="space-y-8">
      <PageHeader
        title={ngoData.organization_name || 'NGO Dashboard'}
        subtitle="Manage community resource requirements and coordinate with surplus donations."
        action={<StatusBadge status={verificationStatus} size="md" />}
      />

      {/* Verification Banner */}
      <NgoVerificationBanner
        status={verificationStatus}
        adminNote={ngoData.admin_note}
      />

      {/* Metrics Grid */}
      <div>
        <h2 className="text-xs font-semibold text-slate-400 uppercase tracking-widest mb-4">
          Activity Overview
        </h2>
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
          <MetricCard
            label="Active Requirements"
            value={metrics.active_requirements ?? 0}
            icon={ClipboardList}
            color="emerald"
            to={isVerified ? '/ngo/requirements' : undefined}
          />
          <MetricCard
            label="Matched Donations"
            value={metrics.matched_donations ?? 0}
            icon={HeartHandshake}
            color="teal"
            to={isVerified ? '/ngo/matches' : undefined}
          />
          <MetricCard
            label="Pending Requests"
            value={metrics.pending_requests ?? 0}
            icon={Clock}
            color="amber"
            to={isVerified ? '/ngo/requests' : undefined}
          />
          <MetricCard
            label="Scheduled Pickups"
            value={metrics.scheduled_pickups ?? 0}
            icon={Package}
            color="purple"
            to={isVerified ? '/ngo/pickups' : undefined}
          />
          <MetricCard
            label="Completed"
            value={metrics.completed_handovers ?? 0}
            icon={CheckCircle2}
            color="cyan"
          />
        </div>
      </div>

      {/* Quick Actions — only for verified NGOs */}
      {isVerified && (
        <div>
          <h2 className="text-xs font-semibold text-slate-400 uppercase tracking-widest mb-4">
            Quick Actions
          </h2>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <Link
              to="/ngo/requirements/new"
              id="ngo-dashboard-post-requirement"
              className="group flex items-center gap-4 bg-gradient-to-br from-emerald-600/20 to-teal-600/10 border border-emerald-500/30 hover:border-emerald-500/60 rounded-2xl p-5 transition-all hover:shadow-lg hover:shadow-emerald-950/40"
            >
              <div className="w-11 h-11 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                <PlusCircle size={22} />
              </div>
              <div>
                <p className="text-sm font-semibold text-white">Post Requirement</p>
                <p className="text-xs text-slate-400 mt-0.5">
                  Tell donors what your NGO needs
                </p>
              </div>
            </Link>

            <Link
              to="/ngo/matches"
              id="ngo-dashboard-browse-matches"
              className="group flex items-center gap-4 bg-slate-900 border border-slate-800 hover:border-teal-500/40 rounded-2xl p-5 transition-all"
            >
              <div className="w-11 h-11 rounded-xl bg-teal-500/10 text-teal-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                <MapPin size={22} />
              </div>
              <div>
                <p className="text-sm font-semibold text-white">Browse Matches</p>
                <p className="text-xs text-slate-400 mt-0.5">
                  Explore ranked donation opportunities
                </p>
              </div>
            </Link>

            <Link
              to="/ngo/requests"
              id="ngo-dashboard-my-requests"
              className="group flex items-center gap-4 bg-slate-900 border border-slate-800 hover:border-purple-500/40 rounded-2xl p-5 transition-all"
            >
              <div className="w-11 h-11 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                <TrendingUp size={22} />
              </div>
              <div>
                <p className="text-sm font-semibold text-white">My Requests</p>
                <p className="text-xs text-slate-400 mt-0.5">
                  Track outgoing request statuses
                </p>
              </div>
            </Link>
          </div>
        </div>
      )}

      {/* Recent Requirements */}
      {isVerified && (
        <div>
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-xs font-semibold text-slate-400 uppercase tracking-widest">
              Recent Requirements
            </h2>
            <Link
              to="/ngo/requirements"
              className="flex items-center gap-1 text-xs text-emerald-400 hover:text-emerald-300 transition-colors"
            >
              View All <ArrowRight size={12} />
            </Link>
          </div>

          {recentRequirements.length === 0 ? (
            <EmptyState
              icon={ClipboardList}
              title="No requirements yet"
              message="Post your first requirement to start receiving matched donations from donors in your area."
              action={
                <Link
                  to="/ngo/requirements/new"
                  className="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-xl transition-all shadow-lg shadow-emerald-950/40"
                >
                  <PlusCircle size={16} />
                  Post First Requirement
                </Link>
              }
            />
          ) : (
            <div className="space-y-3">
              {recentRequirements.map((req) => {
                const needed = req.quantity_needed ?? 0;
                const allocated = req.quantity_allocated ?? 0;
                const fulfilled = req.quantity_fulfilled ?? 0;
                const pct = needed > 0 ? Math.min(100, Math.round((fulfilled / needed) * 100)) : 0;

                return (
                  <Link
                    key={req.id}
                    to={`/ngo/requirements/${req.id}/matches`}
                    className="block bg-slate-900 border border-slate-800 hover:border-emerald-500/30 rounded-2xl p-5 transition-all group"
                  >
                    <div className="flex items-start justify-between gap-4 mb-3">
                      <div className="flex-1 min-w-0">
                        <h3 className="text-sm font-semibold text-white truncate group-hover:text-emerald-300 transition-colors">
                          {req.title}
                        </h3>
                        <div className="flex items-center gap-3 mt-1">
                          <StatusBadge status={req.status} size="sm" />
                          <UrgencyBadge urgency={req.urgency} />
                          {req.needed_by && (
                            <span className="flex items-center gap-1 text-[11px] text-slate-400">
                              <Calendar size={11} />
                              {new Date(req.needed_by).toLocaleDateString('en-IN')}
                            </span>
                          )}
                        </div>
                      </div>
                      <ArrowRight
                        size={16}
                        className="text-slate-600 group-hover:text-emerald-400 shrink-0 mt-1 transition-colors"
                      />
                    </div>

                    {/* Progress Bar */}
                    <div>
                      <div className="flex items-center justify-between text-[11px] text-slate-400 mb-1.5">
                        <span>
                          <span className="text-white font-medium">{fulfilled}</span> of{' '}
                          <span className="text-white font-medium">{needed}</span> fulfilled
                          {allocated > fulfilled && (
                            <span className="ml-1 text-blue-400">
                              · {allocated - fulfilled} reserved
                            </span>
                          )}
                        </span>
                        <span>{pct}%</span>
                      </div>
                      <div
                        className="h-1.5 rounded-full bg-slate-800 overflow-hidden"
                        role="progressbar"
                        aria-valuenow={pct}
                        aria-valuemin={0}
                        aria-valuemax={100}
                        aria-label={`${fulfilled} of ${needed} units fulfilled`}
                      >
                        <div
                          className="h-full bg-gradient-to-r from-emerald-500 to-teal-400 rounded-full transition-all duration-500"
                          style={{ width: `${pct}%` }}
                        />
                      </div>
                    </div>
                  </Link>
                );
              })}
            </div>
          )}
        </div>
      )}

      {/* Non-verified placeholder */}
      {!isVerified && (
        <div className="bg-slate-900 border border-slate-800 rounded-2xl p-8 text-center space-y-4">
          <AlertTriangle size={32} className="mx-auto text-amber-400" />
          <h3 className="text-lg font-bold text-white">
            Awaiting Verification
          </h3>
          <p className="text-sm text-slate-400 max-w-md mx-auto">
            Once your organisation is verified by our admin team, you'll be able to post
            requirements, browse matched donations, and coordinate pickups.
          </p>
          <Link
            to="/profile"
            className="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium rounded-xl transition-all"
          >
            Review Organisation Profile
          </Link>
        </div>
      )}
    </div>
  );
}
