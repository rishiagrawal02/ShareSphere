import React, { useState } from 'react';
import { api, ApiError } from '../../api/client';
import { PageHeader } from '../../components/PageHeader';
import { StatusBadge } from '../../components/StatusBadge';
import { useToast } from '../../components/Toast';
import { formatDate } from '../../utils/date';
import {
  ShieldAlert,
  Search,
  Package,
  Trash2,
  RotateCcw,
  AlertCircle,
  CheckCircle2,
} from 'lucide-react';

export function DonationModeration() {
  const { showSuccess, showError } = useToast();
  const [donationIdInput, setDonationIdInput] = useState('');
  const [donation, setDonation] = useState(null);
  const [loading, setLoading] = useState(false);
  const [searchError, setSearchError] = useState('');

  // Moderation action
  const [action, setAction] = useState('remove'); // 'remove' | 'restore'
  const [reason, setReason] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [modError, setModError] = useState('');

  const handleLookup = async (e) => {
    e.preventDefault();
    const id = donationIdInput.trim();
    if (!id) return;

    setLoading(true);
    setSearchError('');
    setDonation(null);

    try {
      const res = await api.get(`/api/donations/${id}`);
      setDonation(res?.donation || res?.data);
    } catch (err) {
      setSearchError(err.message || 'Donation not found.');
    } finally {
      setLoading(false);
    }
  };

  const handleModerate = async (e) => {
    e.preventDefault();
    if (!reason.trim()) {
      setModError('A moderation reason is mandatory.');
      return;
    }

    setSubmitting(true);
    setModError('');

    try {
      await api.post(`/api/admin/donations/${donation.id}/moderate`, {
        action,
        reason: reason.trim(),
      });
      showSuccess(`Donation #${donation.id} successfully ${action === 'remove' ? 'removed from active circulation' : 'restored'}.`);
      setDonation((prev) => ({
        ...prev,
        status: action === 'remove' ? 'cancelled' : 'active',
      }));
      setReason('');
    } catch (err) {
      setModError(err.message || 'Failed to moderate donation.');
      showError(err.message || 'Moderation action failed.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="max-w-3xl mx-auto space-y-6">
      <PageHeader
        title="Listing Moderation"
        subtitle="Lookup community donations by ID to remove inappropriate listings or restore suspended items."
      />

      {/* Lookup Form */}
      <form onSubmit={handleLookup} className="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl flex items-center gap-3">
        <div className="relative flex-1">
          <Search size={16} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500" />
          <input
            type="number"
            id="lookup-donation-id"
            placeholder="Enter Donation ID (e.g. 5)..."
            value={donationIdInput}
            onChange={(e) => setDonationIdInput(e.target.value)}
            className="w-full bg-slate-950 border border-slate-800 rounded-xl pl-10 pr-4 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
          />
        </div>
        <button
          type="submit"
          disabled={loading || !donationIdInput.trim()}
          className="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-xl text-xs font-semibold shadow-md transition-all"
        >
          {loading ? 'Searching…' : 'Inspect Listing'}
        </button>
      </form>

      {searchError && (
        <div className="p-4 rounded-xl bg-red-500/10 border border-red-500/30 text-red-300 text-xs flex items-center gap-2">
          <AlertCircle size={15} />
          <span>{searchError}</span>
        </div>
      )}

      {/* Donation Found Details & Action Card */}
      {donation && (
        <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-6 shadow-xl">
          <div className="flex items-start justify-between gap-4 pb-4 border-b border-slate-800">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-slate-800 text-slate-300 flex items-center justify-center">
                <Package size={20} />
              </div>
              <div>
                <h3 className="text-base font-bold text-white">{donation.title}</h3>
                <p className="text-xs text-slate-400">
                  ID: #{donation.id} · Total: {donation.total_quantity} units · Available: {donation.available_quantity ?? donation.total_quantity}
                </p>
              </div>
            </div>
            <StatusBadge status={donation.status} />
          </div>

          <div className="grid grid-cols-2 gap-4 text-xs">
            <div>
              <span className="text-slate-500 block">Category</span>
              <span className="font-semibold text-white">{donation.category_name || 'General'}</span>
            </div>
            <div>
              <span className="text-slate-500 block">Listed Date</span>
              <span className="font-semibold text-white">{formatDate(donation.created_at)}</span>
            </div>
          </div>

          {donation.description && (
            <p className="text-xs text-slate-300 leading-relaxed bg-slate-950 p-3.5 rounded-xl border border-slate-800">
              {donation.description}
            </p>
          )}

          {/* Moderation Controls */}
          <form onSubmit={handleModerate} className="pt-4 border-t border-slate-800 space-y-4">
            <div className="flex items-center gap-3">
              <label
                className={`flex-1 p-3 rounded-xl border cursor-pointer text-xs flex items-center gap-2 transition-all ${
                  action === 'remove'
                    ? 'bg-red-500/15 border-red-500/50 text-white font-semibold'
                    : 'bg-slate-950 border-slate-800 text-slate-400'
                }`}
              >
                <input
                  type="radio"
                  name="action"
                  value="remove"
                  checked={action === 'remove'}
                  onChange={() => setAction('remove')}
                />
                <Trash2 size={14} className="text-red-400" />
                <span>Remove from Circulation</span>
              </label>

              <label
                className={`flex-1 p-3 rounded-xl border cursor-pointer text-xs flex items-center gap-2 transition-all ${
                  action === 'restore'
                    ? 'bg-emerald-500/15 border-emerald-500/50 text-white font-semibold'
                    : 'bg-slate-950 border-slate-800 text-slate-400'
                }`}
              >
                <input
                  type="radio"
                  name="action"
                  value="restore"
                  checked={action === 'restore'}
                  onChange={() => setAction('restore')}
                />
                <RotateCcw size={14} className="text-emerald-400" />
                <span>Restore Listing</span>
              </label>
            </div>

            <div>
              <label htmlFor="mod-reason" className="block text-xs font-semibold text-slate-300 mb-1">
                Moderation Reason (Required)
              </label>
              <textarea
                id="mod-reason"
                rows={3}
                required
                value={reason}
                onChange={(e) => {
                  setReason(e.target.value);
                  setModError('');
                }}
                placeholder="Explain the policy violation or reason for this moderation action..."
                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-red-500"
              />
            </div>

            {modError && (
              <p className="text-xs text-red-400 font-medium" role="alert">
                {modError}
              </p>
            )}

            <button
              type="submit"
              id="submit-moderation-btn"
              disabled={submitting}
              className={`w-full py-2.5 rounded-xl text-xs font-semibold text-white shadow-lg transition-all flex items-center justify-center gap-1.5 ${
                action === 'remove'
                  ? 'bg-red-600 hover:bg-red-500 shadow-red-950/40 disabled:opacity-50'
                  : 'bg-emerald-600 hover:bg-emerald-500 shadow-emerald-950/40 disabled:opacity-50'
              }`}
            >
              <ShieldAlert size={14} />
              <span>{submitting ? 'Processing…' : action === 'remove' ? 'Confirm Removal' : 'Confirm Restoration'}</span>
            </button>
          </form>
        </div>
      )}
    </div>
  );
}
