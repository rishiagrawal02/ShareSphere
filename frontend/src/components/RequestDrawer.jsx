import React, { useState, useEffect, useRef, useCallback } from 'react';
import { api, ApiError } from '../api/client';
import { useToast } from './Toast';
import { X, ShoppingCart, AlertCircle, Info, RefreshCw } from 'lucide-react';

/**
 * RequestDrawer – slide-in panel for an NGO to request a quantity from a matched donation.
 *
 * Props:
 *   isOpen         boolean
 *   donation       { id, title, available_quantity, condition, ... }
 *   requirementId  number
 *   outstanding    number – max the NGO still needs
 *   onClose        () => void
 *   onSuccess      (updatedDonation) => void  – called after a successful request
 */
export function RequestDrawer({ isOpen, donation, requirementId, outstanding, onClose, onSuccess }) {
  const { showSuccess, showError } = useToast();

  const [quantity, setQuantity] = useState(1);
  const [submitting, setSubmitting] = useState(false);
  const [fieldError, setFieldError] = useState(null);
  const [conflictMsg, setConflictMsg] = useState(null);
  const [localAvailable, setLocalAvailable] = useState(null);

  // Generate a stable idempotency key per drawer-open event
  const idempotencyKey = useRef(null);

  useEffect(() => {
    if (isOpen && donation) {
      const available = donation.available_quantity ?? donation.total_quantity ?? 0;
      const max = Math.min(available, outstanding ?? available);
      setQuantity(Math.max(1, Math.min(max, 1)));
      setLocalAvailable(available);
      setFieldError(null);
      setConflictMsg(null);
      // Fresh key per drawer open – guarantees idempotency for this specific intent
      idempotencyKey.current = crypto.randomUUID();
    }
  }, [isOpen, donation, outstanding]);

  // Close on Escape
  useEffect(() => {
    if (!isOpen) return;
    const handler = (e) => { if (e.key === 'Escape') onClose(); };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [isOpen, onClose]);

  const available = localAvailable ?? (donation?.available_quantity ?? donation?.total_quantity ?? 0);
  const maxAllowed = Math.min(available, outstanding ?? available);

  const handleQuantityChange = (val) => {
    const n = parseInt(val, 10);
    setQuantity(isNaN(n) ? '' : n);
    setFieldError(null);
    setConflictMsg(null);
  };

  const validate = () => {
    const n = Number(quantity);
    if (!n || n < 1) return 'Quantity must be at least 1.';
    if (n > maxAllowed) return `Maximum requestable is ${maxAllowed} (limited by available stock and outstanding need).`;
    return null;
  };

  const handleSubmit = useCallback(async (e) => {
    e.preventDefault();
    const err = validate();
    if (err) { setFieldError(err); return; }
    if (submitting) return;

    setSubmitting(true);
    setConflictMsg(null);
    setFieldError(null);

    try {
      await api.post(
        '/api/requests',
        {
          donation_id: donation.id,
          requirement_id: requirementId,
          requested_quantity: Number(quantity),
        },
        { headers: { 'Idempotency-Key': idempotencyKey.current } }
      );

      showSuccess(`Request for ${quantity} units submitted! The donor will review it shortly.`);
      onSuccess?.({ ...donation, available_quantity: available - Number(quantity) });
      onClose();
    } catch (err) {
      if (err instanceof ApiError) {
        if (err.code === 'INSUFFICIENT_QUANTITY') {
          const remaining = err?.data?.available ?? null;
          const msg = remaining != null
            ? `Someone else reserved part of this stock — only ${remaining} left. The form has been updated.`
            : 'Insufficient stock. Please reduce your quantity and try again.';
          setConflictMsg(msg);
          if (remaining != null) {
            setLocalAvailable(remaining);
            setQuantity(Math.max(1, Math.min(Number(quantity), remaining, outstanding ?? remaining)));
          }
        } else {
          showError(err.message || 'Failed to submit request.');
        }
      } else {
        showError('An unexpected error occurred. Please try again.');
      }
    } finally {
      setSubmitting(false);
    }
  }, [donation, requirementId, quantity, available, outstanding, submitting, onSuccess, onClose, showSuccess, showError]);

  if (!isOpen || !donation) return null;

  return (
    <>
      {/* Backdrop */}
      <div
        className="fixed inset-0 z-40 bg-slate-950/70 backdrop-blur-sm"
        onClick={onClose}
        aria-hidden="true"
      />

      {/* Drawer */}
      <aside
        role="dialog"
        aria-modal="true"
        aria-label="Request quantity drawer"
        className="fixed right-0 top-0 bottom-0 z-50 w-full sm:w-[420px] bg-slate-900 border-l border-slate-800 flex flex-col shadow-2xl"
      >
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-5 border-b border-slate-800">
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
              <ShoppingCart size={18} />
            </div>
            <div>
              <h2 className="text-sm font-bold text-white">Request Units</h2>
              <p className="text-[11px] text-slate-400 truncate max-w-[220px]">{donation.title}</p>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close request drawer"
            className="w-8 h-8 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition-colors"
          >
            <X size={16} />
          </button>
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto px-6 py-6 space-y-6">
          {/* Stock Summary */}
          <div className="grid grid-cols-3 gap-3">
            <div className="bg-slate-800 rounded-xl p-3 text-center">
              <p className="text-lg font-bold text-white">{available}</p>
              <p className="text-[10px] text-slate-400 mt-0.5">Available</p>
            </div>
            <div className="bg-slate-800 rounded-xl p-3 text-center">
              <p className="text-lg font-bold text-amber-400">{outstanding ?? '—'}</p>
              <p className="text-[10px] text-slate-400 mt-0.5">Outstanding need</p>
            </div>
            <div className="bg-slate-800 rounded-xl p-3 text-center">
              <p className="text-lg font-bold text-emerald-400">{maxAllowed}</p>
              <p className="text-[10px] text-slate-400 mt-0.5">Max requestable</p>
            </div>
          </div>

          {/* 409 Conflict Banner */}
          {conflictMsg && (
            <div className="flex items-start gap-3 bg-amber-500/10 border border-amber-500/30 rounded-xl p-4">
              <RefreshCw size={16} className="text-amber-400 shrink-0 mt-0.5" />
              <p className="text-xs text-amber-300 leading-relaxed">{conflictMsg}</p>
            </div>
          )}

          {/* Quantity Input */}
          <form id="request-drawer-form" onSubmit={handleSubmit} noValidate>
            <label htmlFor="request-qty" className="block text-xs font-semibold text-slate-300 mb-2">
              Quantity to Request <span className="text-red-400">*</span>
            </label>
            <input
              id="request-qty"
              type="number"
              inputMode="numeric"
              min={1}
              max={maxAllowed}
              value={quantity}
              onChange={(e) => handleQuantityChange(e.target.value)}
              disabled={submitting}
              className={`w-full bg-slate-800 border rounded-xl px-4 py-3 text-lg font-bold text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:opacity-50 ${
                fieldError ? 'border-red-500' : 'border-slate-700'
              }`}
            />
            {fieldError && (
              <p className="mt-1.5 flex items-center gap-1.5 text-xs text-red-400">
                <AlertCircle size={12} /> {fieldError}
              </p>
            )}

            {/* Remaining need hint */}
            {outstanding != null && (
              <p className="mt-2 flex items-start gap-1.5 text-xs text-slate-400">
                <Info size={12} className="mt-0.5 shrink-0" />
                Requesting{' '}
                <strong className="text-white mx-1">{Number(quantity) || 0}</strong> will leave{' '}
                <strong className="text-amber-400 mx-1">
                  {Math.max(0, outstanding - (Number(quantity) || 0))}
                </strong>{' '}
                units still needed.
              </p>
            )}

            <div className="mt-2 flex items-start gap-1.5 text-[11px] text-slate-500">
              <Info size={11} className="mt-0.5 shrink-0" />
              Double-clicking Submit will only send one request — duplicate submissions are automatically
              prevented.
            </div>
          </form>
        </div>

        {/* Footer */}
        <div className="px-6 py-5 border-t border-slate-800 flex items-center gap-3">
          <button
            type="button"
            onClick={onClose}
            disabled={submitting}
            className="flex-1 px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-sm font-medium border border-slate-700 transition-all disabled:opacity-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            form="request-drawer-form"
            id="request-drawer-submit"
            disabled={submitting || maxAllowed < 1}
            className="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-sm font-semibold shadow-lg shadow-emerald-950/40 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {submitting ? (
              <>
                <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                Sending…
              </>
            ) : (
              <>
                <ShoppingCart size={15} />
                Send Request
              </>
            )}
          </button>
        </div>
      </aside>
    </>
  );
}
