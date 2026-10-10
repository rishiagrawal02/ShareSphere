import React, { useState, useEffect } from 'react';
import { useSearchParams, useNavigate, Link } from 'react-router-dom';
import { api, ApiError } from '../../api/client';
import { useAuth } from '../../auth/AuthContext';
import { PageHeader } from '../../components/PageHeader';
import { FormField } from '../../components/FormField';
import { LoadingState } from '../../components/LoadingState';
import { ErrorState } from '../../components/ErrorState';
import { useToast } from '../../components/Toast';
import { Calendar, Clock, MapPin, Phone, ArrowLeft, Send } from 'lucide-react';

export function SchedulePickup() {
  const [searchParams] = useSearchParams();
  const allocationId = searchParams.get('allocation_id');
  const navigate = useNavigate();
  const { user } = useAuth();
  const { showSuccess, showError } = useToast();

  const [allocation, setAllocation] = useState(null);
  const [loadingAllocation, setLoadingAllocation] = useState(Boolean(allocationId));
  const [allocError, setAllocError] = useState(null);

  // Form fields
  const [dateStr, setDateStr] = useState('');
  const [timeStr, setTimeStr] = useState('');
  const [locationDetails, setLocationDetails] = useState('');
  const [contactNote, setContactNote] = useState('');
  const [fieldErrors, setFieldErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);

  // Default to tomorrow 10:00 AM
  useEffect(() => {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    tomorrow.setHours(10, 0, 0, 0);

    const pad = (n) => String(n).padStart(2, '0');
    const yyyy = tomorrow.getFullYear();
    const mm = pad(tomorrow.getMonth() + 1);
    const dd = pad(tomorrow.getDate());
    setDateStr(`${yyyy}-${mm}-${dd}`);
    setTimeStr('10:00');
  }, []);

  // Fetch allocation info if allocation_id passed
  useEffect(() => {
    if (!allocationId) return;

    let mounted = true;
    async function fetchAlloc() {
      try {
        setLoadingAllocation(true);
        // We can fetch allocation or pickups list
        const res = await api.get(`/api/pickups?allocation_id=${allocationId}`);
        if (!mounted) return;
        // If an active pickup already exists, redirect to it
        const existing = res?.data?.[0];
        if (existing && existing.state !== 'cancelled') {
          navigate(`/pickups/${existing.id}`, { replace: true });
          return;
        }
      } catch {
        // Fallback: continue form
      } finally {
        if (mounted) setLoadingAllocation(false);
      }
    }
    fetchAlloc();
    return () => {
      mounted = false;
    };
  }, [allocationId, navigate]);

  const validate = () => {
    const errors = {};
    if (!dateStr || !timeStr) {
      errors.scheduled_at = 'Date and time are required';
    } else {
      const selected = new Date(`${dateStr}T${timeStr}`);
      const now = new Date();
      const diffMs = selected.getTime() - now.getTime();
      const oneHourMs = 3600 * 1000;
      const sixtyDaysMs = 60 * 86400 * 1000;

      // Spec §10.1 / T-15.1-02: must be at least 1 hour in future and <= 60 days
      if (isNaN(selected.getTime()) || diffMs < oneHourMs) {
        errors.scheduled_at = 'Pickup time must be at least 1 hour in the future.';
      } else if (diffMs > sixtyDaysMs) {
        errors.scheduled_at = 'Pickup time cannot be more than 60 days in the future.';
      }
    }

    if (locationDetails && locationDetails.length > 500) {
      errors.location_details = 'Location details cannot exceed 500 characters.';
    }

    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!validate()) return;

    if (!allocationId) {
      showError('Missing allocation ID for this pickup.');
      return;
    }

    setSubmitting(true);
    setFieldErrors({});

    try {
      // Local time -> UTC ISO string
      const localDate = new Date(`${dateStr}T${timeStr}`);
      const scheduledUtc = localDate.toISOString();

      const payload = {
        allocation_id: Number(allocationId),
        scheduled_at: scheduledUtc,
        location_details: locationDetails.trim() || undefined,
        contact_note: contactNote.trim() || undefined,
      };

      const res = await api.post('/api/pickups', payload);
      const created = res?.data;
      showSuccess('Pickup time proposed successfully!');
      navigate(`/pickups/${created.id}`);
    } catch (err) {
      if (err instanceof ApiError && err.status === 422) {
        const details = err.details || {};
        setFieldErrors(details);
        showError(details.scheduled_at || err.message || 'Please correct the scheduling errors.');
      } else {
        showError(err.message || 'Could not propose pickup.');
      }
    } finally {
      setSubmitting(false);
    }
  };

  if (loadingAllocation) return <LoadingState message="Checking pickup status…" />;

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      <PageHeader
        title="Propose Pickup Time"
        subtitle="Coordinate a convenient physical handover time with the other party."
        action={
          <Link
            to={user?.role === 'ngo' ? '/ngo/requests' : '/donor/requests'}
            className="flex items-center gap-1.5 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium border border-slate-700 transition-all"
          >
            <ArrowLeft size={14} />
            Back
          </Link>
        }
      />

      <form onSubmit={handleSubmit} className="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 space-y-6 shadow-xl">
        {/* Date and Time Pickers */}
        <div className="space-y-4">
          <h3 className="text-sm font-semibold text-white flex items-center gap-2">
            <Calendar size={16} className="text-emerald-400" />
            <span>Proposed Date & Time</span>
          </h3>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <FormField label="Pickup Date" id="pickup_date" required>
              <input
                type="date"
                id="pickup_date"
                value={dateStr}
                onChange={(e) => {
                  setDateStr(e.target.value);
                  setFieldErrors((prev) => ({ ...prev, scheduled_at: undefined }));
                }}
                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-emerald-500"
              />
            </FormField>

            <FormField label="Pickup Time (IST)" id="pickup_time" required>
              <input
                type="time"
                id="pickup_time"
                value={timeStr}
                onChange={(e) => {
                  setTimeStr(e.target.value);
                  setFieldErrors((prev) => ({ ...prev, scheduled_at: undefined }));
                }}
                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-emerald-500"
              />
            </FormField>
          </div>

          {fieldErrors.scheduled_at && (
            <p className="text-xs text-red-400 font-medium" role="alert">
              {fieldErrors.scheduled_at}
            </p>
          )}

          <p className="text-xs text-slate-500">
            Pickups must be scheduled at least 1 hour in advance to give both parties time to prepare.
          </p>
        </div>

        {/* Location & Handover Instructions */}
        <div className="space-y-4 pt-4 border-t border-slate-800/80">
          <h3 className="text-sm font-semibold text-white flex items-center gap-2">
            <MapPin size={16} className="text-emerald-400" />
            <span>Location & Handover Instructions</span>
          </h3>

          <FormField
            label="Handover Notes / Landmarks"
            id="location_details"
            hint="e.g. Ring bell at main entrance; park near loading bay; ask for reception"
            error={fieldErrors.location_details}
          >
            <textarea
              id="location_details"
              rows={3}
              maxLength={500}
              value={locationDetails}
              onChange={(e) => setLocationDetails(e.target.value)}
              placeholder="Provide specific directions or instructions for the handover representative..."
              className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-emerald-500"
            />
          </FormField>
        </div>

        {/* Contact Info */}
        <div className="space-y-4 pt-4 border-t border-slate-800/80">
          <h3 className="text-sm font-semibold text-white flex items-center gap-2">
            <Phone size={16} className="text-emerald-400" />
            <span>Handover Representative Contact</span>
          </h3>

          <FormField
            label="Contact Person & Phone Number"
            id="contact_note"
            hint="Optional: Name and direct phone number of the person attending the handover"
            error={fieldErrors.contact_note}
          >
            <input
              type="text"
              id="contact_note"
              value={contactNote}
              onChange={(e) => setContactNote(e.target.value)}
              placeholder="e.g. Rahul Sharma (+91 98765 43210)"
              className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-emerald-500"
            />
          </FormField>
        </div>

        {/* Submit */}
        <div className="pt-4 border-t border-slate-800/80 flex items-center justify-end gap-3">
          <Link
            to={user?.role === 'ngo' ? '/ngo/requests' : '/donor/requests'}
            className="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-sm font-medium transition-all"
          >
            Cancel
          </Link>
          <button
            type="submit"
            id="submit-propose-pickup"
            disabled={submitting}
            className="flex items-center gap-2 px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-xl text-sm font-semibold shadow-lg shadow-emerald-950/40 transition-all"
          >
            <Send size={15} />
            <span>{submitting ? 'Proposing…' : 'Propose Pickup'}</span>
          </button>
        </div>
      </form>
    </div>
  );
}
