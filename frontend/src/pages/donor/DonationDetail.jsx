import React, { useState, useEffect } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { api, ApiError } from '../../api/client';
import { useToast } from '../../components/Toast';
import { PageHeader } from '../../components/PageHeader';
import { StatusBadge } from '../../components/StatusBadge';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { LoadingState } from '../../components/LoadingState';
import { ErrorState } from '../../components/ErrorState';
import {
  Package,
  Sparkles,
  MapPin,
  Calendar,
  Layers,
  Edit3,
  Trash2,
  Inbox,
  ArrowLeft,
  Clock,
  CheckCircle2,
  FileText,
  ShieldCheck,
  Award,
  ChevronRight,
} from 'lucide-react';

export function DonationDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { showSuccess, showError } = useToast();

  const [donation, setDonation] = useState(null);
  const [history, setHistory] = useState([]);
  const [activeImageIdx, setActiveImageIdx] = useState(0);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Close Dialog State
  const [showCloseDialog, setShowCloseDialog] = useState(false);
  const [closing, setClosing] = useState(false);

  const loadDonation = async () => {
    setLoading(true);
    setError(null);
    try {
      const [donRes, histRes] = await Promise.all([
        api.get(`/api/donations/${id}`),
        api.get(`/api/donations/${id}/history`).catch(() => ({ data: [] })),
      ]);

      if (donRes?.donation) {
        setDonation(donRes.donation);
      } else {
        throw new ApiError(404, 'NOT_FOUND', 'Donation not found.');
      }

      if (Array.isArray(histRes)) {
        setHistory(histRes);
      } else if (Array.isArray(histRes?.data)) {
        setHistory(histRes.data);
      }
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err);
      } else {
        setError(new ApiError(500, 'FETCH_FAILED', 'Failed to load donation details.'));
      }
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadDonation();
  }, [id]);

  const handleCloseConfirm = async () => {
    setClosing(true);
    try {
      await api.delete(`/api/donations/${id}`);
      showSuccess('Donation listing closed successfully.');
      setShowCloseDialog(false);
      loadDonation();
    } catch (err) {
      if (err.status === 409) {
        showError(err.message || 'Cannot close listing while active claim allocations exist.');
      } else {
        showError(err.message || 'Failed to close donation.');
      }
    } finally {
      setClosing(false);
    }
  };

  if (loading) {
    return <LoadingState message="Loading donation details & history timeline..." />;
  }

  if (error) {
    return <ErrorState error={error} onRetry={loadDonation} />;
  }

  if (!donation) return null;

  const images = Array.isArray(donation.images) ? donation.images : [];
  const totalQty = donation.total_quantity || 0;
  const availableQty = donation.available_quantity ?? totalQty;
  const allocatedQty = totalQty - availableQty;
  const isClosed = donation.status === 'completed' || donation.status === 'cancelled';

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8 animate-fadeIn">
      {/* Navigation Breadcrumb */}
      <div className="flex items-center justify-between">
        <Link
          to="/donor/donations"
          className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-400 hover:text-white transition-colors"
        >
          <ArrowLeft size={14} />
          <span>Back to My Donations</span>
        </Link>

        <div className="flex items-center gap-2">
          {!isClosed && (
            <>
              <Link
                to={`/donor/donations/${id}/edit`}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold transition-colors"
              >
                <Edit3 size={13} />
                <span>Edit</span>
              </Link>
              <button
                type="button"
                onClick={() => setShowCloseDialog(true)}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-red-950/60 hover:bg-red-900/60 border border-red-800/80 text-red-300 text-xs font-semibold transition-colors cursor-pointer"
              >
                <Trash2 size={13} />
                <span>Close Listing</span>
              </button>
            </>
          )}
        </div>
      </div>

      {/* Main Listing Header & Showcase */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
        {/* Left Column: Image Gallery */}
        <div className="lg:col-span-5 space-y-4">
          <div className="aspect-4/3 w-full rounded-2xl bg-slate-950 border border-slate-800 overflow-hidden relative shadow-xl flex items-center justify-center">
            {images.length > 0 ? (
              <img
                src={`/api/media/donation-images/${images[activeImageIdx]?.id || images[0]?.id}`}
                alt={donation.title}
                className="w-full h-full object-cover"
              />
            ) : (
              <div className="flex flex-col items-center gap-2 text-slate-600">
                <Package size={56} />
                <span className="text-xs">No photos attached</span>
              </div>
            )}
            <div className="absolute top-3 right-3">
              <StatusBadge status={donation.status} />
            </div>
          </div>

          {/* Thumbnail row */}
          {images.length > 1 && (
            <div className="flex items-center gap-3 overflow-x-auto pb-2">
              {images.map((img, idx) => (
                <button
                  key={img.id}
                  type="button"
                  onClick={() => setActiveImageIdx(idx)}
                  className={`w-16 h-16 rounded-xl overflow-hidden border-2 shrink-0 transition-all cursor-pointer ${
                    activeImageIdx === idx ? 'border-emerald-500 scale-105' : 'border-slate-800 opacity-60 hover:opacity-100'
                  }`}
                >
                  <img
                    src={`/api/media/donation-images/${img.id}`}
                    alt={`Thumbnail ${idx + 1}`}
                    className="w-full h-full object-cover"
                  />
                </button>
              ))}
            </div>
          )}

          {/* Quick Action Box */}
          <div className="bg-slate-900/90 border border-slate-800 rounded-2xl p-4 space-y-3">
            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400">
              Community Actions
            </h4>
            <div className="grid grid-cols-1 gap-2">
              <Link
                to={`/donor/donations/${id}/matches`}
                className="inline-flex items-center justify-between p-3 rounded-xl bg-gradient-to-r from-emerald-950/70 to-teal-950/70 border border-emerald-800/80 text-emerald-300 hover:text-white transition-all group"
              >
                <div className="flex items-center gap-2.5">
                  <Sparkles size={16} className="text-emerald-400 group-hover:scale-110 transition-transform" />
                  <span className="text-xs font-semibold">Find Matched NGO Needs</span>
                </div>
                <ChevronRight size={14} />
              </Link>

              <Link
                to={`/donor/requests?donation_id=${id}`}
                className="inline-flex items-center justify-between p-3 rounded-xl bg-slate-950 hover:bg-slate-800/80 border border-slate-800 text-slate-300 hover:text-white transition-all group"
              >
                <div className="flex items-center gap-2.5">
                  <Inbox size={16} className="text-amber-400 group-hover:scale-110 transition-transform" />
                  <span className="text-xs font-semibold">View Claim Requests</span>
                </div>
                <ChevronRight size={14} />
              </Link>
            </div>
          </div>
        </div>

        {/* Right Column: Listing Details & Breakdown */}
        <div className="lg:col-span-7 space-y-6">
          <div className="space-y-3">
            <div className="flex items-center gap-2 text-xs font-semibold text-emerald-400">
              <Layers size={14} />
              <span className="capitalize">{donation.category_name || 'Item'}</span>
              <span>•</span>
              <span className="text-slate-400">Posted on {new Date(donation.created_at || Date.now()).toLocaleDateString()}</span>
            </div>

            <h1 className="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
              {donation.title}
            </h1>

            <p className="text-sm text-slate-300 leading-relaxed whitespace-pre-line">
              {donation.description || 'No description provided.'}
            </p>
          </div>

          {/* Inventory Breakdown Cards */}
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div className="bg-slate-900/90 border border-slate-800 rounded-xl p-4">
              <span className="text-[11px] font-semibold uppercase text-slate-400 block mb-1">
                Total Listed
              </span>
              <span className="text-2xl font-black text-white">
                {totalQty} <span className="text-xs font-normal text-slate-400">units</span>
              </span>
            </div>

            <div className="bg-slate-900/90 border border-emerald-900/60 rounded-xl p-4">
              <span className="text-[11px] font-semibold uppercase text-emerald-400 block mb-1">
                Available Now
              </span>
              <span className="text-2xl font-black text-emerald-400">
                {availableQty} <span className="text-xs font-normal text-emerald-300">units</span>
              </span>
            </div>

            <div className="bg-slate-900/90 border border-amber-900/60 rounded-xl p-4 col-span-2 sm:col-span-1">
              <span className="text-[11px] font-semibold uppercase text-amber-400 block mb-1">
                Allocated / Claimed
              </span>
              <span className="text-2xl font-black text-amber-400">
                {allocatedQty} <span className="text-xs font-normal text-amber-300">units</span>
              </span>
            </div>
          </div>

          {/* Logistics & Location Details */}
          <div className="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 space-y-4">
            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
              <MapPin size={14} className="text-emerald-400" />
              <span>Location & Pickup Information</span>
            </h4>

            <div className="space-y-2 text-xs text-slate-300">
              <div>
                <span className="text-slate-500 block text-[11px]">Address Landmark:</span>
                <span className="font-semibold text-white">{donation.address_text || 'Address not specified'}</span>
              </div>

              {donation.pickup_notes && (
                <div>
                  <span className="text-slate-500 block text-[11px]">Special Instructions:</span>
                  <span className="italic text-slate-300">{donation.pickup_notes}</span>
                </div>
              )}

              {donation.latitude != null && donation.longitude != null && (
                <div className="text-[11px] text-slate-500 pt-1">
                  GPS Coordinates: {Number(donation.latitude).toFixed(6)}, {Number(donation.longitude).toFixed(6)}
                </div>
              )}
            </div>
          </div>

          {/* Status History Timeline */}
          <div className="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 space-y-4">
            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
              <Clock size={14} className="text-teal-400" />
              <span>Listing & Handover Audit Trail</span>
            </h4>

            {history.length === 0 ? (
              <p className="text-xs text-slate-500 italic">No historical audit events logged yet.</p>
            ) : (
              <div className="space-y-4 relative before:absolute before:left-3.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-800">
                {history.map((event, idx) => (
                  <div key={idx} className="flex items-start gap-3.5 relative">
                    <div className="w-7 h-7 rounded-full bg-slate-950 border border-emerald-500/50 flex items-center justify-center text-emerald-400 shrink-0 z-10">
                      <CheckCircle2 size={14} />
                    </div>
                    <div className="bg-slate-950/60 border border-slate-800/80 rounded-xl p-3 flex-1 text-xs space-y-1">
                      <div className="flex items-center justify-between gap-2">
                        <strong className="text-white font-semibold capitalize">
                          {event.action ? event.action.replace(/[._]/g, ' ') : event.event_name || 'Status Updated'}
                        </strong>
                        <span className="text-[10px] text-slate-500">
                          {new Date(event.created_at || Date.now()).toLocaleString()}
                        </span>
                      </div>
                      {event.details && (
                        <p className="text-slate-400 text-[11px]">{event.details}</p>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Close Donation Confirmation Dialog */}
      <ConfirmDialog
        isOpen={showCloseDialog}
        title="Close Donation Listing?"
        message={`Are you sure you want to close this listing? Closed listings cannot be matched to incoming NGO requirements. Any active claim handovers must be resolved first.`}
        confirmLabel={closing ? 'Closing...' : 'Yes, Close Listing'}
        cancelLabel="Keep Listing"
        variant="danger"
        onConfirm={handleCloseConfirm}
        onCancel={() => setShowCloseDialog(false)}
      />
    </div>
  );
}
