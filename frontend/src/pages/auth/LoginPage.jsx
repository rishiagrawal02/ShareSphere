import React, { useState } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../../auth/AuthContext';
import { useSubmitOnce } from '../../components/useSubmitOnce';
import { getErrorMessage } from '../../lib/errorMessages';
import { Leaf, Mail, Lock, AlertCircle, CheckCircle2 } from 'lucide-react';

const ROLES = ['Donor', 'NGO', 'Administrator'];

export function LoginPage() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const { isSubmitting, handleSubmit } = useSubmitOnce();

  const searchParams = new URLSearchParams(location.search);
  const nextUrl = searchParams.get('next');
  const justRegistered = searchParams.get('registered') === '1';

  const [activeRole, setActiveRole] = useState(0);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');

  const onSubmit = handleSubmit(async (e) => {
    e.preventDefault();
    setError('');
    if (!email.trim() || !password) {
      setError('Please enter both email and password.');
      return;
    }
    try {
      const user = await login(email.trim(), password);
      if (nextUrl) { navigate(nextUrl, { replace: true }); return; }
      if (user?.role === 'admin') navigate('/admin', { replace: true });
      else if (user?.role === 'ngo') navigate('/ngo', { replace: true });
      else navigate('/donor', { replace: true });
    } catch (err) {
      setError(getErrorMessage(err, 'Invalid email or password.'));
    }
  });

  return (
    <div style={{ minHeight: '90vh', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '2rem 1rem', background: 'var(--ss-bg)' }}>
      <div className="ss-card ss-fade-in" style={{ width: '100%', maxWidth: 420, padding: '2.25rem 2rem' }}>
        {/* Logo & heading */}
        <div style={{ textAlign: 'center', marginBottom: '1.75rem' }}>
          <div style={{ width: 48, height: 48, background: 'var(--ss-green)', borderRadius: 12, display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto .875rem' }}>
            <Leaf size={24} color="#fff" />
          </div>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '.4rem', marginBottom: '.25rem' }}>
            <span style={{ fontSize: '1.0625rem', fontWeight: 700, color: 'var(--ss-green)' }}>ShareSphere</span>
          </div>
          <h1 style={{ fontSize: '1.3125rem', fontWeight: 700, color: 'var(--ss-text)', margin: '0 0 .25rem' }}>Welcome Back</h1>
          <p style={{ fontSize: '.8125rem', color: 'var(--ss-muted)', margin: 0 }}>Login to continue making an impact</p>
        </div>

        {/* Registration success banner */}
        {justRegistered && (
          <div className="ss-alert ss-alert-success" style={{ marginBottom: '1rem' }}>
            <CheckCircle2 size={15} style={{ flexShrink: 0, marginTop: 1 }} />
            <span>Account created successfully! Please log in below.</span>
          </div>
        )}

        {/* Role tabs */}
        <div className="ss-role-tabs" style={{ marginBottom: '1.5rem' }}>
          {ROLES.map((r, i) => (
            <button key={r} type="button" className={`ss-role-tab${activeRole === i ? ' active' : ''}`} onClick={() => setActiveRole(i)}>
              {r}
            </button>
          ))}
        </div>

        {/* Error */}
        {error && (
          <div className="ss-alert ss-alert-error" style={{ marginBottom: '1rem' }}>
            <AlertCircle size={15} style={{ flexShrink: 0, marginTop: 1 }} />
            <span>{error}</span>
          </div>
        )}

        {/* Form */}
        <form onSubmit={onSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <div className="ss-form-group">
            <label htmlFor="login-email" className="ss-form-label">Email Address</label>
            <div className="ss-input-icon">
              <Mail size={15} className="ss-input-icon-el" />
              <input
                id="login-email"
                type="email"
                autoComplete="email"
                required
                value={email}
                onChange={e => setEmail(e.target.value)}
                placeholder="rishi@example.com"
                className="ss-input"
                style={{ paddingLeft: '2.25rem' }}
              />
            </div>
          </div>

          <div className="ss-form-group">
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '.3125rem' }}>
              <label htmlFor="login-password" className="ss-form-label" style={{ margin: 0 }}>Password</label>
              <a href="#" style={{ fontSize: '.75rem', color: 'var(--ss-green)', textDecoration: 'none', fontWeight: 500 }}>Forgot Password?</a>
            </div>
            <div className="ss-input-icon">
              <Lock size={15} className="ss-input-icon-el" />
              <input
                id="login-password"
                type="password"
                autoComplete="current-password"
                required
                value={password}
                onChange={e => setPassword(e.target.value)}
                placeholder="••••••••••••"
                className="ss-input"
                style={{ paddingLeft: '2.25rem' }}
              />
            </div>
          </div>

          <button
            type="submit"
            disabled={isSubmitting}
            className="ss-btn ss-btn-primary"
            style={{ width: '100%', padding: '.6875rem', fontSize: '.9375rem', marginTop: '.25rem' }}
          >
            {isSubmitting ? (
              <><span className="ss-spinner" style={{ width: 17, height: 17 }} /> Logging in…</>
            ) : 'Login'}
          </button>
        </form>

        <p style={{ textAlign: 'center', fontSize: '.8125rem', color: 'var(--ss-muted)', marginTop: '1.25rem' }}>
          Don't have an account?{' '}
          <Link to="/signup" style={{ color: 'var(--ss-green)', fontWeight: 600, textDecoration: 'none' }}>Sign Up</Link>
        </p>
      </div>
    </div>
  );
}
