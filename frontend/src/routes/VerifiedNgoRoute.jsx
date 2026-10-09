import React from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { LoadingState } from '../components/LoadingState';
import { Clock, AlertTriangle, XCircle, FileText } from 'lucide-react';

export function VerifiedNgoRoute({ children }) {
  const { user, loading } = useAuth();

  if (loading) {
    return <LoadingState message="Verifying NGO credentials..." />;
  }

  if (!user || user.role !== 'ngo') {
    return children;
  }

  const status = user.ngo?.verification_status || 'pending';

  if (status === 'verified') {
    return children;
  }

  const renderStatusDetails = () => {
    switch (status) {
      case 'rejected':
        return {
          icon: <XCircle className="w-12 h-12 text-red-400" />,
          title: 'NGO Verification Rejected',
          desc: 'Your application was rejected by the administration. Please review the reason below or contact support.',
          note: user.ngo?.review_note,
        };
      case 'correction_requested':
        return {
          icon: <AlertTriangle className="w-12 h-12 text-amber-400" />,
          title: 'Corrections Requested',
          desc: 'The administrator requested corrections or updated registration documents before approving your account.',
          note: user.ngo?.review_note,
        };
      case 'pending':
      default:
        return {
          icon: <Clock className="w-12 h-12 text-emerald-400" />,
          title: 'NGO Verification In Progress',
          desc: 'Your organization application is currently under administrative review. Once verified, you will be able to post requirements and request surplus goods.',
          note: user.ngo?.review_note,
        };
    }
  };

  const details = renderStatusDetails();

  return (
    <div className="min-h-[70vh] flex items-center justify-center p-6">
      <div className="max-w-lg w-full bg-slate-900 border border-slate-800 rounded-3xl p-8 text-center shadow-2xl">
        <div className="w-20 h-20 bg-slate-800/80 rounded-2xl flex items-center justify-center mx-auto mb-6">
          {details.icon}
        </div>
        <h2 className="text-2xl font-bold text-white mb-3">{details.title}</h2>
        <p className="text-slate-400 text-sm leading-relaxed mb-6">
          {details.desc}
        </p>

        {details.note && (
          <div className="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-4 text-left mb-6">
            <span className="text-xs uppercase font-semibold tracking-wider text-slate-400 block mb-1">
              Admin Review Note:
            </span>
            <p className="text-sm text-slate-200">{details.note}</p>
          </div>
        )}

        <div className="flex flex-col sm:flex-row gap-3 justify-center">
          <Link
            to="/profile"
            className="inline-flex items-center justify-center gap-2 px-6 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-medium rounded-xl text-sm transition-all"
          >
            <FileText size={16} />
            View Profile & Documents
          </Link>
          <Link
            to="/"
            className="inline-flex items-center justify-center px-6 py-3 bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium rounded-xl text-sm transition-all"
          >
            Return to Home
          </Link>
        </div>
      </div>
    </div>
  );
}
