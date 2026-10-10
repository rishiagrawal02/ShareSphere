import React, { useState, useEffect, useCallback } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { api, ApiError } from '../../api/client';
import { useAuth } from '../../auth/AuthContext';
import { PageHeader } from '../../components/PageHeader';
import { StatusBadge } from '../../components/StatusBadge';
import { PickupStepper } from '../../components/PickupStepper';
import { OtpInput } from '../../components/OtpInput';
import { LoadingState } from '../../components/LoadingState';
import { ErrorState } from '../../components/ErrorState';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { useToast } from '../../components/Toast';
import { formatDateTime } from '../../utils/date';
import {
  Calendar,
  Clock,
  MapPin,
  ShieldCheck,
  Send,
  CheckCircle2,
  AlertTriangle,
  RotateCcw,
  XCircle,
  ArrowLeft,
  KeyRound,
  Package,
  Phone,
  Info,
} from 'lucide-react';

export function PickupDetail() {
  const { id } = useParams();
  const { user } = useAuth();
  const navigate = useNavigate();
  const { showSuccess, showError } = useToast();

  const [pickup, setPickup] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [actionLoading, setActionLoading] = useState(false);

  // OTP Verification state (Donor)
  const [otpCode, setOtpCode] = useState('');
  const [otpError, setOtpError] = useState(null);
  const [attemptsRemaining, setAttemptsRemaining] = useState(null);

  // Reschedule Modal
  const [showReschedule, setShowReschedule] = useState(false);
  const [newDate, setNewDate] = useState('');
  const [newTime, setNewTime] = useState('');
  const [rescheduleNotes, setRescheduleNotes] = useState('');
  const [rescheduleError, setRescheduleError] = useState('');

  // Cancel Dialog
  const [showCancel, setShowCancel] = useState(false);
  const [cancelReason, setCancelReason] = useState('');

  const loadPickup = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.get(`/api/pickups/${id}`);
      const data = res?.data;
      setPickup(data);

      if (data) {
        // Calculate attempts remaining if otp_attempts > 0
        if (data.otp_attempts > 0 && data.otp_attempts < 5) {
          setAttemptsRemaining(5 - data.otp_attempts);
        }
      }
    } catch (err) {
      setError(
        err instanceof ApiError
          ? err
          : new ApiError(500, 'LOAD_FAILED', 'Could not load pickup details.')
      );
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadPickup();
  }, [loadPickup]);

  const isDonor = user?.role === 'donor';
  const isNgo = user?.role === 'ngo';
  const isProposedByMe = pickup?.proposed_by === user?.id;

  // 24-hour window check for issuing OTP
  const isWithin24Hours = () => {
    if (!pickup?.scheduled_at) return false;
    const schedTs = new Date(pickup.scheduled_at).getTime();
    const nowTs = Date.now();
    const diffHours = (schedTs - nowTs) / (1000 * 3600);
    return diffHours <= 24;
  };

  // 1. Confirm Pickup (Counterparty action when state === 'proposed')
  const handleConfirm = async () => {
    setActionLoading(true);
    try {
      const res = await api.post(`/api/pickups/${id}/confirm`);
      setPickup(res?.data);
      showSuccess('Pickup schedule confirmed! Exact address is now revealed.');
    } catch (err) {
      showError(err.message || 'Could not confirm pickup.');
    } finally {
      setActionLoading(false);
    }
  };

  // 2. Reschedule Pickup (PATCH /api/pickups/:id)
  const handleReschedule = async (e) => {
    e.preventDefault();
    if (!newDate || !newTime) {
      setRescheduleError('Please choose a valid date and time.');
      return;
    }

    const scheduledDate = new Date(`${newDate}T${newTime}`);
    const now = new Date();
    const diffMs = scheduledDate.getTime() - now.getTime();

    if (diffMs < 3600 * 1000) {
      setRescheduleError('Scheduled time must be at least 1 hour in the future.');
      return;
    }

    setActionLoading(true);
    setRescheduleError('');
    try {
      const res = await api.patch(`/api/pickups/${id}`, {
        scheduled_at: scheduledDate.toISOString(),
        location_details: rescheduleNotes || undefined,
      });
      setPickup(res?.data);
      setShowReschedule(false);
      showSuccess('Pickup rescheduled. Reset to proposed state for confirmation.');
    } catch (err) {
      setRescheduleError(err.message || 'Failed to reschedule pickup.');
    } finally {
      setActionLoading(false);
    }
  };

  // 3. Cancel Pickup (POST /api/pickups/:id/cancel)
  const handleCancel = async () => {
    setActionLoading(true);
    try {
      const res = await api.post(`/api/pickups/${id}/cancel`, {
        reason: cancelReason || undefined,
      });
      setPickup(res?.data);
      setShowCancel(false);
      showSuccess('Pickup cancelled.');
    } catch (err) {
      showError(err.message || 'Could not cancel pickup.');
    } finally {
      setActionLoading(false);
    }
  };

  // 4. Issue OTP (NGO action) - Never display OTP in UI!
  const handleIssueOtp = async () => {
    setActionLoading(true);
    try {
      await api.post(`/api/pickups/${id}/otp`);
      showSuccess('Pickup verification code emailed to your organisation email address.');
      // Refresh pickup state to show otp_issued
      await loadPickup();
    } catch (err) {
      showError(err.message || 'Could not send pickup code.');
    } finally {
      setActionLoading(false);
    }
  };

  // 5. Verify OTP (Donor action)
  const handleVerifyOtp = async (codeToVerify) => {
    const code = codeToVerify || otpCode;
    if (!code || code.length !== 6) {
      setOtpError('Please enter all 6 digits.');
      return;
    }

    setActionLoading(true);
    setOtpError(null);

    try {
      const res = await api.post(`/api/pickups/${id}/verify-otp`, { otp: code });
      showSuccess('Handover verified! Items successfully collected.');
      setPickup((prev) => ({
        ...prev,
        state: 'collected',
        collected_at: new Date().toISOString(),
      }));
      setOtpCode('');
    } catch (err) {
      const msg = err.message || 'Invalid pickup code';
      setOtpError(msg);

      if (err.status === 423 || msg.toLowerCase().includes('locked')) {
        setPickup((prev) => ({ ...prev, otp_locked: true }));
      } else if (msg.toLowerCase().includes('expired')) {
        // Expired
      } else {
        // Decrement attempts
        setAttemptsRemaining((prev) => (prev !== null ? Math.max(0, prev - 1) : 4));
      }
    } finally {
      setActionLoading(false);
    }
  };

  // 6. Confirm Receipt (NGO action when state === 'collected')
  const handleConfirmReceipt = async () => {
    setActionLoading(true);
    try {
      const res = await api.post(`/api/pickups/${id}/confirm-receipt`);
      setPickup((prev) => ({
        ...prev,
        state: 'completed',
        completed_at: new Date().toISOString(),
      }));
      showSuccess('Receipt confirmed! Handover fully completed.');
    } catch (err) {
      showError(err.message || 'Could not confirm receipt.');
    } finally {
      setActionLoading(false);
    }
  };

  if (loading) return <LoadingState message="Loading pickup details…" />;
  if (error) return <ErrorState error={error} onRetry={loadPickup} />;
  if (!pickup) return <ErrorState message="Pickup not found." onRetry={loadPickup} />;

  const isExactLocationRevealed =
    pickup.state === 'scheduled' ||
    pickup.state === 'otp_issued' ||
    pickup.state === 'collected' ||
    pickup.state === 'completed';

  const isOtpExpired =
    pickup.otp_expires_at && new Date(pickup.otp_expires_at).getTime() < Date.now();

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      {/* Top Header */}
      <PageHeader
        title={`Pickup #${pickup.id}`}
        subtitle={`Handover coordination for "${pickup.donation?.title || 'Donation Items'}"`}
        action={
          <div className="flex items-center gap-2">
            <Link
              to={isNgo ? '/ngo/pickups' : '/donor/pickups'}
              className="flex items-center gap-1.5 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium border border-slate-700 transition-all"
            >
              <ArrowLeft size={14} />
              All Pickups
            </Link>
          </div>
        }
      />

      {/* Visual State Stepper */}
      <PickupStepper state={pickup.state} pickup={pickup} />

      {/* Main Grid: Details + Actions */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {/* Left 2 Cols: Details & Privacy Location */}
        <div className="md:col-span-2 space-y-6">
          {/* Handover Summary Card */}
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-5 shadow-xl">
            <div className="flex items-center justify-between pb-4 border-b border-slate-800">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center">
                  <Package size={20} />
                </div>
                <div>
                  <h3 className="text-base font-bold text-white">{pickup.donation?.title}</h3>
                  <p className="text-xs text-slate-400">
                    Allocated Quantity:{' '}
                    <strong className="text-emerald-400">
                      {pickup.allocation?.allocated_quantity} units
                    </strong>
                  </p>
                </div>
              </div>
              <StatusBadge status={pickup.state} />
            </div>

            {/* Scheduled Date/Time Banner */}
            <div className="flex items-center gap-3 p-4 rounded-xl bg-slate-950 border border-slate-800">
              <Calendar size={18} className="text-emerald-400 shrink-0" />
              <div>
                <p className="text-xs text-slate-400">Scheduled Handover (IST)</p>
                <p className="text-sm font-semibold text-white">
                  {formatDateTime(pickup.scheduled_at)}
                </p>
              </div>
            </div>

            {/* Location Privacy & Address Section */}
            <div className="space-y-3">
              <h4 className="text-xs font-semibold text-slate-400 uppercase tracking-wider flex items-center gap-2">
                <MapPin size={14} className="text-emerald-400" />
                <span>Handover Location</span>
              </h4>

              {isExactLocationRevealed ? (
                <div className="p-4 rounded-xl bg-emerald-950/20 border border-emerald-500/30 space-y-2">
                  <div className="flex items-center gap-2 text-xs font-semibold text-emerald-400">
                    <ShieldCheck size={15} />
                    <span>Exact Pickup Address Revealed</span>
                  </div>
                  <p className="text-sm text-white font-medium">
                    {pickup.donation?.address_text || 'Address details confirmed.'}
                  </p>
                  {pickup.donation?.latitude_exact && pickup.donation?.longitude_exact && (
                    <p className="text-xs text-slate-400">
                      GPS: {pickup.donation.latitude_exact.toFixed(4)},{' '}
                      {pickup.donation.longitude_exact.toFixed(4)}
                    </p>
                  )}
                </div>
              ) : (
                <div className="p-4 rounded-xl bg-slate-950 border border-slate-800/80 space-y-2">
                  <div className="flex items-center gap-2 text-xs font-medium text-amber-400">
                    <Info size={14} />
                    <span>Exact Address Protected</span>
                  </div>
                  <p className="text-xs text-slate-400 leading-relaxed">
                    Donor address and exact coordinates are privacy-snapped and will only be
                    revealed once both parties confirm the pickup schedule.
                  </p>
                </div>
              )}

              {/* Handover Instructions / Contact Note */}
              {pickup.location_details && (
                <div className="p-3.5 rounded-xl bg-slate-950 border border-slate-800/80 text-xs text-slate-300">
                  <span className="font-semibold text-white block mb-0.5">
                    Handover Instructions:
                  </span>
                  {pickup.location_details}
                </div>
              )}

              {pickup.contact_note && (
                <div className="p-3.5 rounded-xl bg-slate-950 border border-slate-800/80 text-xs text-slate-300 flex items-center gap-2">
                  <Phone size={13} className="text-emerald-400 shrink-0" />
                  <span>
                    <strong className="text-white">Representative Contact:</strong>{' '}
                    {pickup.contact_note}
                  </span>
                </div>
              )}
            </div>

            {/* Participants */}
            <div className="grid grid-cols-2 gap-4 pt-4 border-t border-slate-800 text-xs">
              <div>
                <span className="text-slate-500 block">Assigned NGO</span>
                <span className="font-semibold text-white">
                  {pickup.ngo?.organization_name || 'Verified NGO'}
                </span>
              </div>
              <div>
                <span className="text-slate-500 block">Proposed By</span>
                <span className="font-semibold text-white">
                  {isProposedByMe ? 'You' : 'Counterparty'}
                </span>
              </div>
            </div>
          </div>
        </div>

        {/* Right Col: Handover Actions & OTP Protocol */}
        <div className="space-y-6">
          {/* Action Box */}
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4 shadow-xl">
            <h3 className="text-sm font-bold text-white flex items-center gap-2">
              <KeyRound size={16} className="text-emerald-400" />
              <span>Handover Protocol</span>
            </h3>

            {/* Case 1: Proposed state */}
            {pickup.state === 'proposed' && (
              <div className="space-y-4">
                {isProposedByMe ? (
                  <div className="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-300 space-y-2">
                    <p className="font-semibold">Awaiting Confirmation</p>
                    <p className="text-amber-300/80">
                      You proposed this pickup time. Waiting for the other party to confirm or
                      propose an alternate time.
                    </p>
                  </div>
                ) : (
                  <div className="space-y-3">
                    <div className="p-3.5 rounded-xl bg-blue-500/10 border border-blue-500/20 text-xs text-blue-300">
                      The other party proposed this pickup time. Confirm if this works, or propose
                      an alternate time.
                    </div>
                    <button
                      type="button"
                      id="confirm-pickup-btn"
                      disabled={actionLoading}
                      onClick={handleConfirm}
                      className="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-xl text-xs font-semibold shadow-lg shadow-emerald-950/40 transition-all flex items-center justify-center gap-1.5"
                    >
                      <CheckCircle2 size={15} />
                      <span>Confirm Pickup Time</span>
                    </button>
                  </div>
                )}

                <div className="flex items-center gap-2 pt-2 border-t border-slate-800">
                  <button
                    type="button"
                    onClick={() => {
                      const d = new Date(pickup.scheduled_at || Date.now());
                      const pad = (n) => String(n).padStart(2, '0');
                      setNewDate(`${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`);
                      setNewTime('10:00');
                      setShowReschedule(true);
                    }}
                    className="flex-1 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium border border-slate-700 transition-all flex items-center justify-center gap-1"
                  >
                    <RotateCcw size={13} />
                    <span>Reschedule</span>
                  </button>
                  <button
                    type="button"
                    onClick={() => setShowCancel(true)}
                    className="py-2 px-3 bg-red-500/10 hover:bg-red-500/20 text-red-400 rounded-xl text-xs font-medium border border-red-500/20 transition-all flex items-center justify-center gap-1"
                  >
                    <XCircle size={13} />
                    <span>Cancel</span>
                  </button>
                </div>
              </div>
            )}

            {/* Case 2: Scheduled state - NGO can request code within 24h */}
            {pickup.state === 'scheduled' && (
              <div className="space-y-4">
                <div className="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-300">
                  <p className="font-semibold">Schedule Confirmed</p>
                  <p className="text-emerald-300/80 mt-0.5">
                    Prepare for the handover at the scheduled time. Exact address has been revealed.
                  </p>
                </div>

                {isNgo && (
                  <div className="space-y-3 pt-2 border-t border-slate-800">
                    <p className="text-xs text-slate-300">
                      When your representative is ready to collect the items, request the pickup
                      verification code:
                    </p>
                    <button
                      type="button"
                      id="send-pickup-code-btn"
                      disabled={actionLoading || !isWithin24Hours()}
                      onClick={handleIssueOtp}
                      className="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-xl text-xs font-semibold shadow-lg shadow-emerald-950/40 transition-all flex items-center justify-center gap-1.5"
                    >
                      <Send size={14} />
                      <span>Send Pickup Code</span>
                    </button>
                    {!isWithin24Hours() && (
                      <p className="text-[11px] text-amber-400">
                        Pickup code can only be generated within 24 hours of scheduled pickup time.
                      </p>
                    )}
                    {pickup.otp_issue_count > 0 && (
                      <p className="text-[11px] text-slate-500">
                        Reissued {pickup.otp_issue_count} of 3 times in 24h.
                      </p>
                    )}
                  </div>
                )}

                {isDonor && (
                  <div className="p-3.5 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-400">
                    The NGO representative will present a 6-digit code upon physical arrival. You
                    will verify it here.
                  </div>
                )}

                <div className="flex items-center gap-2 pt-2 border-t border-slate-800">
                  <button
                    type="button"
                    onClick={() => {
                      const d = new Date(pickup.scheduled_at || Date.now());
                      const pad = (n) => String(n).padStart(2, '0');
                      setNewDate(`${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`);
                      setNewTime('10:00');
                      setShowReschedule(true);
                    }}
                    className="flex-1 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium border border-slate-700 transition-all flex items-center justify-center gap-1"
                  >
                    <RotateCcw size={13} />
                    <span>Reschedule</span>
                  </button>
                  <button
                    type="button"
                    onClick={() => setShowCancel(true)}
                    className="py-2 px-3 bg-red-500/10 hover:bg-red-500/20 text-red-400 rounded-xl text-xs font-medium border border-red-500/20 transition-all flex items-center justify-center gap-1"
                  >
                    <XCircle size={13} />
                    <span>Cancel</span>
                  </button>
                </div>
              </div>
            )}

            {/* Case 3: OTP Issued state */}
            {pickup.state === 'otp_issued' && (
              <div className="space-y-4">
                {isNgo && (
                  <div className="space-y-3">
                    <div className="p-4 rounded-xl bg-purple-500/10 border border-purple-500/20 text-xs text-purple-300 space-y-1.5">
                      <p className="font-semibold flex items-center gap-1.5">
                        <KeyRound size={14} />
                        <span>Code Sent to Your Email</span>
                      </p>
                      <p className="text-purple-300/80 leading-relaxed">
                        The 6-digit verification code was emailed to your organisation email. Share
                        it verbally with the donor during handover.
                      </p>
                    </div>

                    <p className="text-[11px] text-slate-400">
                      Need a new code? (e.g. if expired or locked)
                    </p>

                    <button
                      type="button"
                      id="reissue-otp-btn"
                      disabled={actionLoading || pickup.otp_issue_count >= 3}
                      onClick={handleIssueOtp}
                      className="w-full py-2 bg-slate-800 hover:bg-slate-700 disabled:opacity-40 text-slate-300 rounded-xl text-xs font-medium border border-slate-700 transition-all flex items-center justify-center gap-1.5"
                    >
                      <RotateCcw size={13} />
                      <span>
                        Reissue Code ({3 - (pickup.otp_issue_count || 0)} reissues left)
                      </span>
                    </button>
                  </div>
                )}

                {isDonor && (
                  <div className="space-y-4">
                    <div className="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-300">
                      Enter the 6-digit code shown by the NGO pickup representative:
                    </div>

                    {/* 6 Digit Box Component */}
                    <OtpInput
                      value={otpCode}
                      onChange={setOtpCode}
                      disabled={actionLoading || pickup.otp_locked || isOtpExpired}
                      error={otpError}
                      attemptsRemaining={attemptsRemaining}
                      isLocked={pickup.otp_locked}
                      isExpired={isOtpExpired}
                    />

                    <button
                      type="button"
                      id="verify-otp-btn"
                      disabled={
                        actionLoading ||
                        otpCode.length !== 6 ||
                        pickup.otp_locked ||
                        isOtpExpired
                      }
                      onClick={() => handleVerifyOtp(otpCode)}
                      className="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-xl text-xs font-semibold shadow-lg shadow-emerald-950/40 transition-all flex items-center justify-center gap-1.5"
                    >
                      <CheckCircle2 size={15} />
                      <span>{actionLoading ? 'Verifying…' : 'Verify Handover Code'}</span>
                    </button>
                  </div>
                )}

                <div className="pt-2 border-t border-slate-800">
                  <button
                    type="button"
                    onClick={() => {
                      const d = new Date(pickup.scheduled_at || Date.now());
                      const pad = (n) => String(n).padStart(2, '0');
                      setNewDate(`${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`);
                      setNewTime('10:00');
                      setShowReschedule(true);
                    }}
                    className="w-full py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium border border-slate-700 transition-all flex items-center justify-center gap-1"
                  >
                    <RotateCcw size={13} />
                    <span>Reschedule (Invalidates Current Code)</span>
                  </button>
                </div>
              </div>
            )}

            {/* Case 4: Collected state */}
            {pickup.state === 'collected' && (
              <div className="space-y-4">
                <div className="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-300 space-y-1">
                  <p className="font-semibold flex items-center gap-1.5">
                    <CheckCircle2 size={15} />
                    <span>Items Handed Over & Collected</span>
                  </p>
                  <p className="text-emerald-300/80">
                    Handover code was verified. Items have been collected by the NGO.
                  </p>
                </div>

                {isNgo && (
                  <div className="space-y-3">
                    <p className="text-xs text-slate-300">
                      Inspect the items and confirm final receipt to complete the donation lifecycle:
                    </p>
                    <button
                      type="button"
                      id="confirm-receipt-btn"
                      disabled={actionLoading}
                      onClick={handleConfirmReceipt}
                      className="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-xl text-xs font-semibold shadow-lg shadow-emerald-950/40 transition-all flex items-center justify-center gap-1.5"
                    >
                      <CheckCircle2 size={15} />
                      <span>{actionLoading ? 'Confirming…' : 'Confirm Receipt & Complete'}</span>
                    </button>
                  </div>
                )}

                {isDonor && (
                  <p className="text-xs text-slate-400">
                    Awaiting NGO to complete final inventory check-in.
                  </p>
                )}
              </div>
            )}

            {/* Case 5: Completed state */}
            {pickup.state === 'completed' && (
              <div className="p-4 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-xs text-emerald-300 space-y-2 text-center">
                <CheckCircle2 size={28} className="mx-auto text-emerald-400" />
                <p className="font-bold text-sm text-white">Handover Completed</p>
                <p className="text-emerald-300/80">
                  This donation was successfully transferred to the community. Thank you for your
                  contribution!
                </p>
              </div>
            )}

            {/* Case 6: Cancelled state */}
            {pickup.state === 'cancelled' && (
              <div className="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-xs text-red-300 space-y-1 text-center">
                <XCircle size={24} className="mx-auto text-red-400" />
                <p className="font-bold text-sm text-white">Pickup Cancelled</p>
                <p className="text-red-300/80">
                  This pickup was cancelled. You may propose a new pickup time from your requests list.
                </p>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Reschedule Modal */}
      {showReschedule && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm animate-in fade-in duration-150">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 space-y-5 shadow-2xl">
            <h3 className="text-base font-bold text-white flex items-center gap-2">
              <RotateCcw size={18} className="text-emerald-400" />
              <span>Reschedule Pickup</span>
            </h3>

            {pickup.state === 'otp_issued' && (
              <div className="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-300">
                Notice: Rescheduling will reset the state to &apos;proposed&apos; and invalidate any
                previously issued pickup code.
              </div>
            )}

            <form onSubmit={handleReschedule} className="space-y-4">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label htmlFor="new-date" className="block text-xs font-medium text-slate-300 mb-1">New Date</label>
                  <input
                    id="new-date"
                    type="date"
                    required
                    value={newDate}
                    onChange={(e) => setNewDate(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
                  />
                </div>
                <div>
                  <label htmlFor="new-time" className="block text-xs font-medium text-slate-300 mb-1">New Time</label>
                  <input
                    id="new-time"
                    type="time"
                    required
                    value={newTime}
                    onChange={(e) => setNewTime(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">
                  Reason / Notes for Counterparty
                </label>
                <textarea
                  rows={2}
                  value={rescheduleNotes}
                  onChange={(e) => setRescheduleNotes(e.target.value)}
                  placeholder="Explain why rescheduling is needed..."
                  className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
                />
              </div>

              {rescheduleError && (
                <p className="text-xs text-red-400 font-medium" role="alert">
                  {rescheduleError}
                </p>
              )}

              <div className="flex items-center justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setShowReschedule(false)}
                  className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium transition-all"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  id="submit-reschedule-btn"
                  disabled={actionLoading}
                  className="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-xl text-xs font-semibold transition-all"
                >
                  {actionLoading ? 'Rescheduling…' : 'Propose New Time'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Cancel Confirmation Dialog */}
      <ConfirmDialog
        isOpen={showCancel}
        title="Cancel Scheduled Pickup"
        message="Are you sure you want to cancel this pickup? Both parties will be notified."
        confirmText="Yes, Cancel Pickup"
        variant="danger"
        onConfirm={handleCancel}
        onCancel={() => setShowCancel(false)}
      />
    </div>
  );
}
