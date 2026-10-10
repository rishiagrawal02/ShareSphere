import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate, useParams, Link } from 'react-router-dom';
import { api, ApiError } from '../../api/client';
import { useAuth } from '../../auth/AuthContext';
import { useToast } from '../../components/Toast';
import { useSubmitOnce } from '../../components/useSubmitOnce';
import { PageHeader } from '../../components/PageHeader';
import { FormField } from '../../components/FormField';
import { LocationPicker } from '../../components/LocationPicker';
import { LoadingState } from '../../components/LoadingState';
import { ErrorState } from '../../components/ErrorState';
import { NgoVerificationBanner } from './NgoVerificationBanner';
import {
  ClipboardList,
  AlertCircle,
  ArrowLeft,
  Save,
  Send,
  Sliders,
  Info,
} from 'lucide-react';

const URGENCY_OPTIONS = [
  { value: 'critical', label: 'Critical', desc: 'Immediate / life-sustaining need' },
  { value: 'high', label: 'High', desc: 'Needed within days' },
  { value: 'medium', label: 'Medium', desc: 'Needed within a few weeks' },
  { value: 'low', label: 'Low', desc: 'Ongoing, no immediate deadline' },
];

const CONDITION_OPTIONS = [
  { value: 'new', label: 'New' },
  { value: 'like_new', label: 'Like New' },
  { value: 'good', label: 'Good' },
  { value: 'fair', label: 'Fair' },
];

function today() {
  return new Date().toISOString().split('T')[0];
}

function clamp(val, min, max) {
  return Math.max(min, Math.min(max, val));
}

