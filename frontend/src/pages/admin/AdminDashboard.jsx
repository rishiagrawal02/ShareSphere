import React, { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { api, ApiError } from '../../api/client';
import { useAuth } from '../../auth/AuthContext';
import { PageHeader } from '../../components/PageHeader';
import { LoadingState } from '../../components/LoadingState';
import { ErrorState } from '../../components/ErrorState';
import {
  Users,
  ShieldCheck,
  Package,
  CheckCircle2,
  Layers,
  FileText,
  BarChart3,
  ArrowRight,
  TrendingUp,
  Clock,
  ShieldAlert,
  Table as TableIcon,
  RefreshCw,
} from 'lucide-react';

export function AdminDashboard() {
  const { user } = useAuth();
  const [metrics, setMetrics] = useState(null);
  const [trends, setTrends] = useState([]);
  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [showTrendTable, setShowTrendTable] = useState(false);

  const loadDashboardData = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [dashRes, trendsRes, catRes] = await Promise.all([
        api.get('/api/dashboard'),
        api.get('/api/admin/reports/trends?metric=donations&interval=week'),
        api.get('/api/admin/reports/categories'),
      ]);

      setMetrics(dashRes?.data ?? {});
      setTrends(trendsRes?.data ?? []);
      setCategories(catRes?.data ?? []);
    } catch (err) {
      setError(
        err instanceof ApiError ? err : new ApiError(500, 'LOAD_FAILED', 'Could not load admin dashboard.')
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadDashboardData();
  }, [loadDashboardData]);

  if (loading) return <LoadingState message="Loading administrative dashboard…" />;
  if (error) return <ErrorState error={error} onRetry={loadDashboardData} />;

  const statCards = [
    {
      label: 'Registered Users',
      value: metrics?.total_users ?? 0,
      icon: Users,
      color: 'from-blue-600/20 to-indigo-600/10 border-blue-500/30 text-blue-400',
      to: '/admin/users',
      desc: 'Active donors, NGOs & admins',
    },
    {
      label: 'Pending NGOs',
      value: metrics?.pending_ngos ?? 0,
      icon: ShieldCheck,
      color: 'from-amber-600/20 to-orange-600/10 border-amber-500/30 text-amber-400',
      to: '/admin/ngos?status=pending',
      desc: 'Awaiting registration review',
      alert: (metrics?.pending_ngos ?? 0) > 0,
    },
    {
      label: 'Active Donations',
      value: metrics?.active_donations ?? 0,
      icon: Package,
      color: 'from-emerald-600/20 to-teal-600/10 border-emerald-500/30 text-emerald-400',
      to: '/admin/donations',
      desc: 'Available for NGO requests',
    },
    {
      label: 'Completed Handovers',
      value: metrics?.completed_handovers ?? 0,
      icon: CheckCircle2,
      color: 'from-teal-600/20 to-emerald-600/10 border-teal-500/30 text-teal-400',
      to: '/admin/reports',
      desc: 'Fully verified item transfers',
    },
  ];

  // SVG Trend line calculation
  const maxTrend = Math.max(...trends.map((t) => Number(t.count) || 0), 5);
  const trendPoints = trends.map((t, i) => {
    const x = trends.length > 1 ? (i / (trends.length - 1)) * 500 : 250;
    const y = 140 - ((Number(t.count) || 0) / maxTrend) * 110;
    return `${x},${y}`;
  }).join(' ');

  return (
    <div className="space-y-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
      <PageHeader
        title="Administrative Control Center"
        subtitle={`System overview and operational controls · Signed in as ${user?.name || 'Administrator'}`}
        action={
          <button
            type="button"
            onClick={loadDashboardData}
            aria-label="Refresh admin dashboard"
            className="flex items-center gap-1.5 px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs border border-slate-700 transition-all"
          >
            <RefreshCw size={14} />
          </button>
        }
      />

      {/* Metric Cards Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {statCards.map((card, idx) => {
          const Icon = card.icon;
          return (
            <Link
              key={idx}
              to={card.to}
              className={`bg-slate-900/80 border ${card.color} rounded-2xl p-5 shadow-lg backdrop-blur-sm relative overflow-hidden transition-all duration-200 hover:-translate-y-0.5 block group`}
            >
              <div className="flex items-center justify-between gap-2 mb-3">
                <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                  {card.label}
                </span>
                <div className={`p-2 rounded-xl bg-slate-950/60 border border-slate-800/80 ${card.color}`}>
                  <Icon size={18} />
                </div>
              </div>
              <div className="text-3xl font-extrabold text-white tracking-tight mb-1 flex items-center gap-2">
                <span>{card.value}</span>
                {card.alert && (
                  <span className="w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse" />
                )}
              </div>
              <p className="text-xs text-slate-400 group-hover:text-slate-300 transition-colors">
                {card.desc}
              </p>
            </Link>
          );
        })}
      </div>

      {/* Quick Action Navigation Hub */}
      <div className="space-y-3">
        <h3 className="text-sm font-bold text-white uppercase tracking-wider text-slate-400">
          Management Modules
        </h3>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <Link
            to="/admin/ngos"
            className="bg-slate-900 border border-slate-800 hover:border-slate-700 p-5 rounded-2xl flex items-center justify-between group transition-all"
          >
            <div className="flex items-center gap-3.5">
              <div className="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center">
                <ShieldCheck size={20} />
              </div>
              <div>
                <h4 className="text-sm font-bold text-white group-hover:text-emerald-400 transition-colors">
                  NGO Verification Queue
                </h4>
                <p className="text-xs text-slate-400">Approve or reject organization registrations</p>
              </div>
            </div>
            <ArrowRight size={16} className="text-slate-500 group-hover:text-white transition-colors" />
          </Link>

          <Link
            to="/admin/users"
            className="bg-slate-900 border border-slate-800 hover:border-slate-700 p-5 rounded-2xl flex items-center justify-between group transition-all"
          >
            <div className="flex items-center gap-3.5">
              <div className="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center">
                <Users size={20} />
              </div>
              <div>
                <h4 className="text-sm font-bold text-white group-hover:text-emerald-400 transition-colors">
                  User Directory
                </h4>
                <p className="text-xs text-slate-400">Manage account access, roles, and suspensions</p>
              </div>
            </div>
            <ArrowRight size={16} className="text-slate-500 group-hover:text-white transition-colors" />
          </Link>

          <Link
            to="/admin/categories"
            className="bg-slate-900 border border-slate-800 hover:border-slate-700 p-5 rounded-2xl flex items-center justify-between group transition-all"
          >
            <div className="flex items-center gap-3.5">
              <div className="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
                <Layers size={20} />
              </div>
              <div>
                <h4 className="text-sm font-bold text-white group-hover:text-emerald-400 transition-colors">
                  Categories & Compatibility
                </h4>
                <p className="text-xs text-slate-400">Configure item taxonomy and matching algorithms</p>
              </div>
            </div>
            <ArrowRight size={16} className="text-slate-500 group-hover:text-white transition-colors" />
          </Link>

          <Link
            to="/admin/donations"
            className="bg-slate-900 border border-slate-800 hover:border-slate-700 p-5 rounded-2xl flex items-center justify-between group transition-all"
          >
            <div className="flex items-center gap-3.5">
              <div className="w-10 h-10 rounded-xl bg-red-500/10 text-red-400 flex items-center justify-center">
                <ShieldAlert size={20} />
              </div>
              <div>
                <h4 className="text-sm font-bold text-white group-hover:text-emerald-400 transition-colors">
                  Listing Moderation
                </h4>
                <p className="text-xs text-slate-400">Inspect and moderate reported donation items</p>
              </div>
            </div>
            <ArrowRight size={16} className="text-slate-500 group-hover:text-white transition-colors" />
          </Link>

          <Link
            to="/admin/reports"
            className="bg-slate-900 border border-slate-800 hover:border-slate-700 p-5 rounded-2xl flex items-center justify-between group transition-all"
          >
            <div className="flex items-center gap-3.5">
              <div className="w-10 h-10 rounded-xl bg-teal-500/10 text-teal-400 flex items-center justify-center">
                <BarChart3 size={20} />
              </div>
              <div>
                <h4 className="text-sm font-bold text-white group-hover:text-emerald-400 transition-colors">
                  Analytics & Reports
                </h4>
                <p className="text-xs text-slate-400">Trends, fulfillment ratios, and CSV exports</p>
              </div>
            </div>
            <ArrowRight size={16} className="text-slate-500 group-hover:text-white transition-colors" />
          </Link>

          <Link
            to="/admin/audit-logs"
            className="bg-slate-900 border border-slate-800 hover:border-slate-700 p-5 rounded-2xl flex items-center justify-between group transition-all"
          >
            <div className="flex items-center gap-3.5">
              <div className="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center">
                <FileText size={20} />
              </div>
              <div>
                <h4 className="text-sm font-bold text-white group-hover:text-emerald-400 transition-colors">
                  Audit Logs
                </h4>
                <p className="text-xs text-slate-400">Review platform security and governance logs</p>
              </div>
            </div>
            <ArrowRight size={16} className="text-slate-500 group-hover:text-white transition-colors" />
          </Link>
        </div>
      </div>

      {/* Visual Analytics Preview Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Weekly Donations Trend */}
        <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4 shadow-xl">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-sm font-bold text-white flex items-center gap-2">
                <TrendingUp size={16} className="text-emerald-400" />
                <span>Donation Trends (Weekly)</span>
              </h3>
              <p className="text-xs text-slate-400 mt-0.5">Surplus item posting activity over time</p>
            </div>
            {/* T-16.2-04 Accessible data table toggle */}
            <button
              type="button"
              onClick={() => setShowTrendTable(!showTrendTable)}
              className="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium border border-slate-700 transition-all"
            >
              <TableIcon size={12} />
              <span>{showTrendTable ? 'Chart View' : 'Table View'}</span>
            </button>
          </div>

          {showTrendTable ? (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs text-slate-300">
                <thead className="bg-slate-950 text-slate-400 uppercase text-[10px] font-semibold border-b border-slate-800">
                  <tr>
                    <th scope="col" className="px-3 py-2">Period</th>
                    <th scope="col" className="px-3 py-2 text-right">Donations Posted</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800/60">
                  {trends.map((t, i) => (
                    <tr key={i}>
                      <td className="px-3 py-2 text-slate-300">{t.period ? new Date(t.period).toLocaleDateString('en-IN') : `Week ${i + 1}`}</td>
                      <td className="px-3 py-2 text-right font-bold text-emerald-400">{t.count}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : trends.length === 0 ? (
            <p className="text-xs text-slate-500 text-center py-10">No donation trend data available yet.</p>
          ) : (
            <div className="h-44 w-full bg-slate-950 rounded-xl p-3 flex flex-col justify-between">
              <svg viewBox="0 0 500 150" className="w-full h-full overflow-visible" role="img" aria-label="Donations weekly trend chart">
                <polyline
                  fill="none"
                  stroke="#10b981"
                  strokeWidth="3"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  points={trendPoints}
                />
                {trends.map((t, i) => {
                  const x = trends.length > 1 ? (i / (trends.length - 1)) * 500 : 250;
                  const y = 140 - ((Number(t.count) || 0) / maxTrend) * 110;
                  return (
                    <circle
                      key={i}
                      cx={x}
                      cy={y}
                      r="4"
                      className="fill-emerald-400 stroke-slate-950 stroke-2"
                    />
                  );
                })}
              </svg>
              <div className="flex justify-between text-[10px] text-slate-500 pt-1">
                <span>{trends[0]?.period ? new Date(trends[0].period).toLocaleDateString('en-IN', { month: 'short', day: 'numeric' }) : 'Start'}</span>
                <span>{trends[trends.length - 1]?.period ? new Date(trends[trends.length - 1].period).toLocaleDateString('en-IN', { month: 'short', day: 'numeric' }) : 'Recent'}</span>
              </div>
            </div>
          )}
        </div>

        {/* Category Distribution Preview */}
        <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4 shadow-xl">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-sm font-bold text-white flex items-center gap-2">
                <Layers size={16} className="text-emerald-400" />
                <span>Category Distribution</span>
              </h3>
              <p className="text-xs text-slate-400 mt-0.5">Top active categories across donations & needs</p>
            </div>
            <Link
              to="/admin/reports"
              className="text-xs text-emerald-400 hover:text-emerald-300 font-semibold"
            >
              All Reports →
            </Link>
          </div>

          <div className="space-y-3">
            {categories.slice(0, 5).map((cat) => {
              const count = Number(cat.donations_count ?? 0);
              const maxCat = Math.max(...categories.map((c) => Number(c.donations_count ?? 0)), 1);
              const pct = Math.min(100, Math.round((count / maxCat) * 100));

              return (
                <div key={cat.id} className="space-y-1">
                  <div className="flex justify-between text-xs">
                    <span className="text-slate-300 font-medium">{cat.name}</span>
                    <span className="text-slate-400">{count} donations</span>
                  </div>
                  <div className="w-full bg-slate-950 rounded-full h-2 overflow-hidden border border-slate-800">
                    <div
                      className="bg-gradient-to-r from-emerald-500 to-teal-400 h-full rounded-full transition-all"
                      style={{ width: `${pct}%` }}
                    />
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
}
