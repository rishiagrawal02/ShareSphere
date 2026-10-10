import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { api, ApiError } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { PageHeader } from '../components/PageHeader';
import { EmptyState } from '../components/EmptyState';
import { LoadingState } from '../components/LoadingState';
import { ErrorState } from '../components/ErrorState';
import { Pagination } from '../components/Pagination';
import { useToast } from '../components/Toast';
import { formatRelativeTime, formatDateTime } from '../utils/date';
import {
  Bell,
  CheckCheck,
  Check,
  Calendar,
  Package,
  Inbox,
  AlertCircle,
  ExternalLink,
} from 'lucide-react';

const PAGE_SIZE = 15;

export function NotificationsPage() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const { showSuccess, showError } = useToast();

  const [notifications, setNotifications] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [filter, setFilter] = useState('all'); // 'all' | 'unread'
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);

  const loadNotifications = useCallback(
    async (pg = 1, unreadOnly = filter === 'unread') => {
      setLoading(true);
      setError(null);
      try {
        const query = `page=${pg}&limit=${PAGE_SIZE}${unreadOnly ? '&unread=1' : ''}`;
        const res = await api.get(`/api/notifications?${query}`);
        setNotifications(res?.data ?? []);
        setTotalPages(res?.meta?.total_pages ?? 1);
        setPage(pg);
      } catch (err) {
        setError(
          err instanceof ApiError
            ? err
            : new ApiError(500, 'LOAD_FAILED', 'Could not load notifications.')
        );
      } finally {
        setLoading(false);
      }
    },
    [filter]
  );

  useEffect(() => {
    loadNotifications(1, filter === 'unread');
  }, [loadNotifications, filter]);

  const handleMarkRead = async (id, e) => {
    if (e) e.stopPropagation();
    try {
      await api.post(`/api/notifications/${id}/read`);
      setNotifications((prev) =>
        prev.map((n) =>
          n.id === id ? { ...n, read_at: new Date().toISOString(), is_read: true } : n
        )
      );
      showSuccess('Marked as read');
    } catch {
      showError('Failed to mark notification as read');
    }
  };

  const handleMarkAllRead = async () => {
    try {
      await api.post('/api/notifications/read-all');
      setNotifications((prev) =>
        prev.map((n) => ({ ...n, read_at: new Date().toISOString(), is_read: true }))
      );
      showSuccess('All notifications marked as read');
    } catch {
      showError('Failed to mark all as read');
    }
  };

  const handleNavigate = (notif) => {
    if (!notif.read_at && !notif.is_read) {
      handleMarkRead(notif.id);
    }

    const refType = notif.ref_type?.toLowerCase() || '';
    const refId = notif.ref_id;

    if (refType === 'pickup' && refId) {
      navigate(`/pickups/${refId}`);
    } else if (refType === 'donation' && refId) {
      navigate(user?.role === 'ngo' ? `/ngo/matches` : `/donor/donations/${refId}`);
    } else if (refType === 'request' || refType === 'donation_request') {
      navigate(user?.role === 'ngo' ? `/ngo/requests` : `/donor/requests`);
    }
  };

  const unreadCount = notifications.filter((n) => !n.read_at && !n.is_read).length;

  return (
    <div className="space-y-6">
      <PageHeader
        title="Notification Center"
        subtitle="Stay updated on donation requests, pickup proposals, and physical handover status."
        action={
          unreadCount > 0 && (
            <button
              type="button"
              onClick={handleMarkAllRead}
              className="flex items-center gap-1.5 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-medium border border-slate-700 transition-all"
            >
              <CheckCheck size={14} className="text-emerald-400" />
              <span>Mark all as read</span>
            </button>
          )
        }
      />

      {/* Filter Tabs */}
      <div className="flex items-center gap-2">
        <button
          type="button"
          onClick={() => {
            setFilter('all');
            setPage(1);
          }}
          className={`px-4 py-2 rounded-xl text-xs font-semibold border transition-all ${
            filter === 'all'
              ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30'
              : 'bg-slate-900 border-slate-800 text-slate-400 hover:text-white'
          }`}
        >
          All
        </button>
        <button
          type="button"
          onClick={() => {
            setFilter('unread');
            setPage(1);
          }}
          className={`px-4 py-2 rounded-xl text-xs font-semibold border transition-all flex items-center gap-1.5 ${
            filter === 'unread'
              ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30'
              : 'bg-slate-900 border-slate-800 text-slate-400 hover:text-white'
          }`}
        >
          <span>Unread</span>
          {unreadCount > 0 && (
            <span className="w-4 h-4 rounded-full bg-emerald-500 text-slate-950 text-[10px] font-bold flex items-center justify-center">
              {unreadCount}
            </span>
          )}
        </button>
      </div>

      {loading ? (
        <LoadingState message="Loading notifications…" />
      ) : error ? (
        <ErrorState error={error} onRetry={() => loadNotifications(page)} />
      ) : notifications.length === 0 ? (
        <EmptyState
          icon={Bell}
          title={filter === 'unread' ? 'No unread notifications' : 'No notifications yet'}
          description={
            filter === 'unread'
              ? 'You are all caught up! There are no unread notifications.'
              : 'You have no notifications right now. Activity on donations and pickups will appear here.'
          }
        />
      ) : (
        <div className="space-y-3">
          {notifications.map((notif) => {
            const isUnread = !notif.read_at && !notif.is_read;
            const refType = notif.ref_type?.toLowerCase() || '';

            return (
              <div
                key={notif.id}
                onClick={() => handleNavigate(notif)}
                className={`p-4 sm:p-5 rounded-2xl border transition-all cursor-pointer flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 ${
                  isUnread
                    ? 'bg-slate-900/90 border-emerald-500/30 shadow-lg shadow-emerald-950/20'
                    : 'bg-slate-900/50 border-slate-800/80 hover:border-slate-700/80'
                }`}
              >
                <div className="flex items-start gap-3.5 flex-1 min-w-0">
                  <div
                    className={`w-9 h-9 rounded-xl flex items-center justify-center shrink-0 mt-0.5 ${
                      isUnread
                        ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                        : 'bg-slate-800 text-slate-400'
                    }`}
                  >
                    {refType === 'pickup' ? (
                      <Calendar size={18} />
                    ) : refType === 'donation' ? (
                      <Package size={18} />
                    ) : (
                      <Inbox size={18} />
                    )}
                  </div>

                  <div className="space-y-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <h4 className="text-sm font-semibold text-white truncate">{notif.title}</h4>
                      {isUnread && (
                        <span className="w-2 h-2 rounded-full bg-emerald-400 shrink-0" />
                      )}
                    </div>
                    <p className="text-xs text-slate-300 leading-relaxed">{notif.body}</p>
                    <p className="text-[11px] text-slate-500">
                      {formatDateTime(notif.created_at)} ({formatRelativeTime(notif.created_at)})
                    </p>
                  </div>
                </div>

                <div className="flex items-center gap-2 self-end sm:self-center shrink-0">
                  {isUnread && (
                    <button
                      type="button"
                      onClick={(e) => handleMarkRead(notif.id, e)}
                      title="Mark as read"
                      className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs border border-slate-700 flex items-center gap-1 transition-all"
                    >
                      <Check size={13} className="text-emerald-400" />
                      <span>Mark read</span>
                    </button>
                  )}

                  {notif.ref_id && (
                    <span className="text-xs text-emerald-400 font-medium flex items-center gap-1">
                      <span>View</span>
                      <ExternalLink size={12} />
                    </span>
                  )}
                </div>
              </div>
            );
          })}

          {totalPages > 1 && (
            <Pagination
              page={page}
              totalPages={totalPages}
              onPageChange={(p) => loadNotifications(p)}
            />
          )}
        </div>
      )}
    </div>
  );
}
