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

const STATUS_MAP = {
  active:               { label: 'Active',              cls: 'ss-badge-active',    icon: CheckCircle2 },
  draft:                { label: 'Draft',               cls: 'ss-badge-cancelled', icon: Clock },
  pending:              { label: 'Pending',             cls: 'ss-badge-pending',   icon: Clock },
  accepted:             { label: 'Accepted',            cls: 'ss-badge-accepted',  icon: CheckCircle2 },
  rejected:             { label: 'Rejected',            cls: 'ss-badge-rejected',  icon: XCircle },
  cancelled:            { label: 'Cancelled',           cls: 'ss-badge-cancelled', icon: Ban },
  expired:              { label: 'Expired',             cls: 'ss-badge-cancelled', icon: Clock },
  closed:               { label: 'Closed',              cls: 'ss-badge-cancelled', icon: Archive },
  removed:              { label: 'Removed',             cls: 'ss-badge-rejected',  icon: XCircle },
  suspended:            { label: 'Suspended',           cls: 'ss-badge-rejected',  icon: Lock },
  partially_allocated:  { label: 'Partly Allocated',    cls: 'ss-badge-info',      icon: Sparkles },
  fully_allocated:      { label: 'Fully Allocated',     cls: 'ss-badge-info',      icon: PackageCheck },
  completed:            { label: 'Completed',           cls: 'ss-badge-completed', icon: CheckCircle2 },
  partially_fulfilled:  { label: 'Partly Fulfilled',    cls: 'ss-badge-info',      icon: Sparkles },
  fulfilled:            { label: 'Fulfilled',           cls: 'ss-badge-completed', icon: PackageCheck },
  reserved:             { label: 'Reserved',            cls: 'ss-badge-scheduled', icon: Clock },
  confirmed:            { label: 'Confirmed',           cls: 'ss-badge-accepted',  icon: CheckCircle2 },
  verified:             { label: 'Verified NGO',        cls: 'ss-badge-verified',  icon: CheckCircle2 },
  correction_requested: { label: 'Correction Needed',   cls: 'ss-badge-pending',   icon: AlertTriangle },
  proposed:             { label: 'Time Proposed',       cls: 'ss-badge-info',      icon: Send },
  scheduled:            { label: 'Pickup Scheduled',    cls: 'ss-badge-scheduled', icon: Clock },
  otp_issued:           { label: 'Code Issued',         cls: 'ss-badge-pending',   icon: Lock },
  collected:            { label: 'Items Collected',     cls: 'ss-badge-completed', icon: PackageCheck },
  requested:            { label: 'Requested',           cls: 'ss-badge-requested', icon: Clock },
};

export function StatusBadge({ status, size = 'md' }) {
  const key = (status || '').toLowerCase().replace(/\s+/g, '_');
  const config = STATUS_MAP[key] || {
    label: status || 'Unknown',
    cls: 'ss-badge-cancelled',
    icon: AlertCircle,
  };
  const Icon = config.icon;
  const iconSize = size === 'sm' ? 11 : 13;

  return (
    <span className={`ss-badge ${config.cls}`}>
      <Icon size={iconSize} aria-hidden="true" />
      {config.label}
    </span>
  );
}
