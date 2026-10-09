import React, { useState, useEffect, useRef } from 'react';
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
import {
  Package,
  Upload,
  X,
  Plus,
  Minus,
  AlertCircle,
  CheckCircle2,
  Image as ImageIcon,
  ArrowLeft,
  Save,
  Send,
  Info,
  RefreshCw,
} from 'lucide-react';

const CONDITIONS = [
  { value: 'new', label: 'Brand New', desc: 'Unopened, unused in original packaging' },
  { value: 'like_new', label: 'Like New', desc: 'Used once or twice, excellent condition' },
  { value: 'good', label: 'Good', desc: 'Fully functional, minor cosmetic wear' },
  { value: 'fair', label: 'Fair', desc: 'Usable, noticeable cosmetic wear or repair' },
];

const MAX_IMAGES = 5;
const MAX_IMAGE_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

export function PostDonation() {
  const { id } = useParams();
  const isEditMode = Boolean(id);
  const navigate = useNavigate();
  const { user } = useAuth();
  const { showSuccess, showError } = useToast();
  const { isSubmitting, withSubmitOnce } = useSubmitOnce();

  const fileInputRef = useRef(null);

  // Form State
  const [categories, setCategories] = useState([]);
  const [loadingInitial, setLoadingInitial] = useState(true);
  const [initialError, setInitialError] = useState(null);

  const [categoryId, setCategoryId] = useState('');
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [condition, setCondition] = useState('good');
  const [totalQuantity, setTotalQuantity] = useState(1);
  const [addressText, setAddressText] = useState('');
  const [latitude, setLatitude] = useState(null);
  const [longitude, setLongitude] = useState(null);
  const [pickupNotes, setPickupNotes] = useState('');
  const [status, setStatus] = useState('active');

  // Existing images & New image files to upload
  const [existingImages, setExistingImages] = useState([]);
  const [selectedFiles, setSelectedFiles] = useState([]); // { file, previewUrl, id }
  const [imageUploadError, setImageUploadError] = useState(null);
  const [savedDonationId, setSavedDonationId] = useState(null);

  // Edit Mode Locking Metadata
  const [hasAllocations, setHasAllocations] = useState(false);
  const [allocatedQuantity, setAllocatedQuantity] = useState(0);

  // Field validation errors
  const [fieldErrors, setFieldErrors] = useState({});

  useEffect(() => {
    let isMounted = true;

    async function loadData() {
      setLoadingInitial(true);
      setInitialError(null);
      try {
        // 1. Fetch categories
        const catRes = await api.get('/api/categories');
        if (isMounted && catRes?.data) {
          const activeCats = catRes.data.filter((c) => c.is_active !== false);
          setCategories(activeCats);
          if (activeCats.length > 0 && !isEditMode) {
            setCategoryId(String(activeCats[0].id));
          }
        }

        // 2. If edit mode, fetch existing donation
        if (isEditMode) {
          const donRes = await api.get(`/api/donations/${id}`);
          if (isMounted && donRes?.donation) {
            const don = donRes.donation;
            setTitle(don.title || '');
            setDescription(don.description || '');
            setCategoryId(String(don.category_id || ''));
            setCondition(don.condition || 'good');
            setTotalQuantity(don.total_quantity || 1);
            setAddressText(don.address_text || '');
            setPickupNotes(don.pickup_notes || '');
            setStatus(don.status || 'active');

            if (don.latitude != null && don.longitude != null) {
              setLatitude(parseFloat(don.latitude));
              setLongitude(parseFloat(don.longitude));
            }

            if (Array.isArray(don.images)) {
              setExistingImages(don.images);
            }

            // Check if allocations exist
            const allocated = (don.total_quantity || 0) - (don.available_quantity || 0);
            if (allocated > 0) {
              setHasAllocations(true);
              setAllocatedQuantity(allocated);
            }
          }
        }
      } catch (err) {
        if (isMounted) {
          setInitialError(err instanceof ApiError ? err : new ApiError(500, 'INIT_FAILED', 'Failed to initialize donation form.'));
        }
      } finally {
        if (isMounted) {
          setLoadingInitial(false);
        }
      }
    }

    loadData();

    return () => {
      isMounted = false;
    };
  }, [id, isEditMode]);

  // Image Selection Handler with strict client pre-checks
  const handleFileSelect = (e) => {
    const files = Array.from(e.target.files || []);
    if (!files.length) return;

    setImageUploadError(null);
    const currentTotal = existingImages.length + selectedFiles.length;

    if (currentTotal + files.length > MAX_IMAGES) {
      setImageUploadError(`You can upload at most ${MAX_IMAGES} photos in total. (Currently ${currentTotal})`);
      return;
    }

    const newFiles = [];
    for (const file of files) {
      if (!ALLOWED_IMAGE_TYPES.includes(file.type)) {
        setImageUploadError(`File "${file.name}" is not an accepted format (JPG, PNG, WEBP only).`);
        return;
      }
      if (file.size > MAX_IMAGE_SIZE_BYTES) {
        setImageUploadError(`File "${file.name}" exceeds the 5 MB maximum size limit.`);
        return;
      }
      newFiles.push({
        file,
        previewUrl: URL.createObjectURL(file),
        id: Math.random().toString(36).substring(2, 9),
      });
    }

    setSelectedFiles((prev) => [...prev, ...newFiles]);
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  const removeSelectedFile = (fileId) => {
    setSelectedFiles((prev) => {
      const target = prev.find((f) => f.id === fileId);
      if (target?.previewUrl) {
        URL.revokeObjectURL(target.previewUrl);
      }
      return prev.filter((f) => f.id !== fileId);
    });
  };

  const removeExistingImage = async (imageId) => {
    if (!isEditMode) return;
    try {
      await api.delete(`/api/donations/${id}/images/${imageId}`);
      setExistingImages((prev) => prev.filter((img) => img.id !== imageId));
      showSuccess('Image removed successfully');
    } catch (err) {
      showError(err.message || 'Failed to remove image');
    }
  };

  // Upload pending selected files to a specific donation
  const uploadPendingImages = async (targetDonationId) => {
    if (selectedFiles.length === 0) return true;

    for (const item of selectedFiles) {
      const formData = new FormData();
      formData.append('image', item.file);

      try {
        await api.post(`/api/donations/${targetDonationId}/images`, formData);
      } catch (err) {
        setImageUploadError(`Failed uploading "${item.file.name}". Your listing was saved, but please retry photo upload.`);
        return false;
      }
    }
    return true;
  };

  // Validate form inputs
  const validateForm = () => {
    const errors = {};
    if (!title.trim()) {
      errors.title = 'Title is required';
    } else if (title.trim().length < 5) {
      errors.title = 'Title must be at least 5 characters';
    } else if (title.trim().length > 150) {
      errors.title = 'Title cannot exceed 150 characters';
    }

    if (!description.trim()) {
      errors.description = 'Description is required';
    } else if (description.trim().length < 10) {
      errors.description = 'Description must be at least 10 characters';
    }

    if (!categoryId) {
      errors.category_id = 'Please select a category';
    }

    const qty = parseInt(totalQuantity, 10);
    if (isNaN(qty) || qty < 1) {
      errors.total_quantity = 'Quantity must be at least 1 unit';
    } else if (hasAllocations && qty < allocatedQuantity) {
      errors.total_quantity = `Quantity cannot be reduced below allocated units (${allocatedQuantity})`;
    }

    if (!addressText.trim()) {
      errors.address_text = 'Address or pickup area is required';
    }

    if (latitude === null || longitude === null) {
      errors.location = 'Please select your approximate location on the map';
    }

    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  };

  // Submit Handler
  const handleSubmit = (targetStatus) => async () => {
    if (!validateForm()) {
      showError('Please fix the errors in the form before submitting.');
      return;
    }

    await withSubmitOnce(async () => {
      setImageUploadError(null);
      const payload = {
        category_id: parseInt(categoryId, 10),
        title: title.trim(),
        description: description.trim(),
        condition,
        total_quantity: parseInt(totalQuantity, 10),
        address_text: addressText.trim(),
        latitude: parseFloat(latitude),
        longitude: parseFloat(longitude),
        pickup_notes: pickupNotes.trim() || undefined,
        status: targetStatus,
      };

      try {
        let resultDonationId = id;

        if (isEditMode) {
          const res = await api.patch(`/api/donations/${id}`, payload);
          showSuccess('Donation listing updated successfully!');
          resultDonationId = res?.donation?.id || id;
        } else {
          const res = await api.post('/api/donations', payload);
          resultDonationId = res?.donation?.id;
          setSavedDonationId(resultDonationId);
          showSuccess('Donation listing created successfully!');
        }

        // Step 2: Upload selected images
        if (selectedFiles.length > 0 && resultDonationId) {
          const imagesUploaded = await uploadPendingImages(resultDonationId);
          if (!imagesUploaded) {
            // Photos failed; remain on screen with retry option
            return;
          }
        }

        // Navigate to my donations or donation detail
        navigate(`/donor/donations/${resultDonationId}`);
      } catch (err) {
        if (err instanceof ApiError && err.fields && Object.keys(err.fields).length > 0) {
          setFieldErrors(err.fields);
          showError(err.message || 'Validation failed. Please review the highlighted fields.');
        } else {
          showError(err.message || 'Failed to save donation listing.');
        }
      }
    });
  };

  if (loadingInitial) {
    return <LoadingState message="Preparing donation form..." />;
  }

  if (initialError) {
    return <ErrorState error={initialError} onRetry={() => window.location.reload()} />;
  }

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8 animate-fadeIn">
      {/* Navigation Breadcrumb */}
      <div className="flex items-center justify-between">
        <Link
          to="/donor/donations"
          className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-400 hover:text-white transition-colors"
        >
          <ArrowLeft size={14} />
          <span>Back to My Donations</span>
        </Link>
      </div>

      <PageHeader
        title={isEditMode ? 'Edit Donation Listing' : 'Post a Surplus Donation'}
        subtitle="List your items to be matched algorithmically with verified local NGOs based on category, proximity, and urgency."
      />

      {/* Allocation Rule Notice in Edit Mode */}
      {hasAllocations && (
        <div className="bg-amber-950/40 border border-amber-800/70 rounded-2xl p-4 flex items-start gap-3 text-xs text-amber-300">
          <AlertCircle size={18} className="shrink-0 mt-0.5 text-amber-400" />
          <div>
            <strong className="font-semibold block mb-0.5">Active Claim Allocations in Progress</strong>
            <span>
              This listing has {allocatedQuantity} unit(s) reserved or allocated for pickup. To protect community agreements, the category is locked and the total quantity cannot be lowered below {allocatedQuantity}.
            </span>
          </div>
        </div>
      )}

      {/* Image Upload Error & Retry Banner */}
      {imageUploadError && (
        <div className="bg-red-950/40 border border-red-800/80 rounded-2xl p-4 flex items-center justify-between gap-4 text-xs text-red-300">
          <div className="flex items-center gap-2.5">
            <AlertCircle size={18} className="shrink-0 text-red-400" />
            <span>{imageUploadError}</span>
          </div>
          {savedDonationId && (
            <button
              type="button"
              onClick={() => uploadPendingImages(savedDonationId)}
              disabled={isSubmitting}
              className="px-3 py-1.5 rounded-lg bg-red-900/60 hover:bg-red-800 text-white font-semibold transition-colors flex items-center gap-1.5 cursor-pointer"
            >
              <RefreshCw size={13} className={isSubmitting ? 'animate-spin' : ''} />
              <span>Retry Upload</span>
            </button>
          )}
        </div>
      )}

      <form className="space-y-8" onSubmit={(e) => e.preventDefault()}>
        {/* Step 1: Item Information */}
        <div className="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-6">
          <div className="flex items-center gap-2.5 border-b border-slate-800 pb-4">
            <Package size={20} className="text-emerald-400" />
            <h3 className="text-base font-bold text-white">Item Details</h3>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Category Dropdown */}
            <FormField
              id="category_id"
              label="Item Category"
              required
              error={fieldErrors.category_id}
              hint={hasAllocations ? 'Category locked due to existing allocations' : 'Select the primary item category'}
            >
              <select
                id="category_id"
                value={categoryId}
                onChange={(e) => setCategoryId(e.target.value)}
                disabled={hasAllocations}
                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 disabled:opacity-60 disabled:cursor-not-allowed"
              >
                {categories.map((cat) => (
                  <option key={cat.id} value={cat.id}>
                    {cat.name}
                  </option>
                ))}
              </select>
            </FormField>

            {/* Condition Selection */}
            <FormField
              id="condition"
              label="Item Condition"
              required
              error={fieldErrors.condition}
              hint="Be accurate to help NGOs fulfill verified needs"
            >
              <select
                id="condition"
                value={condition}
                onChange={(e) => setCondition(e.target.value)}
                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 capitalize"
              >
                {CONDITIONS.map((c) => (
                  <option key={c.value} value={c.value}>
                    {c.label} ({c.desc})
                  </option>
                ))}
              </select>
            </FormField>
          </div>

          {/* Title */}
          <FormField
            id="title"
            label="Listing Title"
            required
            error={fieldErrors.title}
            hint={`${title.length}/150 characters (e.g. "50 Unopened Notebooks & Writing Sets")`}
          >
            <input
              id="title"
              type="text"
              maxLength={150}
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              placeholder="e.g. 20 Winter Jackets and Blankets"
              className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500"
            />
          </FormField>

          {/* Description */}
          <FormField
            id="description"
            label="Item Description & Condition Details"
            required
            error={fieldErrors.description}
            hint={`${description.length}/1000 characters. Mention sizes, brand, storage state, and expiry (if food).`}
          >
            <textarea
              id="description"
              rows={4}
              maxLength={1000}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Provide a clear description of the items, packaging condition, and any specific pickup guidelines..."
              className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 leading-relaxed"
            />
          </FormField>

          {/* Quantity Stepper */}
          <div className="w-full sm:w-1/2">
            <FormField
              id="total_quantity"
              label="Total Available Quantity (Units)"
              required
              error={fieldErrors.total_quantity}
              hint={hasAllocations ? `Must be ≥ ${allocatedQuantity} (already allocated)` : 'Minimum 1 unit'}
            >
              <div className="flex items-center gap-3">
                <button
                  type="button"
                  onClick={() => setTotalQuantity((q) => Math.max(hasAllocations ? allocatedQuantity : 1, (parseInt(q, 10) || 1) - 1))}
                  className="p-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 transition-colors cursor-pointer"
                  aria-label="Decrease quantity"
                >
                  <Minus size={16} />
                </button>
                <input
                  id="total_quantity"
                  type="number"
                  min={hasAllocations ? allocatedQuantity : 1}
                  value={totalQuantity}
                  onChange={(e) => setTotalQuantity(e.target.value)}
                  className="w-24 text-center bg-slate-950 border border-slate-800 rounded-xl py-2 text-base font-bold text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500"
                />
                <button
                  type="button"
                  onClick={() => setTotalQuantity((q) => (parseInt(q, 10) || 0) + 1)}
                  className="p-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 transition-colors cursor-pointer"
                  aria-label="Increase quantity"
                >
                  <Plus size={16} />
                </button>
              </div>
            </FormField>
          </div>
        </div>

        {/* Step 2: Photos / Image Upload */}
        <div className="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-6">
          <div className="flex items-center justify-between border-b border-slate-800 pb-4">
            <div className="flex items-center gap-2.5">
              <ImageIcon size={20} className="text-teal-400" />
              <h3 className="text-base font-bold text-white">Photos & Proof</h3>
            </div>
            <span className="text-xs text-slate-400">
              {existingImages.length + selectedFiles.length} of {MAX_IMAGES} photos
            </span>
          </div>

          <p className="text-xs text-slate-400 leading-relaxed">
            High quality photos help NGOs quickly verify items and schedule pickups. Max 5 MB per file (JPG, PNG, WEBP).
          </p>

          {/* Photo Gallery & Upload Drop Area */}
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4">
            {/* Existing Images */}
            {existingImages.map((img) => (
              <div
                key={img.id}
                className="relative aspect-square rounded-xl overflow-hidden border border-slate-700 bg-slate-950 group"
              >
                <img
                  src={`/api/media/donation-images/${img.id}`}
                  alt="Donation attachment"
                  className="w-full h-full object-cover"
                />
                <button
                  type="button"
                  onClick={() => removeExistingImage(img.id)}
                  className="absolute top-1.5 right-1.5 p-1 rounded-full bg-red-600/80 hover:bg-red-600 text-white transition-opacity opacity-0 group-hover:opacity-100 cursor-pointer"
                  title="Remove image"
                >
                  <X size={14} />
                </button>
              </div>
            ))}

            {/* Pending New Files */}
            {selectedFiles.map((item) => (
              <div
                key={item.id}
                className="relative aspect-square rounded-xl overflow-hidden border border-emerald-500/60 bg-slate-950 group"
              >
                <img
                  src={item.previewUrl}
                  alt="Upload preview"
                  className="w-full h-full object-cover"
                />
                <button
                  type="button"
                  onClick={() => removeSelectedFile(item.id)}
                  className="absolute top-1.5 right-1.5 p-1 rounded-full bg-slate-900/90 hover:bg-red-600 text-white transition-colors cursor-pointer"
                  title="Remove photo"
                >
                  <X size={14} />
                </button>
                <div className="absolute bottom-1 left-1 px-1.5 py-0.5 rounded bg-emerald-950/80 text-[10px] text-emerald-400 font-semibold border border-emerald-800">
                  New
                </div>
              </div>
            ))}

            {/* Add Photo Button Tile */}
            {existingImages.length + selectedFiles.length < MAX_IMAGES && (
              <label className="aspect-square rounded-xl border-2 border-dashed border-slate-700 hover:border-emerald-500/70 bg-slate-950/40 hover:bg-slate-950/80 flex flex-col items-center justify-center gap-2 cursor-pointer transition-colors p-3 text-center">
                <Upload size={22} className="text-slate-400" />
                <span className="text-xs font-semibold text-slate-300">Upload Photo</span>
                <span className="text-[10px] text-slate-500">&le; 5 MB</span>
                <input
                  ref={fileInputRef}
                  type="file"
                  multiple
                  accept="image/jpeg,image/png,image/webp"
                  onChange={handleFileSelect}
                  className="hidden"
                />
              </label>
            )}
          </div>
        </div>

        {/* Step 3: Location & Pickup Logistics */}
        <div className="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-6">
          <div className="flex items-center gap-2.5 border-b border-slate-800 pb-4">
            <Info size={20} className="text-amber-400" />
            <h3 className="text-base font-bold text-white">Pickup Location & Logistics</h3>
          </div>

          {/* Address Text */}
          <FormField
            id="address_text"
            label="Street Address / Area Landmark"
            required
            error={fieldErrors.address_text}
            hint="Exact address is hidden from the public and revealed only to the NGO once a pickup is confirmed."
          >
            <input
              id="address_text"
              type="text"
              value={addressText}
              onChange={(e) => setAddressText(e.target.value)}
              placeholder="e.g. Apartment 4B, Greenview Towers, Sector 15"
              className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500"
            />
          </FormField>

          {/* Map Location Picker */}
          <LocationPicker
            latitude={latitude}
            longitude={longitude}
            onChange={({ latitude: lat, longitude: lng }) => {
              setLatitude(lat);
              setLongitude(lng);
            }}
            label="Select Pickup Coordinates on Map"
            required
            error={fieldErrors.location}
          />

          {/* Pickup Notes */}
          <FormField
            id="pickup_notes"
            label="Pickup Instructions (Optional)"
            error={fieldErrors.pickup_notes}
            hint="e.g. 'Call 15 minutes before arrival', 'Gate code is #1234', 'Available on weekends only'"
          >
            <textarea
              id="pickup_notes"
              rows={2}
              value={pickupNotes}
              onChange={(e) => setPickupNotes(e.target.value)}
              placeholder="Add any helpful handover notes for the NGO pickup team..."
              className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500"
            />
          </FormField>
        </div>

        {/* Action Buttons */}
        <div className="flex flex-col-reverse sm:flex-row items-center justify-end gap-3 pt-4">
          <Link
            to="/donor/donations"
            className="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-slate-800 hover:bg-slate-800 text-slate-300 font-semibold text-xs text-center transition-colors"
          >
            Cancel
          </Link>

          <button
            type="button"
            onClick={handleSubmit('draft')}
            disabled={isSubmitting}
            className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-xs transition-colors cursor-pointer"
          >
            <Save size={15} />
            <span>Save as Draft</span>
          </button>

          <button
            type="button"
            onClick={handleSubmit('active')}
            disabled={isSubmitting}
            className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-semibold text-xs shadow-lg shadow-emerald-950/50 transition-all cursor-pointer"
          >
            <Send size={15} />
            <span>{isSubmitting ? 'Publishing...' : isEditMode ? 'Save & Update Listing' : 'Publish Donation Listing'}</span>
          </button>
        </div>
      </form>
    </div>
  );
}
