import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  Bell,
  CheckCheck,
  Package,
  Calendar,
  AlertCircle,
  Clock,
  ArrowRight,
  ExternalLink,
} from 'lucide-react';
import { api } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { formatRelativeTime } from '../utils/date';

const POLL_INTERVAL_MS = 60000; // 60s per Spec §15.1

export function NotificationBell() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [notifications, setNotifications] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [isOpen, setIsOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const dropdownRef = useRef(null);
  const pollTimerRef = useRef(null);
  const backoffRef = useRef(1); // Exponential backoff multiplier on failure

  const fetchNotifications = useCallback(async () => {
    if (!user) return;
    try {
      const res = await api.get('/api/notifications?limit=10');
      const items = res?.data ?? [];
      setNotifications(items);
      const unread = items.filter((n) => !n.read_at && !n.is_read).length;
      setUnreadCount(unread);
      backoffRef.current = 1; // Reset backoff on success
    } catch {
      // T-15.1-15: Silent retry with backoff, no error spam
      backoffRef.current = Math.min(backoffRef.current * 2, 8);
    }
  }, [user]);

  // Tab visibility-based polling (Spec §15.1)
  useEffect(() => {
    if (!user) return;

    fetchNotifications();

    const schedulePoll = () => {
      if (pollTimerRef.current) clearTimeout(pollTimerRef.current);
      const delay = POLL_INTERVAL_MS * backoffRef.current;
      pollTimerRef.current = setTimeout(() => {
        if (document.visibilityState === 'visible') {
          fetchNotifications().finally(schedulePoll);
        } else {
          schedulePoll();
        }
      }, delay);
    };

    schedulePoll();

    const handleVisibilityChange = () => {
      if (document.visibilityState === 'visible') {
        fetchNotifications();
      }
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);

    return () => {
      if (pollTimerRef.current) clearTimeout(pollTimerRef.current);
      document.removeEventListener('visibilitychange', handleVisibilityChange);
    };
  }, [fetchNotifications, user]);

  // Close dropdown on click outside or Escape
  useEffect(() => {
    function handleClickOutside(e) {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target)) {
        setIsOpen(false);
      }
    }
    function handleEscape(e) {
      if (e.key === 'Escape') setIsOpen(false);
    }

    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
      document.addEventListener('keydown', handleEscape);
    }
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
      document.removeEventListener('keydown', handleEscape);
    };
  }, [isOpen]);

  const handleMarkAllRead = async () => {
    try {
      await api.post('/api/notifications/read-all');
      setNotifications((prev) =>
        prev.map((n) => ({ ...n, read_at: new Date().toISOString(), is_read: true }))
      );
      setUnreadCount(0);
    } catch {
      // Ignore
    }
  };

  const handleNotificationClick = async (notif) => {
    setIsOpen(false);

    // Optimistically mark as read
    if (!notif.read_at && !notif.is_read) {
      try {
        await api.post(`/api/notifications/${notif.id}/read`);
        setNotifications((prev) =>
          prev.map((n) =>
            n.id === notif.id
              ? { ...n, read_at: new Date().toISOString(), is_read: true }
              : n
          )
        );
        setUnreadCount((c) => Math.max(0, c - 1));
      } catch {
        // Continue navigation
      }
    }

    // Resolve target path
    const refType = notif.ref_type?.toLowerCase() || '';
    const refId = notif.ref_id;

    if (refType === 'pickup' && refId) {
      navigate(`/pickups/${refId}`);
    } else if (refType === 'donation' && refId) {
      navigate(user?.role === 'ngo' ? `/ngo/matches` : `/donor/donations/${refId}`);
    } else if (refType === 'request' || refType === 'donation_request') {
      navigate(user?.role === 'ngo' ? `/ngo/requests` : `/donor/requests`);
    } else {
      navigate('/notifications');
    }
  };

  if (!user) return null;

  return (
    <div className="relative" ref={dropdownRef}>
      {/* Bell Button */}
      <button
        type="button"
        id="notification-bell-btn"
        onClick={() => setIsOpen(!isOpen)}
        aria-label={`Notifications (${unreadCount} unread)`}
        aria-expanded={isOpen}
        aria-haspopup="true"
        className="relative w-9 h-9 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700/60 flex items-center justify-center text-slate-300 hover:text-white transition-colors"
      >
        <Bell size={16} />
        {unreadCount > 0 && (
          <span
            id="notification-badge"
            className="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 bg-emerald-500 text-slate-950 text-[10px] font-bold rounded-full flex items-center justify-center shadow-md animate-pulse"
          >
            {unreadCount > 9 ? '9+' : unreadCount}
          </span>
        )}
      </button>

      {/* Floating Dropdown */}
      {isOpen && (
        <div
          role="region"
          aria-label="Recent notifications"
          className="absolute right-0 mt-2 w-80 sm:w-96 bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl shadow-black/80 z-50 overflow-hidden"
        >
          {/* Header */}
          <div className="px-4 py-3 bg-slate-900/90 border-b border-slate-800 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <span className="font-semibold text-sm text-white">Notifications</span>
              {unreadCount > 0 && (
                <span className="px-2 py-0.5 text-[10px] font-bold bg-emerald-500/20 text-emerald-400 rounded-full">
                  {unreadCount} new
                </span>
              )}
            </div>
            {unreadCount > 0 && (
              <button
                type="button"
                onClick={handleMarkAllRead}
                className="text-xs text-slate-400 hover:text-emerald-400 flex items-center gap-1 transition-colors"
              >
                <CheckCheck size={13} />
                <span>Mark all read</span>
              </button>
            )}
          </div>

          {/* List */}
          <div className="max-h-80 overflow-y-auto divide-y divide-slate-800/60">
            {notifications.length === 0 ? (
              <div className="p-8 text-center text-slate-500 text-xs">
                No notifications right now.
              </div>
            ) : (
              notifications.map((item) => {
                const isUnread = !item.read_at && !item.is_read;
                return (
                  <button
                    key={item.id}
                    type="button"
                    onClick={() => handleNotificationClick(item)}
                    className={`w-full text-left p-3.5 flex items-start gap-3 hover:bg-slate-800/60 transition-colors ${
                      isUnread ? 'bg-slate-850/60' : 'opacity-80'
                    }`}
                  >
                    <div
                      className={`w-2 h-2 rounded-full mt-1.5 shrink-0 ${
                        isUnread ? 'bg-emerald-400 ring-2 ring-emerald-400/30' : 'bg-transparent'
                      }`}
                    />
                    <div className="flex-1 min-w-0">
                      <p className="text-xs font-semibold text-white truncate">{item.title}</p>
                      <p className="text-xs text-slate-400 line-clamp-2 mt-0.5">{item.body}</p>
                      <span className="text-[10px] text-slate-500 mt-1 block">
                        {formatRelativeTime(item.created_at)}
                      </span>
                    </div>
                  </button>
                );
              })
            )}
          </div>

          {/* Footer */}
          <div className="p-2.5 bg-slate-950/60 border-t border-slate-800 text-center">
            <Link
              to="/notifications"
              onClick={() => setIsOpen(false)}
              className="text-xs text-emerald-400 hover:text-emerald-300 font-medium inline-flex items-center gap-1 transition-colors"
            >
              <span>View all notifications</span>
              <ArrowRight size={12} />
            </Link>
          </div>
        </div>
      )}
    </div>
  );
}
