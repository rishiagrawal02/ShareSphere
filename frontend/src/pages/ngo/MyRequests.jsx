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
  Calendar,
  ArrowRight,
  Clock,
  X,
  RefreshCw,
  Package,
} from 'lucide-react';

const STATUS_FILTER_OPTIONS = [
  { value: '', label: 'All' },
  { value: 'pending', label: 'Pending' },
  { value: 'accepted', label: 'Accepted' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'cancelled', label: 'Cancelled' },
  { value: 'expired', label: 'Expired' },
];

function ExpiryHint({ expiresAt }) {
  if (!expiresAt) return null;
  const ms = new Date(expiresAt) - Date.now();
  if (ms <= 0) return <span className="text-xs text-slate-500">Expired</span>;

  const hours = Math.floor(ms / 3600000);
  const mins = Math.floor((ms % 3600000) / 60000);
  const urgent = hours < 2;
  return (
    <span className={`flex items-center gap-1 text-xs font-medium ${urgent ? 'text-red-400' : 'text-amber-400'}`}>
      <Clock size={12} />
      Expires in {hours > 0 ? `${hours}h ` : ''}{mins}m
    </span>
  );
}

export function MyRequests() {
  const { showSuccess, showError } = useToast();

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [requests, setRequests] = useState([]);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [statusFilter, setStatusFilter] = useState('');

  const [cancelConfirm, setCancelConfirm] = useState(null); // { id, donation_title }
  const [isCancelling, setIsCancelling] = useState(false);

  const load = useCallback(async (pg = 1) => {
    setLoading(true);
    setError(null);
    try {
      const params = new URLSearchParams({ scope: 'sent', page: pg, limit: 10 });
      if (statusFilter) params.set('status', statusFilter);
      const res = await api.get(`/api/requests?${params}`);
      setRequests(res?.data ?? []);
      setTotalPages(res?.meta?.total_pages ?? 1);
      setPage(pg);
    } catch (err) {
      setError(
        err instanceof ApiError ? err : new ApiError(500, 'LOAD_FAILED', 'Could not load requests.')
      );
    } finally {
      setLoading(false);
    }
  }, [statusFilter]);

  useEffect(() => {
    load(1);
  }, [load]);

  const handleCancel = async () => {
    if (!cancelConfirm) return;
    setIsCancelling(true);
    try {
      await api.post(`/api/requests/${cancelConfirm.id}/cancel`, { reason: 'Cancelled by NGO' });
      showSuccess('Request cancelled successfully. Any reserved stock has been released.');
      setCancelConfirm(null);
      load(page);
    } catch (err) {
      showError(err?.message || 'Failed to cancel request.');
    } finally {
      setIsCancelling(false);
    }
  };

  const emptyMessage = statusFilter
    ? 'No requests match the selected filter.'
    : "You haven't sent any requests yet. Browse matched donations to get started.";

  return (
    <div className="space-y-6">
      <PageHeader
        title="My Requests"
        subtitle="Track the status of your outgoing donation requests."
      />

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-3">
        <label className="text-xs text-slate-400 font-medium">Status:</label>
        <div className="flex flex-wrap gap-2">
          {STATUS_FILTER_OPTIONS.map((opt) => (
            <button
              key={opt.value}
              type="button"
              id={`req-filter-${opt.value || 'all'}`}
              onClick={() => setStatusFilter(opt.value)}
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
          aria-label="Refresh requests list"
        >
          <RefreshCw size={13} />
          Refresh
        </button>
      </div>

      {loading ? (
        <LoadingState message="Loading requests..." />
      ) : error ? (
        <ErrorState error={error} onRetry={() => load(page)} />
      ) : requests.length === 0 ? (
        <EmptyState
          icon={ClipboardList}
          title="No requests found"
          message={emptyMessage}
          action={
            !statusFilter && (
              <Link
                to="/ngo/requirements"
                className="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-xl transition-all shadow-lg shadow-emerald-950/40"
              >
                Browse Requirements &amp; Matches
              </Link>
            )
          }
        />
      ) : (
        <div className="space-y-4">
          {requests.map((req) => {
            const isPending = req.status === 'pending';
            const isAccepted = req.status === 'accepted';

            return (
              <div
                key={req.id}
                className="bg-slate-900 border border-slate-800 hover:border-slate-700 rounded-2xl p-5 transition-all"
              >
                <div className="flex items-start gap-4">
                  {/* Icon */}
                  <div className="w-10 h-10 rounded-xl bg-slate-800 text-slate-400 flex items-center justify-center shrink-0">
                    <Package size={18} />
                  </div>

                  {/* Content */}
                  <div className="flex-1 min-w-0 space-y-2">
                    <div className="flex items-start justify-between gap-4">
                      <div>
                        <h3 className="text-sm font-semibold text-white truncate">
                          {req.donation_title ?? req.title ?? `Request #${req.id}`}
                        </h3>
                        {req.requirement_title && (
                          <p className="text-xs text-slate-400 mt-0.5">
                            For requirement: {req.requirement_title}
                          </p>
                        )}
                      </div>
                      <StatusBadge status={req.status} size="sm" />
                    </div>

                    <div className="flex flex-wrap items-center gap-3 text-xs text-slate-400">
                      <span>
                        Requested:{' '}
                        <strong className="text-white">{req.requested_quantity ?? '—'}</strong> units
                      </span>
                      {req.created_at && (
                        <span className="flex items-center gap-1">
                          <Calendar size={11} />
                          {new Date(req.created_at).toLocaleDateString('en-IN')}
                        </span>
                      )}
                      {isPending && <ExpiryHint expiresAt={req.expires_at} />}
                    </div>

                    {/* Rejection / Cancellation reason */}
                    {(req.status === 'rejected' || req.status === 'cancelled') && req.reason && (
                      <p className="text-xs text-slate-400 bg-slate-800 rounded-xl px-3 py-2 border border-slate-700">
                        <span className="font-medium text-slate-300">Reason: </span>
                        {req.reason}
                      </p>
                    )}
                  </div>

                  {/* Actions */}
                  <div className="flex flex-col gap-2 shrink-0">
                    {isPending && (
                      <button
                        type="button"
                        id={`cancel-req-${req.id}`}
                        onClick={() =>
                          setCancelConfirm({ id: req.id, donation_title: req.donation_title })
                        }
                        className="flex items-center gap-1 px-3 py-1.5 bg-red-500/10 hover:bg-red-500/20 text-red-400 rounded-lg text-xs border border-red-500/20 transition-all"
                      >
                        <X size={12} />
                        Cancel
                      </button>
                    )}
                    {isAccepted && req.pickup_id && (
                      <Link
                        to={`/ngo/pickups/${req.pickup_id}`}
                        id={`goto-pickup-${req.id}`}
                        className="flex items-center gap-1 px-3 py-1.5 bg-purple-500/10 hover:bg-purple-500/20 text-purple-400 rounded-lg text-xs border border-purple-500/20 transition-all"
                      >
                        Schedule Pickup
                        <ArrowRight size={12} />
                      </Link>
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

      {/* Cancel Confirm Dialog */}
      <ConfirmDialog
        isOpen={Boolean(cancelConfirm)}
        title="Cancel Request"
        message={`Cancel your request for "${cancelConfirm?.donation_title}"? Any reserved stock will be released back to the donor.`}
        confirmLabel="Yes, Cancel Request"
        cancelLabel="Keep Request"
        isDanger
        isLoading={isCancelling}
        onConfirm={handleCancel}
        onCancel={() => setCancelConfirm(null)}
      />
    </div>
  );
}
