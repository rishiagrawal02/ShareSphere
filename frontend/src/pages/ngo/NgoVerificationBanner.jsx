import React from 'react';
import {
  CheckCircle2,
  Clock,
  AlertTriangle,
  XCircle,
  Lock,
  ShieldCheck,
} from 'lucide-react';

const BANNER_CONFIGS = {
  pending: {
    icon: Clock,
    title: 'Verification Pending',
    message:
      "Your application is under review by our admin team. You'll be notified once a decision is made — this usually takes 1–3 business days.",
    className: 'bg-amber-500/10 border-amber-500/30 text-amber-300',
    iconClass: 'text-amber-400',
  },
  verified: {
    icon: CheckCircle2,
    title: 'Verified NGO',
    message:
      'Your organisation is fully verified. You can post requirements, browse matched donations, and coordinate pickups.',
    className: 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300',
    iconClass: 'text-emerald-400',
  },
  rejected: {
    icon: XCircle,
    title: 'Verification Rejected',
    message:
      "Your verification was rejected. Please review the admin's note below, correct your organisation details in your profile, and contact support to re-apply.",
    className: 'bg-red-500/10 border-red-500/30 text-red-300',
    iconClass: 'text-red-400',
  },
  suspended: {
    icon: Lock,
    title: 'Account Suspended',
    message:
      'Your NGO account has been suspended. All requirements and active requests are paused. Contact our support team to appeal.',
    className: 'bg-red-500/10 border-red-500/30 text-red-300',
    iconClass: 'text-red-400',
  },
  correction_requested: {
    icon: AlertTriangle,
    title: 'Correction Required',
    message:
      'The admin has requested a correction to your application. Please update your profile with the information noted below and re-submit.',
    className: 'bg-amber-500/10 border-amber-500/30 text-amber-300',
    iconClass: 'text-amber-400',
  },
};

export function NgoVerificationBanner({ status, adminNote }) {
  const normalised = (status || 'pending').toLowerCase().replace(/\s+/g, '_');
  const config = BANNER_CONFIGS[normalised] || BANNER_CONFIGS.pending;
  const Icon = config.icon;

  return (
    <div
      role="status"
      aria-live="polite"
      className={`flex items-start gap-4 rounded-2xl border p-5 ${config.className}`}
    >
      <div className={`mt-0.5 shrink-0 ${config.iconClass}`}>
        <Icon size={22} aria-hidden="true" />
      </div>
      <div className="flex-1 min-w-0">
        <h3 className="text-sm font-semibold text-white mb-1">{config.title}</h3>
        <p className="text-xs leading-relaxed opacity-90">{config.message}</p>
        {adminNote && (
          <div className="mt-3 rounded-xl bg-black/20 border border-white/10 px-4 py-2.5">
            <p className="text-xs font-semibold text-white/70 uppercase tracking-wide mb-1">
              Admin Note
            </p>
            <p className="text-xs text-white/80 leading-relaxed">{adminNote}</p>
          </div>
        )}
      </div>
      {normalised === 'verified' && (
        <div className="hidden sm:flex shrink-0 items-center gap-1.5 text-emerald-400">
          <ShieldCheck size={18} aria-hidden="true" />
          <span className="text-xs font-semibold">Verified</span>
        </div>
      )}
    </div>
  );
}
