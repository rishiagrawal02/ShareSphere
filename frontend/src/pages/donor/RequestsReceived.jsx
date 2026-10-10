import React, { useState, useEffect } from 'react';
import { useSearchParams, Link } from 'react-router-dom';
import { api, ApiError } from '../../api/client';
import { useToast } from '../../components/Toast';
import { PageHeader } from '../../components/PageHeader';
import { StatusBadge } from '../../components/StatusBadge';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { FormField } from '../../components/FormField';
import { LoadingState } from '../../components/LoadingState';
import { ErrorState } from '../../components/ErrorState';
import { EmptyState } from '../../components/EmptyState';
import {
  Inbox,
  CheckCircle2,
  XCircle,
  Clock,
  Award,
  Package,
  ArrowRight,
  Filter,
  Calendar,
  AlertCircle,
  Info,
  ShieldCheck,
} from 'lucide-react';

export function RequestsReceived() {
  const [searchParams] = useSearchParams();
  const donationIdParam = searchParams.get('donation_id');
  const { showSuccess, showError } = useToast();

  const [requests, setRequests] = useState([]);
  const [statusFilter, setStatusFilter] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Accept Dialog State
  const [requestToAccept, setRequestToAccept] = useState(null);
  const [accepting, setAccepting] = useState(false);

  // Reject Modal State
  const [requestToReject, setRequestToReject] = useState(null);
  const [rejectReason, setRejectReason] = useState('');
  const [rejecting, setRejecting] = useState(false);

  const fetchRequests = async () => {
    setLoading(true);
    setError(null);
    try {
      const params = new URLSearchParams();
      if (statusFilter) params.append('status', statusFilter);
      if (donationIdParam) params.append('donation_id', donationIdParam);

      const res = await api.get(`/api/requests?${params.toString()}`);
      if (Array.isArray(res?.data)) {
        // Sort: pending first, then by created_at DESC
        const sorted = [...res.data].sort((a, b) => {
          if (a.status === 'pending' && b.status !== 'pending') return -1;
          if (a.status !== 'pending' && b.status === 'pending') return 1;
          return new Date(b.created_at || 0) - new Date(a.created_at || 0);
        });
        setRequests(sorted);
      }
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err);
      } else {
        setError(new ApiError(500, 'LOAD_FAILED', 'Failed to load donation requests.'));
      }
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRequests();
  }, [statusFilter, donationIdParam]);

  // Format Expiry relative time
  const getExpiryCountdown = (expiresAt, createdAt) => {
    const target = expiresAt
      ? new Date(expiresAt).getTime()
      : createdAt
      ? new Date(createdAt).getTime() + 72 * 60 * 60 * 1000
      : null;

    if (!target) return null;

    const diffMs = target - Date.now();
    if (diffMs <= 0) return 'Expired';

    const hours = Math.floor(diffMs / (1000 * 60 * 60));
    const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

    if (hours > 24) {
      const days = Math.floor(hours / 24);
      return `Expires in ~${days}d ${hours % 24}h`;
    }
    if (hours > 0) {
      return `Expires in ~${hours}h ${minutes}m`;
    }
    return `Expires in ${minutes}m`;
  };

  // Handle Accept Request
  const handleAcceptConfirm = async () => {
    if (!requestToAccept) return;
    setAccepting(true);
    try {
      await api.post(`/api/requests/${requestToAccept.id}/accept`);
      showSuccess(`Accepted request from ${requestToAccept.organization_name || 'NGO'}. You can now coordinate pickup!`);
      setRequestToAccept(null);
      fetchRequests();
    } catch (err) {
      if (err.status === 409) {
        showError(err.message || 'Request status has changed or was cancelled by the NGO.');
      } else {
        showError(err.message || 'Failed to accept request.');
      }
      setRequestToAccept(null);
      fetchRequests();
    } finally {
      setAccepting(false);
    }
  };

  // Handle Reject Request
  const handleRejectSubmit = async (e) => {
    e?.preventDefault();
    if (!requestToReject) return;
    setRejecting(true);
    try {
      await api.post(`/api/requests/${requestToReject.id}/reject`, {
        reason: rejectReason.trim() || undefined,
      });
      showSuccess('Request rejected. The reserved quantity has been returned to your available donation balance.');
      setRequestToReject(null);
      setRejectReason('');
      fetchRequests();
    } catch (err) {
      if (err.status === 409) {
        showError(err.message || 'Request is no longer active.');
      } else {
        showError(err.message || 'Failed to reject request.');
      }
      setRequestToReject(null);
      fetchRequests();
    } finally {
      setRejecting(false);
    }
  };

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8 animate-fadeIn">
      {/* Header */}
      <PageHeader
        title="Donation Requests Received"
        subtitle="Review claim requests submitted by verified NGOs for your surplus donations. Accept requests to schedule pickup coordination."
      />

      {/* Filter Tabs */}
      <div className="flex flex-wrap items-center justify-between gap-4 bg-slate-900/90 border border-slate-800 p-4 rounded-2xl shadow-lg">
        <div className="flex items-center gap-2 overflow-x-auto pb-1 sm:pb-0">
          {[
            { id: '', label: 'All Requests' },
            { id: 'pending', label: 'Pending Review' },
            { id: 'accepted', label: 'Accepted' },
            { id: 'rejected', label: 'Rejected' },
            { id: 'cancelled', label: 'Cancelled' },
          ].map((tab) => (
            <button
              key={tab.id}
              type="button"
              onClick={() => setStatusFilter(tab.id)}
              className={`px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer whitespace-nowrap ${
                statusFilter === tab.id
                  ? 'bg-emerald-600 text-white shadow-sm'
                  : 'bg-slate-950 text-slate-400 hover:text-white hover:bg-slate-800'
              }`}
            >
              {tab.label}
            </button>
          ))}
        </div>

        {donationIdParam && (
          <Link
            to="/donor/requests"
            className="text-xs font-semibold text-emerald-400 hover:text-emerald-300"
          >
            Show All Listings' Requests
          </Link>
        )}
      </div>

      {/* Content Body */}
      {loading ? (
        <LoadingState message="Loading incoming NGO requests..." />
      ) : error ? (
        <ErrorState error={error} onRetry={fetchRequests} />
      ) : requests.length === 0 ? (
        <EmptyState
          icon={Inbox}
          title={statusFilter ? `No ${statusFilter} requests found` : 'No donation requests received yet'}
          description={
            statusFilter
              ? 'Try selecting a different status filter to view all requests.'
              : 'When verified NGOs discover and request items from your listings, they will appear here.'
          }
          actionLabel="View My Active Donations"
          onAction={() => window.location.assign('/donor/donations')}
        />
      ) : (
        <div className="space-y-4">
          {requests.map((req) => {
            const isPending = req.status === 'pending';
            const isAccepted = req.status === 'accepted';
            const countdown = isPending ? getExpiryCountdown(req.expires_at, req.created_at) : null;

            return (
              <div
                key={req.id}
                className={`bg-slate-900/90 border rounded-2xl p-5 sm:p-6 shadow-xl transition-all duration-200 flex flex-col md:flex-row items-start md:items-center justify-between gap-6 ${
                  isPending
                    ? 'border-amber-500/40 bg-gradient-to-r from-slate-900/95 to-amber-950/20'
                    : 'border-slate-800'
                }`}
              >
                {/* Left: Request details & NGO Info */}
                <div className="space-y-3 flex-1">
                  <div className="flex flex-wrap items-center gap-2.5">
                    <StatusBadge status={req.status} size="sm" />
                    
                    {req.urgency && (
                      <span className="text-[11px] font-bold px-2.5 py-0.5 rounded-md bg-slate-800 text-slate-300 uppercase border border-slate-700">
                        {req.urgency} Urgency
                      </span>
                    )}

                    {countdown && (
                      <span className="text-xs text-amber-400 font-semibold flex items-center gap-1 bg-amber-950/60 px-2.5 py-0.5 rounded-md border border-amber-800/80">
                        <Clock size={12} />
                        <span>{countdown}</span>
                      </span>
                    )}
                  </div>

                  <div>
                    <h3 className="text-lg font-bold text-white flex items-center gap-2">
                      <span>{req.organization_name || req.ngo_name || 'Verified NGO'}</span>
                      <ShieldCheck size={16} className="text-emerald-400" title="Verified NGO" />
                    </h3>
                    <p className="text-xs text-slate-400 mt-0.5">
                      Requested for Need: <strong className="text-slate-200">{req.requirement_title || 'General Community Need'}</strong>
                    </p>
                  </div>

                  {/* Quantity and Item info */}
                  <div className="flex flex-wrap items-center gap-4 text-xs bg-slate-950/60 p-3 rounded-xl border border-slate-800/80">
                    <div className="flex items-center gap-1.5">
                      <Package size={14} className="text-emerald-400" />
                      <span>Item: <strong className="text-white">{req.donation_title || 'Donation Item'}</strong></span>
                    </div>

                    <div className="flex items-center gap-1.5">
                      <Award size={14} className="text-amber-400" />
                      <span>Claim Quantity: <strong className="text-emerald-400">{req.requested_quantity ?? req.quantity ?? 1} units</strong></span>
                    </div>

                    <div className="flex items-center gap-1.5 text-slate-400">
                      <Calendar size={14} />
                      <span>Requested on {new Date(req.created_at || Date.now()).toLocaleDateString()}</span>
                    </div>
                  </div>

                  {req.notes && (
                    <p className="text-xs text-slate-300 italic bg-slate-950/30 p-2.5 rounded-lg border border-slate-800/40">
                      "{req.notes}"
                    </p>
                  )}
                </div>

                {/* Right: Decision Actions */}
                <div className="w-full md:w-auto flex flex-row md:flex-col items-stretch gap-2 shrink-0 border-t md:border-t-0 md:border-l border-slate-800 pt-4 md:pt-0 md:pl-6">
                  {isPending ? (
                    <>
                      <button
                        type="button"
                        onClick={() => setRequestToAccept(req)}
                        className="flex-1 md:flex-initial inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-semibold text-xs shadow-md transition-all cursor-pointer"
                      >
                        <CheckCircle2 size={15} />
                        <span>Accept Request</span>
                      </button>

                      <button
                        type="button"
                        onClick={() => {
                          setRequestToReject(req);
                          setRejectReason('');
                        }}
                        className="flex-1 md:flex-initial inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-red-950/60 hover:text-red-300 border border-slate-700 text-slate-300 font-semibold text-xs transition-colors cursor-pointer"
                      >
                        <XCircle size={15} />
                        <span>Reject</span>
                      </button>
                    </>
                  ) : isAccepted ? (
                    <div className="flex flex-col items-center md:items-end gap-1.5">
                      <span className="text-xs text-emerald-400 font-semibold flex items-center gap-1">
                        <CheckCircle2 size={14} />
                        <span>Request Accepted</span>
                      </span>
                      <Link
                        to={req.pickup_id ? `/pickups/${req.pickup_id}` : '/donor/pickups'}
                        className="text-xs text-purple-400 hover:text-purple-300 font-medium flex items-center gap-1"
                      >
                        <span>{req.pickup_id ? 'View Pickup' : 'Coordinate Pickup'}</span>
                        <ArrowRight size={12} />
                      </Link>
                    </div>
                  ) : (
                    <div className="text-xs text-slate-500 capitalize italic">
                      {req.status}
                    </div>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Accept Request Confirmation Dialog */}
      <ConfirmDialog
        isOpen={Boolean(requestToAccept)}
        title="Accept Donation Claim Request?"
        message={`By accepting this request, you agree to allocate ${requestToAccept?.requested_quantity} unit(s) of "${requestToAccept?.donation_title}" to ${requestToAccept?.organization_name || 'the NGO'}. You will then coordinate physical pickup.`}
        confirmLabel={accepting ? 'Accepting...' : 'Yes, Accept Request'}
        cancelLabel="Cancel"
        variant="primary"
        onConfirm={handleAcceptConfirm}
        onCancel={() => setRequestToAccept(null)}
      />

      {/* Reject Request Modal Dialog */}
      {requestToReject && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm animate-fadeIn">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-5">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-red-950/70 border border-red-800/80 flex items-center justify-center text-red-400">
                <XCircle size={22} />
              </div>
              <div>
                <h3 className="text-base font-bold text-white">Decline Donation Request</h3>
                <p className="text-xs text-slate-400">The reserved items will be released back to your available balance.</p>
              </div>
            </div>

            <form onSubmit={handleRejectSubmit} className="space-y-4">
              <FormField
                id="reject_reason"
                label="Reason for Declining (Optional)"
                hint="Let the NGO know why you cannot fulfill this request"
              >
                <textarea
                  id="reject_reason"
                  rows={3}
                  value={rejectReason}
                  onChange={(e) => setRejectReason(e.target.value)}
                  placeholder="e.g. Items reserved for local drive, pickup timing conflict, etc."
                  className="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-red-500/50 focus:border-red-500"
                />
              </FormField>

              <div className="flex items-center justify-end gap-3 pt-2">
                <button
                  type="button"
                  onClick={() => setRequestToReject(null)}
                  className="px-4 py-2 text-xs font-semibold rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={rejecting}
                  className="px-5 py-2 text-xs font-semibold rounded-xl bg-red-600 hover:bg-red-500 text-white transition-colors cursor-pointer shadow-lg shadow-red-950/50"
                >
                  {rejecting ? 'Declining...' : 'Confirm Rejection'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
