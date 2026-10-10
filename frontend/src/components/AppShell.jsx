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
  Bell,
  User,
  LogOut,
  Menu,
  X,
  FileText,
  Inbox,
  Calendar,
} from 'lucide-react';

export function AppShell({ children }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const getNavLinks = () => {
    if (!user) {
      return [
        { to: '/', label: 'Home', icon: Globe },
        { to: '/about', label: 'About', icon: HeartHandshake },
        { to: '/how-it-works', label: 'How It Works', icon: Layers },
        { to: '/impact', label: 'Impact', icon: BarChart3 },
        { to: '/contact', label: 'Contact', icon: FileText },
      ];
    }

    if (user.role === 'donor') {
      return [
        { to: '/donor', label: 'Dashboard', icon: BarChart3, end: true },
        { to: '/donor/donations/new', label: 'Donate Items', icon: Package },
        { to: '/donor/donations', label: 'My Donations', icon: ClipboardList },
        { to: '/donor/requests', label: 'Requests', icon: Inbox },
        { to: '/donor/pickups', label: 'Pickups', icon: Calendar },
        { to: '/profile', label: 'Profile', icon: User },
      ];
    }

    if (user.role === 'ngo') {
      return [
        { to: '/ngo', label: 'Dashboard', icon: BarChart3, end: true },
        { to: '/ngo/requirements', label: 'Requirements', icon: ClipboardList },
        { to: '/ngo/matches', label: 'Matched Donations', icon: MapPin },
        { to: '/ngo/requests', label: 'My Requests', icon: Inbox },
        { to: '/ngo/pickups', label: 'Pickups', icon: Package },
        { to: '/profile', label: 'Organization Profile', icon: User },
      ];
    }

    if (user.role === 'admin') {
      return [
        { to: '/admin', label: 'Dashboard', icon: BarChart3, end: true },
        { to: '/admin/ngos', label: 'NGO Verification', icon: ShieldCheck },
        { to: '/admin/users', label: 'User Directory', icon: Users },
        { to: '/admin/categories', label: 'Categories', icon: Layers },
        { to: '/admin/reports', label: 'Reports & Analytics', icon: BarChart3 },
        { to: '/admin/audit-logs', label: 'Audit Logs', icon: FileText },
        { to: '/profile', label: 'Admin Profile', icon: User },
      ];
    }

    return [];
  };

  const navLinks = getNavLinks();

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col font-sans selection:bg-emerald-500 selection:text-white">
      {/* Top Navbar */}
      <header className="sticky top-0 z-40 bg-slate-900/90 backdrop-blur-md border-b border-slate-800/80">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
          {/* Logo */}
          <Link to="/" className="flex items-center gap-2.5 group">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-400 flex items-center justify-center text-white shadow-lg shadow-emerald-950/50 group-hover:scale-105 transition-transform">
              <HeartHandshake size={22} />
            </div>
            <span className="text-xl font-bold tracking-tight bg-gradient-to-r from-white via-slate-100 to-slate-400 bg-clip-text text-transparent">
              ShareSphere
            </span>
          </Link>

          {/* Desktop Navigation */}
          <nav className="hidden md:flex items-center gap-1.5" aria-label="Main Navigation">
            {navLinks.map((item) => {
              const Icon = item.icon;
              return (
                <NavLink
                  key={item.to}
                  to={item.to}
                  end={item.end}
                  className={({ isActive }) =>
                    `flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-medium transition-all ${
                      isActive
                        ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'
                        : 'text-slate-300 hover:text-white hover:bg-slate-800/60'
                    }`
                  }
                >
                  <Icon size={15} />
                  <span>{item.label}</span>
                </NavLink>
              );
            })}
          </nav>

          {/* User Status / Auth CTAs */}
          <div className="flex items-center gap-3">
            {user ? (
              <div className="flex items-center gap-3">
                <NotificationBell />

                <div className="hidden sm:flex flex-col text-right">
                  <span className="text-xs font-semibold text-white leading-tight">{user.name}</span>
                  <span className="text-[10px] text-emerald-400 uppercase font-semibold tracking-wider">
                    {user.role}
                  </span>
                </div>

                <button
                  type="button"
                  onClick={handleLogout}
                  aria-label="Log out"
                  className="w-9 h-9 rounded-xl bg-slate-800/80 hover:bg-red-500/20 hover:text-red-400 border border-slate-700/60 flex items-center justify-center text-slate-400 transition-colors"
                  title="Log out"
                >
                  <LogOut size={16} />
                </button>
              </div>
            ) : (
              <div className="hidden sm:flex items-center gap-2">
                <Link
                  to="/login"
                  className="px-4 py-2 text-xs font-medium text-slate-300 hover:text-white transition-colors"
                >
                  Sign In
                </Link>
                <Link
                  to="/signup"
                  className="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-semibold shadow-lg shadow-emerald-950/40 transition-all"
                >
                  Join ShareSphere
                </Link>
              </div>
            )}

            {/* Mobile Menu Toggle */}
            <button
              type="button"
              onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
              aria-label="Toggle navigation menu"
              className="md:hidden w-9 h-9 rounded-xl bg-slate-800/80 border border-slate-700/60 flex items-center justify-center text-slate-300"
            >
              {mobileMenuOpen ? <X size={18} /> : <Menu size={18} />}
            </button>
          </div>
        </div>

        {/* Mobile Dropdown Drawer */}
        {mobileMenuOpen && (
          <div className="md:hidden bg-slate-900 border-b border-slate-800 px-4 pt-3 pb-5 space-y-2 animate-in slide-in-from-top-2 duration-150">
            {navLinks.map((item) => {
              const Icon = item.icon;
              return (
                <NavLink
                  key={item.to}
                  to={item.to}
                  end={item.end}
                  onClick={() => setMobileMenuOpen(false)}
                  className={({ isActive }) =>
                    `flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-all ${
                      isActive
                        ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'
                        : 'text-slate-300 hover:bg-slate-800'
                    }`
                  }
                >
                  <Icon size={18} />
                  <span>{item.label}</span>
                </NavLink>
              );
            })}

            {!user && (
              <div className="pt-3 border-t border-slate-800 flex flex-col gap-2">
                <Link
                  to="/login"
                  onClick={() => setMobileMenuOpen(false)}
                  className="block text-center py-2.5 text-sm font-medium text-slate-300 hover:bg-slate-800 rounded-xl"
                >
                  Sign In
                </Link>
                <Link
                  to="/signup"
                  onClick={() => setMobileMenuOpen(false)}
                  className="block text-center py-2.5 bg-emerald-600 text-white text-sm font-semibold rounded-xl"
                >
                  Join ShareSphere
                </Link>
              </div>
            )}
          </div>
        )}
      </header>

      {/* Main Content Area */}
      <main className="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {children}
      </main>

      {/* Footer */}
      <footer className="bg-slate-900/60 border-t border-slate-800/60 py-8 text-center text-xs text-slate-500">
        <div className="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-4">
          <p>© {new Date().getFullYear()} ShareSphere. Connecting community surplus with active NGO needs.</p>
          <div className="flex items-center gap-6 text-slate-400">
            <Link to="/about" className="hover:text-emerald-400 transition-colors">About</Link>
            <Link to="/how-it-works" className="hover:text-emerald-400 transition-colors">How It Works</Link>
            <Link to="/impact" className="hover:text-emerald-400 transition-colors">Impact</Link>
            <Link to="/contact" className="hover:text-emerald-400 transition-colors">Contact & Appeals</Link>
          </div>
        </div>
      </footer>
    </div>
  );
}