export function PostRequirement() {
  const { id } = useParams();
  const isEditMode = Boolean(id);
  const navigate = useNavigate();
  const { user } = useAuth();
  const { showSuccess, showError } = useToast();
  const { isSubmitting, withSubmitOnce } = useSubmitOnce();

  const verificationStatus = user?.ngo?.verification_status || 'pending';
  const isVerified = verificationStatus === 'verified';

  // Lookup data
  const [categories, setCategories] = useState([]);
  const [loadingInitial, setLoadingInitial] = useState(true);
  const [initialError, setInitialError] = useState(null);

  // Edit-mode lock
  const [allocatedQuantity, setAllocatedQuantity] = useState(0);

  // Form fields
  const [categoryId, setCategoryId] = useState('');
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [quantityNeeded, setQuantityNeeded] = useState(10);
  const [urgency, setUrgency] = useState('medium');
  const [minCondition, setMinCondition] = useState('good');
  const [radiusKm, setRadiusKm] = useState(25);
  const [neededBy, setNeededBy] = useState('');
  const [latitude, setLatitude] = useState(null);
  const [longitude, setLongitude] = useState(null);

  // Field errors
  const [fieldErrors, setFieldErrors] = useState({});

  const loadData = useCallback(async () => {
    setLoadingInitial(true);
    setInitialError(null);
    try {
      const catRes = await api.get('/api/categories');
      if (catRes?.data) {
        setCategories(catRes.data.filter((c) => c.is_active));
      }

      if (isEditMode) {
        const reqRes = await api.get(`/api/requirements/${id}`);
        const r = reqRes?.data?.requirement || reqRes?.data;
        if (r) {
          setCategoryId(String(r.category_id ?? ''));
          setTitle(r.title ?? '');
          setDescription(r.description ?? '');
          setQuantityNeeded(r.quantity_needed ?? 10);
          setUrgency(r.urgency ?? 'medium');
          setMinCondition(r.min_condition ?? 'good');
          setRadiusKm(r.radius_km ?? 25);
          setNeededBy(r.needed_by ? r.needed_by.split('T')[0] : '');
          setLatitude(r.latitude != null ? parseFloat(r.latitude) : null);
          setLongitude(r.longitude != null ? parseFloat(r.longitude) : null);
          setAllocatedQuantity(r.quantity_allocated ?? 0);
        }
      } else {
        // Default to NGO's own location
        const ngo = user?.ngo;
        if (ngo?.latitude != null) setLatitude(parseFloat(ngo.latitude));
        if (ngo?.longitude != null) setLongitude(parseFloat(ngo.longitude));
        if (ngo?.service_radius_km) setRadiusKm(ngo.service_radius_km);
      }
    } catch (err) {
      setInitialError(
        err instanceof ApiError ? err : new ApiError(500, 'LOAD_FAILED', 'Could not load form data.')
      );
    } finally {
      setLoadingInitial(false);
    }
  }, [id, isEditMode, user]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const validate = () => {
    const errs = {};
    if (!categoryId) errs.categoryId = 'Please select a category.';
    if (!title.trim()) errs.title = 'Title is required.';
    if (title.trim().length < 5) errs.title = 'Title must be at least 5 characters.';
    const qty = Number(quantityNeeded);
    if (!qty || qty < 1) errs.quantityNeeded = 'Quantity must be at least 1.';
    if (isEditMode && qty < allocatedQuantity)
      errs.quantityNeeded = `Cannot set below already-allocated quantity (${allocatedQuantity}).`;
    if (radiusKm < 1 || radiusKm > 500) errs.radiusKm = 'Radius must be between 1 and 500 km.';
    if (!neededBy) errs.neededBy = 'Please choose a needed-by date.';
    else if (neededBy < today()) errs.neededBy = 'Needed-by date must be in the future.';
    if (latitude == null || longitude == null) errs.location = 'Please pin a location on the map.';
    return errs;
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    const errs = validate();
    setFieldErrors(errs);
    if (Object.keys(errs).length > 0) return;

    withSubmitOnce(async () => {
      const payload = {
        category_id: Number(categoryId),
        title: title.trim(),
        description: description.trim(),
        quantity_needed: Number(quantityNeeded),
        urgency,
        min_condition: minCondition,
        radius_km: Number(radiusKm),
        needed_by: neededBy,
        latitude,
        longitude,
      };

      try {
        if (isEditMode) {
          await api.patch(`/api/requirements/${id}`, payload);
          showSuccess('Requirement updated successfully.');
          navigate(`/ngo/requirements`);
        } else {
          const res = await api.post('/api/requirements', payload);
          const newId = res?.data?.requirement?.id;
          showSuccess('Requirement posted! Matching donations will appear shortly.');
          navigate(newId ? `/ngo/requirements/${newId}/matches` : '/ngo/requirements');
        }
      } catch (err) {
        if (err instanceof ApiError && err.fields) {
          setFieldErrors(err.fields);
          showError('Please fix the highlighted errors.');
        } else {
          showError(err?.message || 'Failed to save requirement. Please try again.');
        }
      }
    });
  };

  if (loadingInitial) return <LoadingState message="Loading requirement form…" />;
  if (initialError) return <ErrorState error={initialError} onRetry={loadData} />;

  // Block non-verified NGOs from creating
  if (!isVerified) {
    return (
      <div className="space-y-6">
        <PageHeader
          title="Post Requirement"
          subtitle="Tell donors what your NGO needs."
          back={{ to: '/ngo', label: 'Dashboard' }}
        />
        <NgoVerificationBanner
          status={verificationStatus}
          adminNote={user?.ngo?.admin_note}
        />
      </div>
    );
  }

  const quantityEditable = !isEditMode || allocatedQuantity === 0;
  const quantityMin = isEditMode ? allocatedQuantity + 1 : 1;

  return (
    <div className="space-y-8">
      <PageHeader
        title={isEditMode ? 'Edit Requirement' : 'Post Requirement'}
        subtitle={
          isEditMode
            ? 'Update requirement details. Quantity cannot be reduced below allocated.'
            : 'Describe what your NGO needs and where it is needed.'
        }
        action={
          <Link
            to="/ngo/requirements"
            className="flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-sm font-medium transition-all"
          >
            <ArrowLeft size={15} />
            Back to Requirements
          </Link>
        }
      />

      <form onSubmit={handleSubmit} noValidate className="space-y-8 max-w-2xl">
        {/* Category & Title */}
        <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-5">
          <h3 className="text-sm font-semibold text-white flex items-center gap-2">
            <ClipboardList size={17} className="text-emerald-400" />
            Requirement Details
          </h3>

          <FormField
            label="Category"
            htmlFor="req-category"
            required
            error={fieldErrors.categoryId}
          >
            <select
              id="req-category"
              value={categoryId}
              onChange={(e) => { setCategoryId(e.target.value); setFieldErrors((p) => ({ ...p, categoryId: undefined })); }}
              className={`w-full bg-slate-800 border rounded-xl px-4 py-3 text-sm text-white appearance-none focus:outline-none focus:ring-2 focus:ring-emerald-500 ${
                fieldErrors.categoryId ? 'border-red-500' : 'border-slate-700'
              }`}
              required
            >
              <option value="">Select category…</option>
              {categories.map((c) => (
                <option key={c.id} value={c.id}>{c.name}</option>
              ))}
            </select>
          </FormField>

          <FormField label="Title" htmlFor="req-title" required error={fieldErrors.title}>
            <input
              id="req-title"
              type="text"
              value={title}
              maxLength={120}
              onChange={(e) => { setTitle(e.target.value); setFieldErrors((p) => ({ ...p, title: undefined })); }}
              placeholder="e.g. Need 50 winter blankets for relief camp"
              className={`w-full bg-slate-800 border rounded-xl px-4 py-3 text-sm text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 ${
                fieldErrors.title ? 'border-red-500' : 'border-slate-700'
              }`}
              required
            />
          </FormField>

          <FormField label="Description" htmlFor="req-desc" error={fieldErrors.description}>
            <textarea
              id="req-desc"
              value={description}
              rows={3}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Additional context, specific requirements, use-case…"
              className="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 resize-none"
            />
          </FormField>
        </div>

        {/* Quantity, Urgency, Min Condition */}
        <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-5">
          <h3 className="text-sm font-semibold text-white flex items-center gap-2">
            <Sliders size={17} className="text-teal-400" />
            Quantity &amp; Condition
          </h3>

          <FormField
            label="Quantity Needed"
            htmlFor="req-qty"
            required
            error={fieldErrors.quantityNeeded}
            hint={
              !quantityEditable
                ? `Cannot reduce below allocated quantity (${allocatedQuantity})`
                : undefined
            }
          >
            <input
              id="req-qty"
              type="number"
              min={quantityMin}
              max={100000}
              value={quantityNeeded}
              onChange={(e) => {
                setQuantityNeeded(e.target.value);
                setFieldErrors((p) => ({ ...p, quantityNeeded: undefined }));
              }}
              disabled={!quantityEditable}
              className={`w-full bg-slate-800 border rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed ${
                fieldErrors.quantityNeeded ? 'border-red-500' : 'border-slate-700'
              }`}
              required
            />
          </FormField>

          {/* Urgency selector */}
          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-3">
              Urgency <span className="text-red-400">*</span>
            </label>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
              {URGENCY_OPTIONS.map((opt) => (
                <button
                  key={opt.value}
                  type="button"
                  id={`urgency-${opt.value}`}
                  onClick={() => setUrgency(opt.value)}
                  className={`text-left px-3 py-3 rounded-xl border transition-all ${
                    urgency === opt.value
                      ? 'border-emerald-500 bg-emerald-500/10 text-emerald-300'
                      : 'border-slate-700 bg-slate-800 text-slate-300 hover:border-slate-600'
                  }`}
                >
                  <p className="text-xs font-semibold">{opt.label}</p>
                  <p className="text-[10px] text-slate-400 mt-0.5 leading-snug">{opt.desc}</p>
                </button>
              ))}
            </div>
          </div>

          {/* Minimum Condition */}
          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-3">
              Minimum Acceptable Condition <span className="text-red-400">*</span>
            </label>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
              {CONDITION_OPTIONS.map((opt) => (
                <button
                  key={opt.value}
                  type="button"
                  id={`condition-${opt.value}`}
                  onClick={() => setMinCondition(opt.value)}
                  className={`text-center px-3 py-2.5 rounded-xl border transition-all text-sm font-medium ${
                    minCondition === opt.value
                      ? 'border-teal-500 bg-teal-500/10 text-teal-300'
                      : 'border-slate-700 bg-slate-800 text-slate-300 hover:border-slate-600'
                  }`}
                >
                  {opt.label}
                </button>
              ))}
            </div>
          </div>

          {/* Needed By Date */}
          <FormField
            label="Needed By Date"
            htmlFor="req-needed-by"
            required
            error={fieldErrors.neededBy}
          >
            <input
              id="req-needed-by"
              type="date"
              min={today()}
              value={neededBy}
              onChange={(e) => { setNeededBy(e.target.value); setFieldErrors((p) => ({ ...p, neededBy: undefined })); }}
              className={`w-full bg-slate-800 border rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 ${
                fieldErrors.neededBy ? 'border-red-500' : 'border-slate-700'
              }`}
              required
            />
          </FormField>
        </div>

        {/* Location & Radius */}
        <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-5">
          <h3 className="text-sm font-semibold text-white flex items-center gap-2">
            <Info size={17} className="text-blue-400" />
            Service Location &amp; Radius
          </h3>

          {/* Radius Slider */}
          <div>
            <label
              htmlFor="req-radius"
              className="block text-xs font-semibold text-slate-300 mb-2"
            >
              Match Radius:{' '}
              <span className="text-emerald-400 font-bold">{radiusKm} km</span>
            </label>
            <input
              id="req-radius"
              type="range"
              min={1}
              max={500}
              step={1}
              value={radiusKm}
              onChange={(e) => {
                setRadiusKm(Number(e.target.value));
                setFieldErrors((p) => ({ ...p, radiusKm: undefined }));
              }}
              aria-valuemin={1}
              aria-valuemax={500}
              aria-valuenow={radiusKm}
              aria-label={`Match radius: ${radiusKm} kilometres`}
              className="w-full accent-emerald-500 cursor-pointer"
            />
            <div className="flex justify-between text-[10px] text-slate-500 mt-1">
              <span>1 km</span>
              <span>250 km</span>
              <span>500 km</span>
            </div>
            {fieldErrors.radiusKm && (
              <p className="mt-1 text-xs text-red-400 flex items-center gap-1">
                <AlertCircle size={12} /> {fieldErrors.radiusKm}
              </p>
            )}
            <p className="text-xs text-slate-500 mt-2 flex items-start gap-1.5">
              <Info size={12} className="mt-0.5 shrink-0" />
              The server will search for active donations within this radius. The circle on the map
              is indicative only.
            </p>
          </div>

          {/* Location Picker */}
          <LocationPicker
            latitude={latitude}
            longitude={longitude}
            onChange={({ latitude: lat, longitude: lng }) => {
              setLatitude(lat);
              setLongitude(lng);
              setFieldErrors((p) => ({ ...p, location: undefined }));
            }}
            defaultCenter={[28.6139, 77.209]}
            height="320px"
            label="Requirement Service Location"
            required
            error={fieldErrors.location}
          />
        </div>

        {/* Submit */}
        <div className="flex items-center justify-between gap-4">
          <Link
            to="/ngo/requirements"
            className="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-sm font-medium transition-all"
          >
            Cancel
          </Link>
          <button
            id="post-requirement-submit"
            type="submit"
            disabled={isSubmitting}
            className="flex items-center gap-2 px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-sm font-semibold shadow-lg shadow-emerald-950/40 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isSubmitting ? (
              <>
                <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                Saving…
              </>
            ) : (
              <>
                {isEditMode ? <Save size={16} /> : <Send size={16} />}
                {isEditMode ? 'Save Changes' : 'Post Requirement'}
              </>
            )}
          </button>
        </div>
      </form>
    </div>
  );
}
