import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { LoadingState } from '../components/LoadingState';
import { ShieldAlert } from 'lucide-react';

export function RoleRoute({ roles = [], children }) {
  const { user, loading } = useAuth();
  const location = useLocation();

  if (loading) {
    return <LoadingState message="Checking authorization..." />;
  }

  if (!user) {
    const nextUrl = location.pathname + location.search;
    return <Navigate to={`/login?next=${encodeURIComponent(nextUrl)}`} replace />;
  }

  if (roles.length > 0 && !roles.includes(user.role)) {
    return (
      <div className="min-h-[60vh] flex items-center justify-center p-6">
        <div className="max-w-md w-full bg-slate-900 border border-red-500/30 rounded-2xl p-8 text-center shadow-xl">
          <div className="w-16 h-16 bg-red-500/10 text-red-400 rounded-full flex items-center justify-center mx-auto mb-4">
            <ShieldAlert size={32} />
          </div>
          <h2 className="text-xl font-bold text-white mb-2">Access Restricted</h2>
          <p className="text-slate-400 text-sm mb-6">
            Your account role ({user.role}) does not have permission to view this page.
          </p>
          <a
            href="/"
            className="inline-block px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-medium rounded-xl text-sm transition-all"
          >
            Return to Home
          </a>
        </div>
      </div>
    );
  }

  return children;
}
