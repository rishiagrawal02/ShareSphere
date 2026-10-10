import React, { useState, useEffect, useCallback } from 'react';
import { useSearchParams } from 'react-router-dom';
import { api, ApiError } from '../../api/client';
import { useAuth } from '../../auth/AuthContext';
import { PageHeader } from '../../components/PageHeader';
import { StatusBadge } from '../../components/StatusBadge';
import { EmptyState } from '../../components/EmptyState';
import { LoadingState } from '../../components/LoadingState';
import { ErrorState } from '../../components/ErrorState';
import { Pagination } from '../../components/Pagination';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { useToast } from '../../components/Toast';
import { formatDate } from '../../utils/date';
import {
  Users as UsersIcon,
  Search,
  ShieldAlert,
  ShieldCheck,
  Filter,
  UserX,
  UserCheck,
  RefreshCw,
} from 'lucide-react';

const PAGE_SIZE = 15;

export function Users() {
  const { user: currentAdmin } = useAuth();
  const { showSuccess, showError } = useToast();
  const [searchParams, setSearchParams] = useSearchParams();

  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);

  // Filters
  const currentRole = searchParams.get('role') || '';
  const currentStatus = searchParams.get('status') || '';
  const currentSearch = searchParams.get('q') || '';
  const [searchInput, setSearchInput] = useState(currentSearch);

  // Suspend / Reinstate dialog state
  const [targetUser, setTargetUser] = useState(null);
  const [actionType, setActionType] = useState('suspend'); // 'suspend' | 'reinstate'
  const [reason, setReason] = useState('');
  const [cascade, setCascade] = useState(false);
  const [dialogError, setDialogError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const loadUsers = useCallback(
    async (pg = 1, role = currentRole, status = currentStatus, query = currentSearch) => {
      setLoading(true);
      setError(null);
      try {
        let url = `/api/admin/users?page=${pg}&limit=${PAGE_SIZE}`;
        if (role) url += `&role=${encodeURIComponent(role)}`;
        if (status) url += `&status=${encodeURIComponent(status)}`;
        if (query) url += `&q=${encodeURIComponent(query)}`;

        const res = await api.get(url);
        setUsers(res?.data ?? []);
        setTotalPages(res?.meta?.pages ?? res?.meta?.total_pages ?? 1);
        setPage(pg);
      } catch (err) {
        setError(
          err instanceof ApiError ? err : new ApiError(500, 'LOAD_FAILED', 'Could not load users directory.')
        );
      } finally {
        setLoading(false);
      }
    },
    [currentRole, currentStatus, currentSearch]
  );

  useEffect(() => {
    loadUsers(1, currentRole, currentStatus, currentSearch);
  }, [loadUsers, currentRole, currentStatus, currentSearch]);

  const updateFilters = (key, val) => {
    const params = new URLSearchParams(searchParams);
    if (val) params.set(key, val);
    else params.delete(key);
    params.delete('page');
    setSearchParams(params);
  };

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    updateFilters('q', searchInput.trim());
  };

  const handleOpenStatusModal = (userItem, type) => {
    setTargetUser(userItem);
    setActionType(type);
    setReason('');
    setCascade(false);
    setDialogError('');
  };

  const handleConfirmStatusChange = async (e) => {
    e.preventDefault();
    if (actionType === 'suspend' && !reason.trim()) {
      setDialogError('A suspension reason is mandatory.');
      return;
    }

    setSubmitting(true);
    setDialogError('');
    try {
      const newStatus = actionType === 'suspend' ? 'suspended' : 'active';
      const payload = {
        status: newStatus,
        reason: reason.trim() || undefined,
        cascade,
      };

      await api.patch(`/api/admin/users/${targetUser.id}/status`, payload);
      showSuccess(
        `User ${targetUser.name || targetUser.email} has been ${newStatus}.`
      );
      setUsers((prev) =>
        prev.map((u) =>
          u.id === targetUser.id ? { ...u, account_status: newStatus, status: newStatus } : u
        )
      );
      setTargetUser(null);
    } catch (err) {
      setDialogError(err.message || 'Could not update user status.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="space-y-6">
      <PageHeader
        title="User Directory & Access Control"
        subtitle="Manage donors, NGO delegates, and administrators. Enforce account status policies and inspect user records."
        action={
          <button
            type="button"
            onClick={() => loadUsers(page, currentRole, currentStatus, currentSearch)}
            aria-label="Refresh users list"
            className="flex items-center gap-1.5 px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs border border-slate-700 transition-all"
          >
            <RefreshCw size={14} />
          </button>
        }
      />

      {/* Filter Row */}
      <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
        {/* Search */}
        <form onSubmit={handleSearchSubmit} className="flex-1 flex items-center gap-2">
          <div className="relative flex-1">
            <Search size={16} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500" />
            <input
              type="text"
              id="search-users"
              placeholder="Search by user name or email..."
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              className="w-full bg-slate-900 border border-slate-800 rounded-xl pl-10 pr-4 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
            />
          </div>
          <button
            type="submit"
            className="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-semibold border border-slate-700 transition-all"
          >
            Search
          </button>
        </form>

        {/* Role Filter */}
        <select
          aria-label="Filter by role"
          value={currentRole}
          onChange={(e) => updateFilters('role', e.target.value)}
          className="bg-slate-900 border border-slate-800 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-emerald-500"
        >
          <option value="">All Roles</option>
          <option value="donor">Donors</option>
          <option value="ngo">NGO Delegates</option>
          <option value="admin">Administrators</option>
        </select>

        {/* Status Filter */}
        <select
          aria-label="Filter by account status"
          value={currentStatus}
          onChange={(e) => updateFilters('status', e.target.value)}
          className="bg-slate-900 border border-slate-800 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-emerald-500"
        >
          <option value="">All Statuses</option>
          <option value="active">Active</option>
          <option value="suspended">Suspended</option>
        </select>
      </div>

      {/* Content Table */}
      {loading ? (
        <LoadingState message="Loading users…" />
      ) : error ? (
        <ErrorState error={error} onRetry={() => loadUsers(page, currentRole, currentStatus, currentSearch)} />
      ) : users.length === 0 ? (
        <EmptyState
          icon={UsersIcon}
          title="No users found"
          description="No users matched your current search and filter criteria."
        />
      ) : (
        <div className="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-slate-300">
              <thead className="bg-slate-950/80 text-slate-400 uppercase font-semibold text-[11px] border-b border-slate-800">
                <tr>
                  <th scope="col" className="px-5 py-3.5">User</th>
                  <th scope="col" className="px-5 py-3.5">Role</th>
                  <th scope="col" className="px-5 py-3.5">Account Status</th>
                  <th scope="col" className="px-5 py-3.5">Joined</th>
                  <th scope="col" className="px-5 py-3.5 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/60">
                {users.map((u) => {
                  const isCurrentAdmin = u.id === currentAdmin?.id;
                  const accStatus = u.account_status || u.status || 'active';
                  const isSuspended = accStatus === 'suspended';

                  return (
                    <tr key={u.id} className="hover:bg-slate-800/40 transition-colors">
                      <td className="px-5 py-4">
                        <div>
                          {/* Render strictly as text to prevent stored XSS (T-16.1-04) */}
                          <span className="font-bold text-white text-sm block">
                            {u.name || 'Unnamed User'}
                          </span>
                          <span className="text-[11px] text-slate-500">
                            {u.email} · ID #{u.id}
                          </span>
                        </div>
                      </td>
                      <td className="px-5 py-4">
                        <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-800 text-slate-300 border border-slate-700">
                          {u.role}
                        </span>
                      </td>
                      <td className="px-5 py-4">
                        <StatusBadge status={accStatus} size="sm" />
                      </td>
                      <td className="px-5 py-4 text-slate-400 whitespace-nowrap">
                        {formatDate(u.created_at)}
                      </td>
                      <td className="px-5 py-4 text-right">
                        {/* Playbook §3: Self-suspend button is hidden when u.id === currentAdmin.id */}
                        {isCurrentAdmin ? (
                          <span className="text-[11px] text-slate-500 italic">Current Session</span>
                        ) : isSuspended ? (
                          <button
                            type="button"
                            id={`reinstate-user-${u.id}`}
                            onClick={() => handleOpenStatusModal(u, 'reinstate')}
                            className="inline-flex items-center gap-1 px-3 py-1.5 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 rounded-xl font-medium border border-emerald-500/20 transition-all"
                          >
                            <UserCheck size={13} />
                            <span>Reinstate</span>
                          </button>
                        ) : (
                          <button
                            type="button"
                            id={`suspend-user-${u.id}`}
                            onClick={() => handleOpenStatusModal(u, 'suspend')}
                            className="inline-flex items-center gap-1 px-3 py-1.5 bg-red-500/10 hover:bg-red-500/20 text-red-400 rounded-xl font-medium border border-red-500/20 transition-all"
                          >
                            <UserX size={13} />
                            <span>Suspend</span>
                          </button>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {totalPages > 1 && (
            <div className="p-4 border-t border-slate-800">
              <Pagination
                page={page}
                totalPages={totalPages}
                onPageChange={(p) => loadUsers(p, currentRole, currentStatus, currentSearch)}
              />
            </div>
          )}
        </div>
      )}

      {/* Suspend / Reinstate Modal */}
      {targetUser && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm animate-in fade-in duration-150">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 space-y-5 shadow-2xl">
            <h3 className="text-base font-bold text-white flex items-center gap-2">
              {actionType === 'suspend' ? (
                <>
                  <UserX size={18} className="text-red-400" />
                  <span>Suspend Account: {targetUser.name || targetUser.email}</span>
                </>
              ) : (
                <>
                  <UserCheck size={18} className="text-emerald-400" />
                  <span>Reinstate Account: {targetUser.name || targetUser.email}</span>
                </>
              )}
            </h3>

            <p className="text-xs text-slate-400 leading-relaxed">
              {actionType === 'suspend'
                ? 'Suspended users will be immediately revoked from active sessions and blocked from performing actions.'
                : 'Reinstating this account will restore full platform permissions for this user.'}
            </p>

            <form onSubmit={handleConfirmStatusChange} className="space-y-4">
              {actionType === 'suspend' && (
                <>
                  <div>
                    <label htmlFor="suspend-reason" className="block text-xs font-semibold text-slate-300 mb-1">
                      Suspension Reason (Required)
                    </label>
                    <textarea
                      id="suspend-reason"
                      rows={3}
                      required
                      value={reason}
                      onChange={(e) => {
                        setReason(e.target.value);
                        setDialogError('');
                      }}
                      placeholder="e.g. Terms violation, fraudulent items listed, unverified activity..."
                      className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-red-500"
                    />
                  </div>

                  <label className="flex items-center gap-2 text-xs text-slate-300 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={cascade}
                      onChange={(e) => setCascade(e.target.checked)}
                      className="rounded border-slate-700 text-red-500"
                    />
                    <span>Also cancel active donation listings / requirements (cascade)</span>
                  </label>
                </>
              )}

              {dialogError && (
                <p className="text-xs text-red-400 font-medium" role="alert">
                  {dialogError}
                </p>
              )}

              <div className="flex items-center justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setTargetUser(null)}
                  className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium transition-all"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  id="confirm-user-status-btn"
                  disabled={submitting}
                  className={`px-5 py-2 text-white rounded-xl text-xs font-semibold transition-all ${
                    actionType === 'suspend'
                      ? 'bg-red-600 hover:bg-red-500 disabled:opacity-50'
                      : 'bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50'
                  }`}
                >
                  {submitting
                    ? 'Updating…'
                    : actionType === 'suspend'
                    ? 'Confirm Suspension'
                    : 'Reinstate User'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
