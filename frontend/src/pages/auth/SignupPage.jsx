import React, { useState } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../../auth/AuthContext';
import { FormField } from '../../components/FormField';
import { LocationPicker } from '../../components/LocationPicker';
import { useSubmitOnce } from '../../components/useSubmitOnce';
import {
  User,
  Building2,
  Lock,
  Mail,
  Phone,
  FileText,
  AlertCircle,
  CheckCircle2,
  Upload,
} from 'lucide-react';

export function SignupPage() {
  const { register } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const { isSubmitting, handleSubmit } = useSubmitOnce();

  const queryRole = new URLSearchParams(location.search).get('role');
  const [role, setRole] = useState(queryRole === 'ngo' ? 'ngo' : 'donor');

  // Common fields
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [phone, setPhone] = useState('');
  const [termsAccepted, setTermsAccepted] = useState(false);

  // NGO specific fields
  const [orgName, setOrgName] = useState('');
  const [regNumber, setRegNumber] = useState('');
  const [description, setDescription] = useState('');
  const [addressText, setAddressText] = useState('');
  const [latitude, setLatitude] = useState(40.7128);
  const [longitude, setLongitude] = useState(-74.0060);
  const [serviceRadiusKm, setServiceRadiusKm] = useState(25);
  const [documentFile, setDocumentFile] = useState(null);

  // Validation & errors
  const [fieldErrors, setFieldErrors] = useState({});
  const [globalError, setGlobalError] = useState('');

  // Password Strength check
  const getPasswordStrength = (pwd) => {
    if (!pwd) return { score: 0, label: 'None' };
    let score = 0;
    if (pwd.length >= 8) score += 1;
    if (pwd.length >= 12) score += 1;
    if (/[0-9]/.test(pwd)) score += 1;
    if (/[^A-Za-z0-9]/.test(pwd)) score += 1;

    const labels = ['Weak', 'Fair', 'Good', 'Strong'];
    return { score, label: labels[Math.min(score - 1, 3)] || 'Weak' };
  };

  const pwdStrength = getPasswordStrength(password);

  // Client file pre-check
  const handleFileChange = (e) => {
    const file = e.target.files?.[0];
    if (!file) {
      setDocumentFile(null);
      return;
    }

    const allowedTypes = ['application/pdf', 'image/png', 'image/jpeg', 'image/jpg'];
    if (!allowedTypes.includes(file.type)) {
      setFieldErrors((prev) => ({
        ...prev,
        document: 'Document must be a PDF, PNG, or JPEG file',
      }));
      setDocumentFile(null);
      return;
    }

    const maxBytes = 10 * 1024 * 1024; // 10MB
    if (file.size > maxBytes) {
      setFieldErrors((prev) => ({
        ...prev,
        document: 'File size exceeds maximum allowable limit of 10MB',
      }));
      setDocumentFile(null);
      return;
    }

    setFieldErrors((prev) => {
      const copy = { ...prev };
      delete copy.document;
      return copy;
    });
    setDocumentFile(file);
  };

  const onSubmit = handleSubmit(async () => {
    setFieldErrors({});
    setGlobalError('');

    const errors = {};

    if (!name.trim()) errors.name = 'Name is required';
    if (!email.trim()) errors.email = 'Valid email is required';
    if (password.length < 8) errors.password = 'Password must be at least 8 characters';
    if (password !== confirmPassword) errors.confirmPassword = 'Passwords do not match';
    if (!termsAccepted) errors.terms = 'You must agree to the Terms and Privacy Policy';

    if (role === 'ngo') {
      if (!orgName.trim()) errors.organization_name = 'Organization name is required';
      if (!regNumber.trim()) errors.registration_number = 'Registration number is required';
      if (!addressText.trim()) errors.address_text = 'Physical address is required';
      if (latitude === null || longitude === null) errors.location = 'Location coordinates are required';
    }

    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors);
      return;
    }

    try {
      const payload = {
        name: name.trim(),
        email: email.trim(),
        password,
        role,
        phone: phone.trim() || undefined,
      };

      if (role === 'ngo') {
        payload.organization_name = orgName.trim();
        payload.registration_number = regNumber.trim();
        payload.description = description.trim();
        payload.address_text = addressText.trim();
        payload.latitude = latitude;
        payload.longitude = longitude;
        payload.service_radius_km = Number(serviceRadiusKm) || 25;
      }

      let resultUser;

      if (role === 'ngo' && documentFile) {
        const formData = new FormData();
        Object.entries(payload).forEach(([k, v]) => {
          if (v !== undefined) formData.append(k, v);
        });
        formData.append('document', documentFile);

        const res = await fetch('/api/auth/register', {
          method: 'POST',
          credentials: 'include',
          body: formData,
        });

        const json = await res.json();
        if (!res.ok) {
          throw new Error(json?.error?.message || 'Registration failed');
        }
        resultUser = json.data?.user || json.data;
      } else {
        resultUser = await register(payload);
      }

      if (role === 'ngo') {
        navigate('/ngo', { replace: true });
      } else {
        navigate('/donor', { replace: true });
      }
    } catch (err) {
      if (err.fields && Object.keys(err.fields).length > 0) {
        setFieldErrors(err.fields);
      } else {
        setGlobalError(err.message || 'Registration could not be completed.');
      }
    }
  });

  return (
    <div className="max-w-2xl mx-auto py-8 px-4">
      <div className="bg-slate-900 border border-slate-800 rounded-3xl p-8 sm:p-10 shadow-2xl space-y-8">
        <div className="text-center space-y-2">
          <h1 className="text-2xl sm:text-3xl font-bold text-white tracking-tight">Create ShareSphere Account</h1>
          <p className="text-xs text-slate-400">Join our community giving & relief network</p>
        </div>

        {/* Role Selector Toggle */}
        <div className="grid grid-cols-2 gap-3 p-1.5 bg-slate-950 border border-slate-800 rounded-2xl">
          <button
            type="button"
            onClick={() => setRole('donor')}
            className={`flex items-center justify-center gap-2 py-3 rounded-xl text-xs font-semibold transition-all ${
              role === 'donor'
                ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-950/50'
                : 'text-slate-400 hover:text-white'
            }`}
          >
            <User size={16} />
            <span>Donor (Individual / Surplus)</span>
          </button>

          <button
            type="button"
            onClick={() => setRole('ngo')}
            className={`flex items-center justify-center gap-2 py-3 rounded-xl text-xs font-semibold transition-all ${
              role === 'ngo'
                ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-950/50'
                : 'text-slate-400 hover:text-white'
            }`}
          >
            <Building2 size={16} />
            <span>NGO (Verified Charity)</span>
          </button>
        </div>

        {globalError && (
          <div
            role="alert"
            className="p-4 bg-red-950/40 border border-red-900/60 rounded-xl text-xs text-red-300 flex items-start gap-2.5 animate-in fade-in"
          >
            <AlertCircle size={16} className="shrink-0 mt-0.5 text-red-400" />
            <span>{globalError}</span>
          </div>
        )}

        <form onSubmit={onSubmit} className="space-y-6">
          {/* Section: Basic Account Info */}
          <div className="space-y-4">
            <h3 className="text-xs font-bold uppercase tracking-wider text-emerald-400">
              Account Credentials
            </h3>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <FormField id="signup-name" label="Full Name / Representative" required error={fieldErrors.name}>
                <input
                  type="text"
                  required
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="Jane Doe"
                  className="w-full bg-slate-950 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500"
                />
              </FormField>

              <FormField id="signup-email" label="Email Address" required error={fieldErrors.email}>
                <input
                  type="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="contact@organization.org"
                  className="w-full bg-slate-950 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500"
                />
              </FormField>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <FormField
                id="signup-password"
                label="Password"
                required
                error={fieldErrors.password}
                hint="Minimum 8 characters with numbers or symbols"
              >
                <div>
                  <input
                    type="password"
                    required
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="••••••••••••"
                    className="w-full bg-slate-950 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500"
                  />
                  {password && (
                    <div className="mt-1.5 flex items-center gap-2 text-[11px] text-slate-400">
                      <span>Strength:</span>
                      <span className="font-semibold text-emerald-400">{pwdStrength.label}</span>
                    </div>
                  )}
                </div>
              </FormField>

              <FormField
                id="signup-confirm-password"
                label="Confirm Password"
                required
                error={fieldErrors.confirmPassword}
              >
                <input
                  type="password"
                  required
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  placeholder="••••••••••••"
                  className="w-full bg-slate-950 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500"
                />
              </FormField>
            </div>

            <FormField id="signup-phone" label="Contact Phone" error={fieldErrors.phone} hint="Optional phone number">
              <input
                type="tel"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                placeholder="+1 555-0199"
                className="w-full bg-slate-950 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500"
              />
            </FormField>
          </div>

          {/* Section: NGO Organization Specific Fields */}
          {role === 'ngo' && (
            <div className="space-y-4 pt-4 border-t border-slate-800">
              <h3 className="text-xs font-bold uppercase tracking-wider text-emerald-400">
                Organization & Registration Details
              </h3>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <FormField
                  id="signup-org-name"
                  label="Legal Organization Name"
                  required
                  error={fieldErrors.organization_name}
                >
                  <input
                    type="text"
                    required
                    value={orgName}
                    onChange={(e) => setOrgName(e.target.value)}
                    placeholder="Community Relief Foundation"
                    className="w-full bg-slate-950 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500"
                  />
                </FormField>

                <FormField
                  id="signup-reg-number"
                  label="Government Registration / Tax ID"
                  required
                  error={fieldErrors.registration_number}
                >
                  <input
                    type="text"
                    required
                    value={regNumber}
                    onChange={(e) => setRegNumber(e.target.value)}
                    placeholder="e.g. 501(c)(3) ID / REG-12345"
                    className="w-full bg-slate-950 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500"
                  />
                </FormField>
              </div>

              <FormField
                id="signup-description"
                label="Mission & Activities"
                hint="Brief description of the causes and communities your non-profit serves"
                error={fieldErrors.description}
              >
                <textarea
                  rows={3}
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  placeholder="We provide educational supplies and emergency clothing to local youth shelters..."
                  className="w-full bg-slate-950 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500"
                />
              </FormField>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <FormField
                  id="signup-address"
                  label="Official Physical Address"
                  required
                  error={fieldErrors.address_text}
                >
                  <input
                    type="text"
                    required
                    value={addressText}
                    onChange={(e) => setAddressText(e.target.value)}
                    placeholder="123 Charity Lane, Suite 100"
                    className="w-full bg-slate-950 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500"
                  />
                </FormField>

                <FormField
                  id="signup-radius"
                  label="Service Radius (km)"
                  required
                  hint="Maximum distance for item collection"
                  error={fieldErrors.service_radius_km}
                >
                  <input
                    type="number"
                    min={1}
                    max={200}
                    value={serviceRadiusKm}
                    onChange={(e) => setServiceRadiusKm(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500"
                  />
                </FormField>
              </div>

              {/* Interactive Location Picker */}
              <LocationPicker
                latitude={latitude}
                longitude={longitude}
                onChange={({ latitude: lat, longitude: lng }) => {
                  setLatitude(lat);
                  setLongitude(lng);
                }}
                label="Set Organization Base Coordinates"
                required
                error={fieldErrors.location}
              />

              {/* Document Upload with client pre-check */}
              <FormField
                id="signup-document"
                label="Upload Verification Document"
                hint="Upload official registration certificate or 501(c)(3) determination letter (PDF, PNG, JPEG up to 10MB)"
                error={fieldErrors.document}
              >
                <div className="flex items-center gap-3">
                  <label className="cursor-pointer inline-flex items-center gap-2 px-4 py-2.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-xl text-xs font-medium text-slate-200 transition-colors">
                    <Upload size={14} />
                    <span>Choose Document</span>
                    <input
                      type="file"
                      accept=".pdf,.png,.jpg,.jpeg"
                      onChange={handleFileChange}
                      className="sr-only"
                    />
                  </label>
                  <span className="text-xs text-slate-400 truncate max-w-xs">
                    {documentFile ? documentFile.name : 'No file selected'}
                  </span>
                </div>
              </FormField>
            </div>
          )}

          {/* Terms & Conditions Checkbox */}
          <div className="pt-2">
            <label className="flex items-start gap-3 cursor-pointer">
              <input
                type="checkbox"
                required
                checked={termsAccepted}
                onChange={(e) => setTermsAccepted(e.target.checked)}
                className="mt-1 w-4 h-4 text-emerald-600 bg-slate-950 border-slate-700 rounded focus:ring-emerald-500 focus:ring-2"
              />
              <span className="text-xs text-slate-400 leading-relaxed">
                I agree to the ShareSphere Community Guidelines, Safety Standards, and Privacy Terms. I understand that all non-profit claims are subject to administrative verification.
              </span>
            </label>
            {fieldErrors.terms && (
              <p className="text-xs text-red-400 mt-1">{fieldErrors.terms}</p>
            )}
          </div>

          <button
            type="submit"
            disabled={isSubmitting}
            className="w-full py-3.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white font-semibold rounded-xl text-sm shadow-xl shadow-emerald-950/50 transition-all flex items-center justify-center gap-2"
          >
            <CheckCircle2 size={16} />
            <span>{isSubmitting ? 'Registering Account...' : 'Complete Registration'}</span>
          </button>
        </form>

        <div className="text-center pt-4 border-t border-slate-800/80 text-xs text-slate-400">
          Already have an account?{' '}
          <Link to="/login" className="text-emerald-400 font-semibold hover:underline">
            Sign in
          </Link>
        </div>
      </div>
    </div>
  );
}
