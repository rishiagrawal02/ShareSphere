import React, { useState, useRef } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../../auth/AuthContext';
import { api, ApiError } from '../../api/client';
import { FormField } from '../../components/FormField';
import { LocationPicker } from '../../components/LocationPicker';
import { useSubmitOnce } from '../../components/useSubmitOnce';
import { getErrorMessage } from '../../lib/errorMessages';
import {
  User,
  Building2,
  AlertCircle,
  CheckCircle2,
  Upload,
  Leaf,
} from 'lucide-react';

export function SignupPage() {
  const navigate = useNavigate();
  const location = useLocation();
  const { isSubmitting, handleSubmit } = useSubmitOnce();
  const errorRef = useRef(null);

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
  const [latitude, setLatitude] = useState(18.5204);
  const [longitude, setLongitude] = useState(73.8567);
  const [serviceRadiusKm, setServiceRadiusKm] = useState(25);
  const [documentFile, setDocumentFile] = useState(null);

  // UI state
  const [fieldErrors, setFieldErrors] = useState({});
  const [globalError, setGlobalError] = useState('');
  const [successMessage, setSuccessMessage] = useState('');

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
    if (password.length < 10) errors.password = 'Password must be at least 10 characters';
    if (password !== confirmPassword) errors.confirmPassword = 'Passwords do not match';
    if (!termsAccepted) errors.terms = 'You must agree to the Terms and Conditions';

    if (role === 'ngo') {
      if (!orgName.trim()) errors.organization_name = 'Organization name is required';
      if (!regNumber.trim()) errors.registration_number = 'Registration number is required';
      if (!addressText.trim()) errors.address_text = 'Physical address is required';
      if (!latitude || !longitude) errors.location = 'Organization coordinates must be selected on the map';
    }

    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors);
      // Scroll to top of form to show errors
      errorRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      return;
    }

    try {
      const payload = {
        name: name.trim(),
        email: email.trim(),
        password,
        password_confirmation: confirmPassword,
        accept_terms: true,
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

      // Use raw API directly so we don't auto-login; redirect to login page instead
      if (role === 'ngo' && documentFile) {
        const formData = new FormData();
        Object.entries(payload).forEach(([k, v]) => {
          if (v !== undefined) formData.append(k, String(v));
        });
        formData.append('documents[]', documentFile);

        const res = await fetch('/api/auth/register', {
          method: 'POST',
          credentials: 'include',
          body: formData,
        });

        const json = await res.json();
        if (!res.ok) {
          const fields = json?.error?.fields || {};
          if (fields.password_confirmation) fields.confirmPassword = fields.password_confirmation;
          if (fields.accept_terms) fields.terms = fields.accept_terms;
          if (Object.keys(fields).length > 0) {
            setFieldErrors(fields);
            errorRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
          }
          throw new ApiError(
            res.status,
            json?.error?.code || 'INTERNAL_ERROR',
            json?.error?.message || 'Registration failed',
            fields,
            json?.error?.request_id || null
          );
        }
      } else {
        await api.post('/api/auth/register', payload);
      }

      // ✅ Success — redirect to login with success message
      navigate('/login?registered=1', { replace: true });

    } catch (err) {
      const fields = { ...(err?.fields || {}) };
      if (fields.password_confirmation) fields.confirmPassword = fields.password_confirmation;
      if (fields.accept_terms) fields.terms = fields.accept_terms;

      if (Object.keys(fields).length > 0) {
        setFieldErrors(fields);
      } else {
        setGlobalError(getErrorMessage(err, 'Registration could not be completed. Please try again.'));
      }
      errorRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });

  return (
    <div style={{ maxWidth: 640, margin: '2rem auto', padding: '0 1rem 3rem' }}>
      <div ref={errorRef} className="ss-card" style={{ padding: '2rem', display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
        <div style={{ textAlign: 'center' }}>
          <h1 style={{ fontSize: '1.375rem', fontWeight: 700, color: 'var(--ss-text)', marginBottom: '.25rem' }}>Create an Account</h1>
          <p style={{ fontSize: '.8125rem', color: 'var(--ss-muted)' }}>Join ShareSphere and be a part of the change</p>
        </div>

        {/* Role Selector Toggle */}
        <div className="ss-role-tabs">
          <button type="button" className={`ss-role-tab${role === 'donor' ? ' active' : ''}`} onClick={() => setRole('donor')}>
            <User size={14} /> Donor
          </button>
          <button type="button" className={`ss-role-tab${role === 'ngo' ? ' active' : ''}`} onClick={() => setRole('ngo')}>
            <Building2 size={14} /> NGO
          </button>
          <button type="button" className="ss-role-tab" style={{ opacity: .45, cursor: 'not-allowed' }} disabled>
            Administrator
          </button>
        </div>

        {globalError && (
          <div role="alert" className="ss-alert ss-alert-error">
            <AlertCircle size={15} style={{ flexShrink: 0, marginTop: 1 }} />
            <span>{globalError}</span>
          </div>
        )}

        <form onSubmit={onSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          {/* Section: Basic Account Info */}
          <div style={{ display: 'flex', flexDirection: 'column', gap: '.875rem' }}>
            <h3 className="ss-label" style={{ color: 'var(--ss-green)' }}>Account Credentials</h3>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.875rem' }}>
              <FormField id="signup-name" label="Full Name" required error={fieldErrors.name}>
                <input id="signup-name" type="text" required value={name} onChange={e => setName(e.target.value)} placeholder="Nidhi Agrawal" className="ss-input" />
              </FormField>
              <FormField id="signup-email" label="Email" required error={fieldErrors.email}>
                <input id="signup-email" type="email" required value={email} onChange={e => setEmail(e.target.value)} placeholder="nidhi@example.com" className="ss-input" />
              </FormField>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.875rem' }}>
              <FormField id="signup-password" label="Password" required error={fieldErrors.password} hint="Min. 10 chars">
                <input id="signup-password" type="password" required value={password} onChange={e => setPassword(e.target.value)} placeholder="••••••••••" className="ss-input" />
                {password && (
                  <span style={{ fontSize: '.75rem', color: 'var(--ss-green)', fontWeight: 600, marginTop: '.2rem', display: 'block' }}>Strength: {pwdStrength.label}</span>
                )}
              </FormField>
              <FormField id="signup-confirm-password" label="Confirm Password" required error={fieldErrors.confirmPassword}>
                <input id="signup-confirm-password" type="password" required value={confirmPassword} onChange={e => setConfirmPassword(e.target.value)} placeholder="••••••••••" className="ss-input" />
              </FormField>
            </div>

            <FormField id="signup-phone" label="Phone (optional)" error={fieldErrors.phone}>
              <input id="signup-phone" type="tel" value={phone} onChange={e => setPhone(e.target.value)} placeholder="+91 9876543210" className="ss-input" />
            </FormField>
          </div>

          {/* Section: NGO Organization Specific Fields */}
          {role === 'ngo' && (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '.875rem', paddingTop: '1rem', borderTop: '1px solid var(--ss-border)' }}>
              <h3 className="ss-label" style={{ color: 'var(--ss-green)' }}>Organization Details</h3>

              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.875rem' }}>
                <FormField id="signup-org-name" label="Organization Name" required error={fieldErrors.organization_name}>
                  <input id="signup-org-name" type="text" required value={orgName} onChange={e => setOrgName(e.target.value)} placeholder="Helping Hands NGO" className="ss-input" />
                </FormField>
                <FormField id="signup-reg-number" label="Registration No." required error={fieldErrors.registration_number}>
                  <input id="signup-reg-number" type="text" required value={regNumber} onChange={e => setRegNumber(e.target.value)} placeholder="REG-12345" className="ss-input" />
                </FormField>
              </div>

              <FormField id="signup-description" label="Mission & Activities" hint="Brief description" error={fieldErrors.description}>
                <textarea id="signup-description" rows={3} value={description} onChange={e => setDescription(e.target.value)} placeholder="We provide educational supplies..." className="ss-textarea" />
              </FormField>

              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.875rem' }}>
                <FormField id="signup-address" label="Physical Address" required error={fieldErrors.address_text}>
                  <input id="signup-address" type="text" required value={addressText} onChange={e => setAddressText(e.target.value)} placeholder="123 Main St, Pune" className="ss-input" />
                </FormField>
                <FormField id="signup-radius" label="Service Radius (km)" required hint="Max collection distance" error={fieldErrors.service_radius_km}>
                  <input id="signup-radius" type="number" min={1} max={200} value={serviceRadiusKm} onChange={e => setServiceRadiusKm(e.target.value)} className="ss-input" />
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

              <FormField id="signup-document" label="Verification Document" hint="PDF, PNG or JPEG up to 10MB" error={fieldErrors.document}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '.75rem' }}>
                  <label className="ss-btn ss-btn-ghost ss-btn-sm" style={{ cursor: 'pointer' }}>
                    <Upload size={13} />
                    Choose File
                    <input type="file" accept=".pdf,.png,.jpg,.jpeg" onChange={handleFileChange} style={{ position: 'absolute', opacity: 0, width: 0, height: 0 }} />
                  </label>
                  <span style={{ fontSize: '.75rem', color: 'var(--ss-muted)' }}>
                    {documentFile ? documentFile.name : 'No file selected'}
                  </span>
                </div>
              </FormField>
            </div>
          )}

          {/* Terms & Conditions Checkbox */}
          <div style={{ paddingTop: '.5rem' }}>
            <label style={{ display: 'flex', alignItems: 'flex-start', gap: '.75rem', cursor: 'pointer' }}>
              <input
                type="checkbox"
                required
                checked={termsAccepted}
                onChange={e => setTermsAccepted(e.target.checked)}
                style={{ marginTop: 3, width: 16, height: 16, accentColor: 'var(--ss-green)', flexShrink: 0 }}
              />
              <span style={{ fontSize: '.75rem', color: 'var(--ss-muted)', lineHeight: 1.6 }}>
                I agree to the{' '}
                <a href="#" style={{ color: 'var(--ss-green)', textDecoration: 'none', fontWeight: 600 }}>Terms and Conditions</a>
              </span>
            </label>
            {fieldErrors.terms && <p className="ss-field-error">{fieldErrors.terms}</p>}
          </div>

          <button
            type="submit"
            disabled={isSubmitting}
            className="ss-btn ss-btn-primary"
            style={{ width: '100%', padding: '.6875rem', fontSize: '.9375rem' }}
          >
            {isSubmitting ? (
              <><span className="ss-spinner" style={{ width: 17, height: 17 }} /> Creating Account…</>
            ) : (
              <><CheckCircle2 size={16} /> Sign Up</>
            )}
          </button>
        </form>

        <p style={{ textAlign: 'center', fontSize: '.8125rem', color: 'var(--ss-muted)', paddingTop: '1rem', borderTop: '1px solid var(--ss-border)' }}>
          Already have an account?{' '}
          <Link to="/login" style={{ color: 'var(--ss-green)', fontWeight: 600, textDecoration: 'none' }}>Login</Link>
        </p>
      </div>
    </div>
  );
}
