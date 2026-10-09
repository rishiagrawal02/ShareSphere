import React from 'react';
import {
  CheckCircle2,
  Clock,
  AlertCircle,
  XCircle,
  AlertTriangle,
  PackageCheck,
  Send,
  Lock,
  Archive,
  Ban,
  Sparkles,
} from 'lucide-react';

const STATUS_CONFIGS = {
  // Common / General
  active: {
    label: 'Active',
    icon: CheckCircle2,
    className: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
  },
  draft: {
    label: 'Draft',
    icon: Clock,
    className: 'bg-slate-500/10 text-slate-400 border-slate-500/20',
  },
  pending: {
    label: 'Pending',
    icon: Clock,
    className: 'bg-amber-500/10 text-amber-400 border-amber-500/20',
  },
  accepted: {
    label: 'Accepted',
    icon: CheckCircle2,
    className: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
  },
  rejected: {
    label: 'Rejected',
    icon: XCircle,
    className: 'bg-red-500/10 text-red-400 border-red-500/20',
  },
  cancelled: {
    label: 'Cancelled',
    icon: Ban,
    className: 'bg-slate-500/10 text-slate-400 border-slate-500/20',
  },
  expired: {
    label: 'Expired',
    icon: Clock,
    className: 'bg-slate-500/10 text-slate-400 border-slate-500/20',
  },
  closed: {
    label: 'Closed',
    icon: Archive,
    className: 'bg-slate-500/10 text-slate-400 border-slate-500/20',
  },
  removed: {
    label: 'Removed',
    icon: XCircle,
    className: 'bg-red-500/10 text-red-400 border-red-500/20',
  },
  suspended: {
    label: 'Suspended',
    icon: Lock,
    className: 'bg-red-500/10 text-red-400 border-red-500/20',
  },

  // Allocations & Donations
  partially_allocated: {
    label: 'Partially Allocated',
    icon: Sparkles,
    className: 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20',
  },
  fully_allocated: {
    label: 'Fully Allocated',
    icon: PackageCheck,
    className: 'bg-blue-500/10 text-blue-400 border-blue-500/20',
  },
  completed: {
    label: 'Completed',
    icon: CheckCircle2,
    className: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
  },
  partially_fulfilled: {
    label: 'Partially Fulfilled',
    icon: Sparkles,
    className: 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20',
  },
  fulfilled: {
    label: 'Fulfilled',
    icon: PackageCheck,
    className: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
  },
  reserved: {
    label: 'Reserved',
    icon: Clock,
    className: 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
  },
  confirmed: {
    label: 'Confirmed',
    icon: CheckCircle2,
    className: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
  },

  // NGO Verifications
  verified: {
    label: 'Verified NGO',
    icon: CheckCircle2,
    className: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
  },
  correction_requested: {
    label: 'Correction Requested',
    icon: AlertTriangle,
    className: 'bg-amber-500/10 text-amber-400 border-amber-500/20',
  },

  // Pickups
  proposed: {
    label: 'Time Proposed',
    icon: Send,
    className: 'bg-blue-500/10 text-blue-400 border-blue-500/20',
  },
  scheduled: {
    label: 'Pickup Scheduled',
    icon: Clock,
    className: 'bg-purple-500/10 text-purple-400 border-purple-500/20',
  },
  otp_issued: {
    label: 'Code Issued',
    icon: Lock,
    className: 'bg-amber-500/10 text-amber-400 border-amber-500/20',
  },
  collected: {
    label: 'Items Collected',
    icon: PackageCheck,
    className: 'bg-teal-500/10 text-teal-400 border-teal-500/20',
  },
};

export function StatusBadge({ status, size = 'md' }) {
  const normalizedKey = (status || '').toLowerCase().replace(/\s+/g, '_');
  const config = STATUS_CONFIGS[normalizedKey] || {
    label: status || 'Unknown',
    icon: AlertCircle,
    className: 'bg-slate-500/10 text-slate-400 border-slate-500/20',
  };

  const IconComponent = config.icon;
  const isSmall = size === 'sm';

  return (
    <span
      className={`inline-flex items-center gap-1.5 font-medium rounded-full border ${config.className} ${
        isSmall ? 'text-xs px-2.5 py-0.5' : 'text-xs px-3 py-1'
      }`}
    >
      <IconComponent size={isSmall ? 12 : 14} aria-hidden="true" />
      <span>{config.label}</span>
    </span>
  );
}
