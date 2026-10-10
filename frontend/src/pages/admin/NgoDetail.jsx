import React, { useState, useEffect, useCallback } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { api, ApiError } from '../../api/client';
import { PageHeader } from '../../components/PageHeader';
import { StatusBadge } from '../../components/StatusBadge';
import { LoadingState } from '../../components/LoadingState';
import { ErrorState } from '../../components/ErrorState';
import { FormField } from '../../components/FormField';
import { useToast } from '../../components/Toast';
import { formatDate, formatDateTime } from '../../utils/date';
import {
  Building2,
  FileText,
  ShieldCheck,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  RotateCcw,
  ArrowLeft,
  ExternalLink,
  Phone,
  Mail,
  MapPin,
  Send,
  AlertCircle,
} from 'lucide-react';

export function NgoDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { showSuccess, showError } = useToast();

  const [ngo, setNgo] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Decision state
  const [decision, setDecision] = useState('');
  const [note, setNote] = useState('');
  const [noteError, setNoteError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const loadNgo = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.get(`/api/admin/ngos/${id}`);
      setNgo(res?.ngo || res?.data);
    } catch (err) {
      setError(
        err instanceof ApiError ? err : new ApiError(500, 'LOAD_FAILED', 'Could not load NGO details.')
      );
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadNgo();
  }, [loadNgo]);

  const handleSubmitDecision = async (e) => {
    e.preventDefault();
    if (!decision) {
      showError('Please choose a verification decision.');
      return;
    }

    // T-16.1-02: Review note is required for reject, request_correction, and suspend
    if (['reject', 'request_correction', 'suspend'].includes(decision) && !note.trim()) {
      setNoteError('A review note explaining this decision is mandatory.');
      return;
    }

    setSubmitting(true);
    setNoteError('');
    try {
      const payload = {
        decision,
        action: decision,
        note: note.trim() || undefined,
      };
      const res = await api.post(`/api/admin/ngos/${id}/verify`, payload);
      showSuccess(`Decision "${decision}" applied successfully.`);
      if (res?.ngo) {
        setNgo((prev) => ({ ...prev, ...res.ngo }));
      } else {
        await loadNgo();
      }
      setDecision('');
      setNote('');
    } catch (err) {
      if (err instanceof ApiError && err.fields?.note) {
        setNoteError(err.fields.note);
      } else {
        showError(err.message || 'Could not apply verification decision.');
      }
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) return <LoadingState message="Loading organization review…" />;
  if (error) return <ErrorState error={error} onRetry={loadNgo} />;
  if (!ngo) return <ErrorState message="Organization not found." onRetry={loadNgo} />;

  const status = ngo.verification_status || 'pending';
  const documents = ngo.documents || [];

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      {/* Top Header */}
      <PageHeader
        title={ngo.organization_name}
        subtitle={`Registration Verification Review for NGO #${ngo.id}`}
        action={
          <Link
            to="/admin/ngos"
            className="flex items-center gap-1.5 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium border border-slate-700 transition-all"
          >
            <ArrowLeft size={14} />
            Back to Queue
          </Link>
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left 2 Cols: Organization Data & Documents */}
        <div className="lg:col-span-2 space-y-6">
          {/* Org Profile Card */}
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-5 shadow-xl">
            <div className="flex items-center justify-between pb-4 border-b border-slate-800">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center">
                  <Building2 size={20} />
                </div>
                <div>
                  <h3 className="text-base font-bold text-white">{ngo.organization_name}</h3>
                  <p className="text-xs text-slate-400">
                    Reg #: <strong className="text-slate-200">{ngo.registration_number || 'N/A'}</strong>
                  </p>
                </div>
              </div>
              <StatusBadge status={status} />
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
              <div className="space-y-1">
                <span className="text-slate-500 block">Contact Representative</span>
                <span className="font-semibold text-white">{ngo.contact_person || '—'}</span>
              </div>
              <div className="space-y-1">
                <span className="text-slate-500 block">Contact Phone</span>
                <span className="font-semibold text-white flex items-center gap-1">
                  <Phone size={12} className="text-emerald-400" />
                  {ngo.contact_phone || '—'}
                </span>
              </div>
              <div className="space-y-1">
                <span className="text-slate-500 block">Official Email</span>
                <span className="font-semibold text-white flex items-center gap-1">
                  <Mail size={12} className="text-emerald-400" />
                  {ngo.email || ngo.user_email || '—'}
                </span>
              </div>
              <div className="space-y-1">
                <span className="text-slate-500 block">Registered On</span>
                <span className="font-semibold text-white">{formatDate(ngo.created_at)}</span>
              </div>
            </div>

            {ngo.address && (
              <div className="pt-3 border-t border-slate-800/80 text-xs">
                <span className="text-slate-500 block mb-0.5 flex items-center gap-1">
                  <MapPin size={12} className="text-emerald-400" />
                  Operating Address
                </span>
                <span className="text-slate-300 font-medium">{ngo.address}</span>
              </div>
            )}

            {ngo.verification_note && (
              <div className="p-3.5 rounded-xl bg-slate-950 border border-slate-800/80 text-xs space-y-1">
                <span className="text-amber-400 font-semibold block">Last Admin Note:</span>
                <p className="text-slate-300 leading-relaxed">{ngo.verification_note}</p>
              </div>
            )}
          </div>

          {/* Uploaded Verification Documents */}
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4 shadow-xl">
            <h4 className="text-sm font-bold text-white flex items-center gap-2">
              <FileText size={16} className="text-emerald-400" />
              <span>Verification Documents ({documents.length})</span>
            </h4>

            {documents.length === 0 ? (
              <div className="p-6 rounded-xl bg-slate-950 border border-slate-800 text-center text-xs text-slate-500">
                No legal documents have been uploaded by this organization yet.
              </div>
            ) : (
              <div className="divide-y divide-slate-800/80 rounded-xl bg-slate-950 border border-slate-800 overflow-hidden">
                {documents.map((doc) => {
                  const mediaUrl = doc.media_url || `/api/media/ngo-documents/${doc.id}`;
                  return (
                    <div
                      key={doc.id}
                      className="p-4 flex items-center justify-between gap-4 hover:bg-slate-900/60 transition-colors"
                    >
                      <div className="flex items-center gap-3 min-w-0">
                        <div className="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-400 flex items-center justify-center shrink-0">
                          <FileText size={16} />
                        </div>
                        <div className="min-w-0">
                          {/* Render strictly as text to prevent stored XSS (T-16.1-04) */}
                          <span className="text-xs font-semibold text-white block truncate">
                            {doc.file_name || `Document #${doc.id}`}
                          </span>
                          <span className="text-[10px] text-slate-500">
                            Uploaded {formatDate(doc.created_at)}
                          </span>
                        </div>
                      </div>

                      {/* T-16.1-05: Opened in a new tab with rel="noopener noreferrer" */}
                      <a
                        href={mediaUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium border border-slate-700 transition-all shrink-0"
                      >
                        <span>View Document</span>
                        <ExternalLink size={12} />
                      </a>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </div>

        {/* Right Col: Admin Decision Panel */}
        <div className="space-y-6">
          <form
            onSubmit={handleSubmitDecision}
            className="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-5 shadow-xl"
          >
            <h3 className="text-sm font-bold text-white flex items-center gap-2">
              <ShieldCheck size={16} className="text-emerald-400" />
              <span>Review Decision Panel</span>
            </h3>

            <div className="space-y-2">
              <label className="block text-xs font-semibold text-slate-300">Choose Action</label>
              <div className="space-y-2">
                {/* Approve */}
                {['pending', 'correction_requested'].includes(status) && (
                  <label
                    className={`flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-all ${
                      decision === 'approve'
                        ? 'bg-emerald-500/15 border-emerald-500/50 text-white'
                        : 'bg-slate-950 border-slate-800 text-slate-300 hover:border-slate-700'
                    }`}
                  >
                    <input
                      type="radio"
                      name="decision"
                      value="approve"
                      checked={decision === 'approve'}
                      onChange={() => {
                        setDecision('approve');
                        setNoteError('');
                      }}
                      className="mt-0.5 text-emerald-500"
                    />
                    <div>
                      <span className="font-semibold text-xs block text-emerald-400">
                        Approve (Verify NGO)
                      </span>
                      <span className="text-[11px] text-slate-400 block mt-0.5">
                        Authorizes organization to publish requirements and claim donations.
                      </span>
                    </div>
                  </label>
                )}

                {/* Request Correction */}
                {status === 'pending' && (
                  <label
                    className={`flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-all ${
                      decision === 'request_correction'
                        ? 'bg-amber-500/15 border-amber-500/50 text-white'
                        : 'bg-slate-950 border-slate-800 text-slate-300 hover:border-slate-700'
                    }`}
                  >
                    <input
                      type="radio"
                      name="decision"
                      value="request_correction"
                      checked={decision === 'request_correction'}
                      onChange={() => {
                        setDecision('request_correction');
                        setNoteError('');
                      }}
                      className="mt-0.5 text-amber-500"
                    />
                    <div>
                      <span className="font-semibold text-xs block text-amber-400">
                        Request Correction
                      </span>
                      <span className="text-[11px] text-slate-400 block mt-0.5">
                        Requires note explaining missing or illegible documents.
                      </span>
                    </div>
                  </label>
                )}

                {/* Reject */}
                {['pending', 'correction_requested'].includes(status) && (
                  <label
                    className={`flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-all ${
                      decision === 'reject'
                        ? 'bg-red-500/15 border-red-500/50 text-white'
                        : 'bg-slate-950 border-slate-800 text-slate-300 hover:border-slate-700'
                    }`}
                  >
                    <input
                      type="radio"
                      name="decision"
                      value="reject"
                      checked={decision === 'reject'}
                      onChange={() => {
                        setDecision('reject');
                        setNoteError('');
                      }}
                      className="mt-0.5 text-red-500"
                    />
                    <div>
                      <span className="font-semibold text-xs block text-red-400">Reject</span>
                      <span className="text-[11px] text-slate-400 block mt-0.5">
                        Permanently rejects registration. Review note is required.
                      </span>
                    </div>
                  </label>
                )}

                {/* Suspend */}
                {status === 'verified' && (
                  <label
                    className={`flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-all ${
                      decision === 'suspend'
                        ? 'bg-red-500/15 border-red-500/50 text-white'
                        : 'bg-slate-950 border-slate-800 text-slate-300 hover:border-slate-700'
                    }`}
                  >
                    <input
                      type="radio"
                      name="decision"
                      value="suspend"
                      checked={decision === 'suspend'}
                      onChange={() => {
                        setDecision('suspend');
                        setNoteError('');
                      }}
                      className="mt-0.5 text-red-500"
                    />
                    <div>
                      <span className="font-semibold text-xs block text-red-400">Suspend</span>
                      <span className="text-[11px] text-slate-400 block mt-0.5">
                        Suspends organization activity. Mandatory review note required.
                      </span>
                    </div>
                  </label>
                )}

                {/* Reinstate */}
                {status === 'suspended' && (
                  <label
                    className={`flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-all ${
                      decision === 'reinstate'
                        ? 'bg-emerald-500/15 border-emerald-500/50 text-white'
                        : 'bg-slate-950 border-slate-800 text-slate-300 hover:border-slate-700'
                    }`}
                  >
                    <input
                      type="radio"
                      name="decision"
                      value="reinstate"
                      checked={decision === 'reinstate'}
                      onChange={() => {
                        setDecision('reinstate');
                        setNoteError('');
                      }}
                      className="mt-0.5 text-emerald-500"
                    />
                    <div>
                      <span className="font-semibold text-xs block text-emerald-400">
                        Reinstate
                      </span>
                      <span className="text-[11px] text-slate-400 block mt-0.5">
                        Restores verified status for this suspended organization.
                      </span>
                    </div>
                  </label>
                )}
              </div>
            </div>

            {/* Note input */}
            <div className="space-y-1">
              <label htmlFor="admin-note" className="block text-xs font-semibold text-slate-300">
                Review Note {['reject', 'request_correction', 'suspend'].includes(decision) ? '(Required)' : '(Optional)'}
              </label>
              <textarea
                id="admin-note"
                rows={4}
                value={note}
                onChange={(e) => {
                  setNote(e.target.value);
                  setNoteError('');
                }}
                placeholder="Provide feedback or justification visible to the organization..."
                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-emerald-500"
              />
              {noteError && (
                <p className="text-xs text-red-400 font-medium" role="alert">
                  {noteError}
                </p>
              )}
            </div>

            <button
              type="submit"
              id="submit-decision-btn"
              disabled={submitting || !decision}
              className="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-xl text-xs font-semibold shadow-lg shadow-emerald-950/40 transition-all flex items-center justify-center gap-1.5"
            >
              <Send size={14} />
              <span>{submitting ? 'Applying Decision…' : 'Apply Decision'}</span>
            </button>
          </form>
        </div>
      </div>
    </div>
  );
}
