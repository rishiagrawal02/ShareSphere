/**
 * Date and time formatting utilities for ShareSphere.
 * Defaults to Indian Standard Time (Asia/Kolkata) per spec §15.1.
 */

const IST_TIMEZONE = 'Asia/Kolkata';

export function formatDateTime(isoString, options = {}) {
  if (!isoString) return '—';
  try {
    const date = new Date(isoString);
    if (isNaN(date.getTime())) return isoString;

    const defaultOptions = {
      timeZone: IST_TIMEZONE,
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      hour12: true,
      ...options,
    };

    return new Intl.DateTimeFormat('en-IN', defaultOptions).format(date);
  } catch {
    return isoString;
  }
}

export function formatDate(isoString) {
  return formatDateTime(isoString, { hour: undefined, minute: undefined, hour12: undefined });
}

export function formatTime(isoString) {
  return formatDateTime(isoString, { day: undefined, month: undefined, year: undefined });
}

export function formatRelativeTime(isoString) {
  if (!isoString) return '';
  try {
    const date = new Date(isoString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffSec = Math.floor(diffMs / 1000);
    const diffMin = Math.floor(diffSec / 60);
    const diffHour = Math.floor(diffMin / 60);
    const diffDay = Math.floor(diffHour / 24);

    if (diffSec < 60) return 'just now';
    if (diffMin < 60) return `${diffMin}m ago`;
    if (diffHour < 24) return `${diffHour}h ago`;
    if (diffDay < 7) return `${diffDay}d ago`;
    return formatDate(isoString);
  } catch {
    return '';
  }
}
