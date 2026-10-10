import React, { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { api, ApiError } from '../../api/client';
import { useToast } from '../../components/Toast';
import { PageHeader } from '../../components/PageHeader';
import { StatusBadge } from '../../components/StatusBadge';
import { LoadingState } from '../../components/LoadingState';
import { ErrorState } from '../../components/ErrorState';
import { EmptyState } from '../../components/EmptyState';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { Pagination } from '../../components/Pagination';
import {
  ClipboardList,
  PlusCircle,
  ArrowRight,
  Calendar,
  MapPin,
  X,
  RefreshCw,
  AlertTriangle,
} from 'lucide-react';

const URGENCY_COLORS = {
  critical: 'text-red-400',
  high: 'text-amber-400',
  medium: 'text-blue-400',
  low: 'text-slate-400',
};

const STATUS_FILTER_OPTIONS = [
  { value: '', label: 'All Statuses' },
  { value: 'active', label: 'Active' },
  { value: 'closed', label: 'Closed' },
  { value: 'fulfilled', label: 'Fulfilled' },
  { value: 'partially_fulfilled', label: 'Partially Fulfilled' },
];

export function MyRequirements() {
  const { showSuccess, showError } = useToast();

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [requirements, setRequirements] = useState([]);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [statusFilter, setStatusFilter] = useState('');

  const [closingId, setClosingId] = useState(null);
  const [confirmClose, setConfirmClose] = useState(null); // { id, title }
  const [isClosing, setIsClosing] = useState(false);

  const load = useCallback(async (pg = 1) => {
    setLoading(true);
    setError(null);
    try {
      const params = new URLSearchParams({ scope: 'mine', page: pg, limit: 10 });
      if (statusFilter) params.set('status', statusFilter);
      const res = await api.get(`/api/requirements?${params}`);
      setRequirements(res?.data ?? []);
      setTotalPages(res?.meta?.total_pages ?? 1);
      setPage(pg);
    } catch (err) {
      setError(
        err instanceof ApiError ? err : new ApiError(500, 'LOAD_FAILED', 'Could not load requirements.')
      );
    } finally {
      setLoading(false);
    }
  }, [statusFilter]);

  useEffect(() => {
    load(1);
  }, [load]);

  const handleClose = async () => {
    if (!confirmClose) return;
    setIsClosing(true);
    try {
      await api.post(`/api/requirements/${confirmClose.id}/close`, {});
      showSuccess('Requirement closed successfully.');
      setConfirmClose(null);
      load(page);
    } catch (err) {
      showError(err?.message || 'Failed to close requirement.');
    } finally {
      setIsClosing(false);
    }
  };

  return (
    <div className="space-y-6">
      <PageHeader
        title="My Requirements"
        subtitle="Track your organisation's resource needs and fulfillment progress."
        action={
          <Link
            to="/ngo/requirements/new"
            id="my-requirements-post-new"
            className="flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-sm font-semibold shadow-lg shadow-emerald-950/40 transition-all"
          >
            <PlusCircle size={15} />
            Post Requirement
          </Link>
        }
      />

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-3">
        <label className="text-xs text-slate-400 font-medium">Filter by status:</label>
        <div className="flex flex-wrap gap-2">
          {STATUS_FILTER_OPTIONS.map((opt) => (
            <button
              key={opt.value}
              type="button"
              id={`filter-status-${opt.value || 'all'}`}
              onClick={() => { setStatusFilter(opt.value); }}
              className={`px-3 py-1.5 rounded-lg text-xs font-medium border transition-all ${
                statusFilter === opt.value
                  ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30'
                  : 'bg-slate-800 text-slate-400 border-slate-700 hover:border-slate-600'
              }`}
            >
              {opt.label}
            </button>
          ))}
        </div>
        <button
          type="button"
          onClick={() => load(page)}
          className="ml-auto flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-xs border border-slate-700 transition-all"
          aria-label="Refresh requirements list"
        >
          <RefreshCw size={13} />
          Refresh
        </button>
      </div>

      {loading ? (
        <LoadingState message="Loading requirements…" />
      ) : error ? (
        <ErrorState error={error} onRetry={() => load(page)} />
      ) : requirements.length === 0 ? (
        <EmptyState
          icon={ClipboardList}
          title="No requirements found"
          message={
            statusFilter
              ? 'No requirements match the selected filter.'
              : 'Post your first requirement to start receiving matched donations.'
          }
          action={
            !statusFilter && (
              <Link
                to="/ngo/requirements/new"
                className="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-xl transition-all shadow-lg shadow-emerald-950/40"
              >
                <PlusCircle size={16} />
                Post First Requirement
              </Link>
            )
          }
        />
      ) : (
        <div className="space-y-4">
          {requirements.map((req) => {
            const needed = req.quantity_needed ?? 0;
            const allocated = req.quantity_allocated ?? 0;
            const fulfilled = req.quantity_fulfilled ?? 0;
            const outstanding = Math.max(0, needed - allocated);
            const pct = needed > 0 ? Math.min(100, Math.round((fulfilled / needed) * 100)) : 0;
            const allocPct = needed > 0 ? Math.min(100, Math.round((allocated / needed) * 100)) : 0;
            const urgencyColor = URGENCY_COLORS[req.urgency] || URGENCY_COLORS.low;
            const isClosed = req.status === 'closed' || req.status === 'fulfilled';

            return (
              <div
                key={req.id}
                className="bg-slate-900 border border-slate-800 hover:border-slate-700 rounded-2xl p-6 transition-all"
              >
                {/* Header Row */}
                <div className="flex items-start gap-4 mb-4">
                  <div className="flex-1 min-w-0">
                    <div className="flex flex-wrap items-center gap-2 mb-1">
                      <h3 className="text-sm font-semibold text-white truncate">{req.title}</h3>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                      <StatusBadge status={req.status} size="sm" />
                      <span className={`text-xs font-semibold ${urgencyColor}`}>
                        {req.urgency ? req.urgency.charAt(0).toUpperCase() + req.urgency.slice(1) : ''} Priority
                      </span>
                      {req.category_name && (
                        <span className="text-[11px] text-slate-400 bg-slate-800 px-2 py-0.5 rounded-full">
                          {req.category_name}
                        </span>
                      )}
                      {req.needed_by && (
                        <span className="flex items-center gap-1 text-[11px] text-slate-400">
                          <Calendar size={11} />
                          Needed by {new Date(req.needed_by).toLocaleDateString('en-IN')}
                        </span>
                      )}
                      {req.radius_km && (
                        <span className="flex items-center gap-1 text-[11px] text-slate-400">
                          <MapPin size={11} />
                          {req.radius_km} km radius
                        </span>
                      )}
                    </div>
                  </div>

                  {/* Actions */}
                  <div className="flex items-center gap-2 shrink-0">
                    {!isClosed && (
                      <>
                        <Link
                          to={`/ngo/requirements/${req.id}/edit`}
                          id={`edit-req-${req.id}`}
                          className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-xs border border-slate-700 transition-all"
                        >
                          Edit
                        </Link>
                        <button
                          type="button"
                          id={`close-req-${req.id}`}
                          onClick={() => setConfirmClose({ id: req.id, title: req.title })}
                          className="flex items-center gap-1 px-3 py-1.5 bg-red-500/10 hover:bg-red-500/20 text-red-400 rounded-lg text-xs border border-red-500/20 transition-all"
                        >
                          <X size={12} />
                          Close
                        </button>
                      </>
                    )}
                    <Link
                      to={`/ngo/requirements/${req.id}/matches`}
                      id={`view-matches-${req.id}`}
                      className="flex items-center gap-1 px-3 py-1.5 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 rounded-lg text-xs border border-emerald-500/20 transition-all"
                    >
                      View Matches
                      <ArrowRight size={12} />
                    </Link>
                  </div>
                </div>

                {/* Progress */}
                <div>
                  <div className="flex items-center justify-between text-xs text-slate-400 mb-1.5">
                    <span>
                      <span className="text-white font-medium">{fulfilled}</span> of{' '}
                      <span className="text-white font-medium">{needed}</span> fulfilled
                      {allocated > fulfilled && (
                        <span className="ml-2 text-blue-400">· {allocated - fulfilled} reserved</span>
                      )}
                      {outstanding > 0 && !isClosed && (
                        <span className="ml-2 text-amber-400">· {outstanding} outstanding</span>
                      )}
                    </span>
                    <span>{pct}% complete</span>
                  </div>

                  {/* Stacked Progress Bar: fulfilled (green) + reserved (blue) */}
                  <div
                    className="relative h-2 rounded-full bg-slate-800 overflow-hidden"
                    role="progressbar"
                    aria-valuenow={pct}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-label={`${fulfilled} of ${needed} units fulfilled, ${allocated - fulfilled} reserved`}
                  >
                    {/* reserved (blue) behind */}
                    <div
                      className="absolute inset-y-0 left-0 bg-blue-500/60 rounded-full transition-all duration-500"
                      style={{ width: `${allocPct}%` }}
                    />
                    {/* fulfilled (green) on top */}
                    <div
                      className="absolute inset-y-0 left-0 bg-gradient-to-r from-emerald-500 to-teal-400 rounded-full transition-all duration-500"
                      style={{ width: `${pct}%` }}
                    />
                  </div>

                  {/* Legend */}
                  <div className="flex items-center gap-4 mt-1.5">
                    <span className="flex items-center gap-1.5 text-[10px] text-emerald-400">
                      <span className="w-2 h-2 rounded-full bg-emerald-400 inline-block" /> Fulfilled ({fulfilled})
                    </span>
                    {allocated > fulfilled && (
                      <span className="flex items-center gap-1.5 text-[10px] text-blue-400">
                        <span className="w-2 h-2 rounded-full bg-blue-500/60 inline-block" /> Reserved ({allocated - fulfilled})
                      </span>
                    )}
                    {outstanding > 0 && (
                      <span className="flex items-center gap-1.5 text-[10px] text-slate-400">
                        <span className="w-2 h-2 rounded-full bg-slate-600 inline-block" /> Outstanding ({outstanding})
                      </span>
                    )}
                  </div>
                </div>
              </div>
            );
          })}

          {totalPages > 1 && (
            <Pagination
              page={page}
              totalPages={totalPages}
              onPageChange={(p) => load(p)}
            />
          )}
        </div>
      )}

      {/* Close Confirmation Dialog */}
      <ConfirmDialog
        isOpen={Boolean(confirmClose)}
        title="Close Requirement"
        message={`Are you sure you want to close "${confirmClose?.title}"? Active reserved allocations will be unaffected, but no new requests can be made.`}
        confirmLabel="Close Requirement"
        cancelLabel="Keep Open"
        isDanger
        isLoading={isClosing}
        onConfirm={handleClose}
        onCancel={() => setConfirmClose(null)}
      />
    </div>
  );
}
