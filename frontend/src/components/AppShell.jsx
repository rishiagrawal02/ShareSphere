import React, { useState } from 'react';
import { Link, NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { NotificationBell } from './NotificationBell';
import {
  Globe,
  HeartHandshake,
  Package,
  Layers,
  MapPin,
  ClipboardList,
  Users,
  BarChart3,
  ShieldCheck,
  User,
  LogOut,
  Menu,
  X,
  FileText,
  Inbox,
  Calendar,
  Leaf,
  Bell,
  Search,
  Home,
  ChevronRight,
} from 'lucide-react';

function Logo({ size = 24 }) {
  return (
    <div
      style={{ width: size, height: size, background: 'var(--ss-green)', borderRadius: 7, display: 'flex', alignItems: 'center', justifyContent: 'center' }}
    >
      <Leaf size={size * 0.55} color="#fff" />
    </div>
  );
}

function UserAvatar({ name, size = 30 }) {
  const initials = (name || 'U').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
  return (
    <div className="ss-user-avatar" style={{ width: size, height: size, fontSize: size * 0.35 }}>
      {initials}
    </div>
  );
}

export function AppShell({ children }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const [mobileOpen, setMobileOpen] = useState(false);

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const getNavLinks = () => {
    if (!user) return [];

    if (user.role === 'donor') {
      return [
        { to: '/donor', label: 'Dashboard', icon: Home, end: true },
        { to: '/donor/donations/new', label: 'Post Donation', icon: Package },
        { to: '/donor/donations', label: 'My Donations', icon: ClipboardList },
        { to: '/donor/requests', label: 'Requests Received', icon: Inbox },
        { to: '/donor/pickups', label: 'Scheduled Pickup', icon: Calendar },
        { to: '/profile', label: 'Profile', icon: User },
        { to: '/logout', label: 'Logout', icon: LogOut, action: handleLogout },
      ];
    }

    if (user.role === 'ngo') {
      return [
        { to: '/ngo', label: 'Dashboard', icon: Home, end: true },
        { to: '/ngo/requirements/new', label: 'Post Requirement', icon: Package },
        { to: '/ngo/requirements', label: 'My Requirements', icon: ClipboardList },
        { to: '/ngo/matches', label: 'Matched Donations', icon: MapPin },
        { to: '/ngo/pickups', label: 'Scheduled Pickup', icon: Calendar },
        { to: '/profile', label: 'Profile', icon: User },
        { to: '/logout', label: 'Logout', icon: LogOut, action: handleLogout },
      ];
    }

    if (user.role === 'admin') {
      return [
        { to: '/admin', label: 'Dashboard', icon: Home, end: true },
        { to: '/admin/ngos', label: 'Manage NGOs', icon: ShieldCheck },
        { to: '/admin/users', label: 'Manage Users', icon: Users },
        { to: '/admin/categories', label: 'Manage Categories', icon: Layers },
        { to: '/admin/reports', label: 'Reports & Analytics', icon: BarChart3 },
        { to: '/admin/audit-logs', label: 'Audit Logs', icon: FileText },
        { to: '/logout', label: 'Logout', icon: LogOut, action: handleLogout },
      ];
    }

    return [];
  };

  const navLinks = getNavLinks();

  // ─── Public (unauthenticated) layout ─────────────────────────
  if (!user) {
    return (
      <div style={{ minHeight: '100vh', background: 'var(--ss-bg)', display: 'flex', flexDirection: 'column' }}>
        {/* Skip link */}
        <a
          href="#main-content"
          className="ss-btn ss-btn-primary"
          style={{ position: 'absolute', top: -100, left: 8, zIndex: 99, transition: 'top .15s' }}
          onFocus={e => (e.currentTarget.style.top = '8px')}
          onBlur={e => (e.currentTarget.style.top = '-100px')}
        >
          Skip to content
        </a>

        {/* Topbar */}
        <header className="ss-topbar">
          <div className="ss-topbar-inner">
            <Link to="/" className="ss-topbar-logo">
              <div className="ss-topbar-logo-icon"><Leaf size={18} /></div>
              <span className="ss-topbar-logo-text">ShareSphere</span>
            </Link>

            <nav className="ss-topbar-nav" style={{ display: 'flex' }} aria-label="Main navigation">
              {[
                { to: '/', label: 'Home', end: true },
                { to: '/about', label: 'About' },
                { to: '/how-it-works', label: 'How it works' },
                { to: '/impact', label: 'Impact' },
                { to: '/contact', label: 'Contact' },
              ].map(item => (
                <NavLink
                  key={item.to}
                  to={item.to}
                  end={item.end}
                  className={({ isActive }) =>
                    `ss-topbar-nav-link${isActive ? ' active' : ''}`
                  }
                >
                  {item.label}
                </NavLink>
              ))}
            </nav>

            <div style={{ display: 'flex', alignItems: 'center', gap: '.625rem' }}>
              <Link to="/login" className="ss-btn ss-btn-ghost" style={{ padding: '.4rem .875rem' }}>Login</Link>
              <Link to="/signup" className="ss-btn ss-btn-primary" style={{ padding: '.4rem .875rem' }}>Sign Up</Link>
            </div>
          </div>
        </header>

        <main id="main-content" style={{ flex: 1 }}>
          {children}
        </main>

        <footer style={{ background: 'var(--ss-surface)', borderTop: '1px solid var(--ss-border)', padding: '2rem 1.5rem' }}>
          <div style={{ maxWidth: 1200, margin: '0 auto', display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: '1rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '.5rem' }}>
              <Logo size={26} />
              <span style={{ fontWeight: 700, fontSize: '.9rem', color: 'var(--ss-text)' }}>ShareSphere</span>
            </div>
            <p style={{ fontSize: '.75rem', color: 'var(--ss-muted)', margin: 0 }}>
              © {new Date().getFullYear()} ShareSphere. Connecting community surplus with verified NGO needs.
            </p>
            <nav style={{ display: 'flex', gap: '1.25rem' }}>
              {[
                { to: '/about', label: 'About' },
                { to: '/how-it-works', label: 'How It Works' },
                { to: '/impact', label: 'Impact' },
                { to: '/contact', label: 'Contact' },
              ].map(l => (
                <Link key={l.to} to={l.to} style={{ fontSize: '.75rem', color: 'var(--ss-muted)', textDecoration: 'none' }}
                  onMouseEnter={e => e.currentTarget.style.color = 'var(--ss-green)'}
                  onMouseLeave={e => e.currentTarget.style.color = 'var(--ss-muted)'}
                >
                  {l.label}
                </Link>
              ))}
            </nav>
          </div>
        </footer>
      </div>
    );
  }

  // ─── Authenticated sidebar layout ────────────────────────────
  return (
    <div className="ss-layout">
      {/* Skip link */}
      <a
        href="#main-content"
        className="ss-btn ss-btn-primary"
        style={{ position: 'absolute', top: -100, left: 8, zIndex: 99, transition: 'top .15s' }}
        onFocus={e => (e.currentTarget.style.top = '8px')}
        onBlur={e => (e.currentTarget.style.top = '-100px')}
      >
        Skip to content
      </a>

      {/* Mobile overlay */}
      {mobileOpen && (
        <div
          onClick={() => setMobileOpen(false)}
          style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,.35)', zIndex: 40 }}
        />
      )}

      {/* Sidebar */}
      <aside
        className={`ss-sidebar${mobileOpen ? ' mobile-open' : ''}`}
        aria-label="Sidebar navigation"
        id="sidebar"
      >
        {/* Logo */}
        <Link to="/" className="ss-sidebar-logo" onClick={() => setMobileOpen(false)}>
          <div className="ss-sidebar-logo-icon"><Leaf size={16} /></div>
          <span className="ss-sidebar-logo-text">ShareSphere</span>
        </Link>

        {/* Navigation */}
        <nav className="ss-sidebar-nav" aria-label="Dashboard navigation">
          <div className="ss-sidebar-section">
            {navLinks.map(item => {
              const Icon = item.icon;
              if (item.action) {
                return (
                  <button
                    key={item.label}
                    onClick={() => { setMobileOpen(false); item.action(); }}
                    className="ss-nav-link"
                    style={{ width: '100%', border: 'none', cursor: 'pointer', background: 'transparent', textAlign: 'left' }}
                  >
                    <Icon size={16} aria-hidden="true" />
                    <span>{item.label}</span>
                  </button>
                );
              }
              return (
                <NavLink
                  key={item.to}
                  to={item.to}
                  end={item.end}
                  onClick={() => setMobileOpen(false)}
                  className={({ isActive }) => `ss-nav-link${isActive ? ' active' : ''}`}
                >
                  <Icon size={16} aria-hidden="true" />
                  <span>{item.label}</span>
                </NavLink>
              );
            })}
          </div>
        </nav>

        {/* User footer */}
        <div className="ss-sidebar-footer">
          <div className="ss-user-chip">
            <UserAvatar name={user.name} />
            <div style={{ flex: 1, minWidth: 0 }}>
              <div className="ss-user-name" style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                {user.name}
              </div>
              <div className="ss-user-role">{user.role}</div>
            </div>
          </div>
        </div>
      </aside>

      {/* Main content */}
      <div className="ss-content">
        {/* Content topbar */}
        <div className="ss-content-topbar">
          {/* Mobile menu toggle */}
          <button
            type="button"
            onClick={() => setMobileOpen(prev => !prev)}
            aria-label="Toggle menu"
            aria-expanded={mobileOpen}
            aria-controls="sidebar"
            className="ss-btn ss-btn-ghost ss-btn-sm"
            style={{ display: 'none' }}
            id="mobile-menu-btn"
          >
            {mobileOpen ? <X size={18} /> : <Menu size={18} />}
          </button>

          <div style={{ display: 'flex', alignItems: 'center', gap: '.5rem' }}>
            <button
              type="button"
              onClick={() => setMobileOpen(prev => !prev)}
              aria-label="Toggle navigation menu"
              style={{ display: 'flex', alignItems: 'center', gap: '.4rem', padding: '.35rem .6rem', background: 'transparent', border: '1px solid var(--ss-border)', borderRadius: 8, cursor: 'pointer', color: 'var(--ss-muted)' }}
            >
              <Menu size={18} />
            </button>
          </div>

          <div style={{ flex: 1 }} />

          {/* Right side — notifications + user */}
          <div style={{ display: 'flex', alignItems: 'center', gap: '.75rem' }}>
            <NotificationBell />
            <div style={{ display: 'flex', alignItems: 'center', gap: '.5rem' }}>
              <UserAvatar name={user.name} size={32} />
              <div style={{ display: 'flex', flexDirection: 'column' }}>
                <span className="ss-user-name">{user.name}</span>
                <span className="ss-user-role">{user.role}</span>
              </div>
            </div>
            <button
              type="button"
              onClick={handleLogout}
              aria-label="Log out"
              className="ss-btn ss-btn-ghost ss-btn-sm"
              style={{ padding: '.35rem .5rem' }}
              title="Log out"
            >
              <LogOut size={16} />
            </button>
          </div>
        </div>

        {/* Page content */}
        <main id="main-content" className="ss-content-body ss-fade-in">
          {children}
        </main>
      </div>
    </div>
  );
}
