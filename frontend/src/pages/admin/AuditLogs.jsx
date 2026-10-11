import React, { useState, useEffect, useCallback } from 'react';
import { useSearchParams } from 'react-router-dom';
import { api, ApiError } from '../../api/client';
import { PageHeader } from '../../components/PageHeader';
import { EmptyState } from '../../components/EmptyState';
import { LoadingState } from '../../components/LoadingState';
import { ErrorState } from '../../components/ErrorState';
import { Pagination } from '../../components/Pagination';
import { formatDate } from '../../utils/date';
import {
  FileText,
  Filter,
  RefreshCw,
  Download,
  ChevronDown,
  ChevronRight,
  Shield,
  Search,
  User,
  Clock,
  Layers,
} from 'lucide-react';

const PAGE_LIMIT = 20;

// Client-side safety filter to guarantee no credentials/secrets are displayed
function sanitizeMetadata(metadata) {
  if (!metadata || typeof metadata !== 'object') return {};
  const sanitized = { ...metadata };
  const sensitiveKeys = ['otp', 'password', 'password_hash', 'token', 'secret', 'key', 'auth_token'];
  for (const key of Object.keys(sanitized)) {
    if (sensitiveKeys.some((s) => key.toLowerCase().includes(s))) {
      delete sanitized[key];
    }
  }
  return sanitized;
}

export function AuditLogs() {
  const [searchParams, setSearchParams] = useSearchParams();

  const [logs, setLogs] = useState([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [expandedRows, setExpandedRows] = useState(new Set());

  // Filter states
  const actionParam = searchParams.get('action') || '';
  const actorIdParam = searchParams.get('actor_id') || '';
  const targetTypeParam = searchParams.get('target_type') || '';
  const fromParam = searchParams.get('from') || '';
  const toParam = searchParams.get('to') || '';
  const pageParam = parseInt(searchParams.get('page') || '1', 10);

  const [actionInput, setActionInput] = useState(actionParam);
  const [actorIdInput, setActorIdInput] = useState(actorIdParam);
  const [targetTypeInput, setTargetTypeInput] = useState(targetTypeParam);
  const [fromInput, setFromInput] = useState(fromParam);
  const [toInput, setToInput] = useState(toParam);

  const loadLogs = useCallback(
    async (page = pageParam) => {
      setLoading(true);
      setError(null);
      try {
        const queryParams = new URLSearchParams();
        queryParams.set('page', String(page));
        queryParams.set('limit', String(PAGE_LIMIT));
        if (actionParam) queryParams.set('action', actionParam);
        if (actorIdParam) queryParams.set('actor_id', actorIdParam);
        if (targetTypeParam) queryParams.set('target_type', targetTypeParam);
        if (fromParam) queryParams.set('from', fromParam);
        if (toParam) queryParams.set('to', toParam);

        const res = await api.get(`/api/admin/audit-logs?${queryParams.toString()}`);
        const items = Array.isArray(res?.data) ? res.data : res?.data?.items || [];
        setLogs(items);
        setTotal(res?.meta?.total ?? res?.data?.total ?? items.length);
      } catch (err) {
        setError(err instanceof ApiError ? err : new ApiError(500, 'INTERNAL_ERROR', 'Failed to load audit logs.'));
      } finally {
        setLoading(false);
      }
    },
    [pageParam, actionParam, actorIdParam, targetTypeParam, fromParam, toParam]
  );

  useEffect(() => {
    loadLogs(pageParam);
  }, [loadLogs, pageParam]);

  const handleApplyFilters = (e) => {
    e.preventDefault();
    const newParams = new URLSearchParams();
    if (actionInput.trim()) newParams.set('action', actionInput.trim());
    if (actorIdInput.trim()) newParams.set('actor_id', actorIdInput.trim());
    if (targetTypeInput.trim()) newParams.set('target_type', targetTypeInput.trim());
    if (fromInput.trim()) newParams.set('from', fromInput.trim());
    if (toInput.trim()) newParams.set('to', toInput.trim());
    newParams.set('page', '1');
    setSearchParams(newParams);
  };

  const handleResetFilters = () => {
    setActionInput('');
    setActorIdInput('');
    setTargetTypeInput('');
    setFromInput('');
    setToInput('');
    setSearchParams(new URLSearchParams());
  };

  const handlePageChange = (newPage) => {
    const newParams = new URLSearchParams(searchParams);
    newParams.set('page', String(newPage));
    setSearchParams(newParams);
  };

  const toggleRowExpansion = (id) => {
    setExpandedRows((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  const totalPages = Math.max(1, Math.ceil(total / PAGE_LIMIT));

  const csvExportUrl = (() => {
    const p = new URLSearchParams();
    p.set('format', 'csv');
    if (actionParam) p.set('action', actionParam);
    if (actorIdParam) p.set('actor_id', actorIdParam);
    if (targetTypeParam) p.set('target_type', targetTypeParam);
    if (fromParam) p.set('from', fromParam);
    if (toParam) p.set('to', toParam);
    return `/api/admin/audit-logs?${p.toString()}`;
  })();

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <PageHeader
          title="Audit Log Viewer"
          description="Track administrative actions, user security events, verification decisions, and moderation records."
        />
        <div className="flex items-center gap-2">
          <a
            href={csvExportUrl}
            download="audit_logs.csv"
            id="audit-export-csv-btn"
            className="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition"
          >
            <Download className="w-4 h-4 text-slate-500" />
            Export CSV
          </a>
          <button
            onClick={() => loadLogs(pageParam)}
            id="audit-refresh-btn"
            title="Refresh logs"
            className="p-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition"
          >
            <RefreshCw className="w-4 h-4 text-slate-500" />
          </button>
        </div>
      </div>

      {/* Filter Toolbar */}
      <form
        onSubmit={handleApplyFilters}
        className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm space-y-4"
        id="audit-filter-form"
      >
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
          <div>
            <label htmlFor="filter-action" className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">
              Action
            </label>
            <input
              id="filter-action"
              type="text"
              placeholder="e.g. user.suspend"
              value={actionInput}
              onChange={(e) => setActionInput(e.target.value)}
              className="w-full text-sm px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
            />
          </div>

          <div>
            <label htmlFor="filter-actor" className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">
              Actor ID
            </label>
            <input
              id="filter-actor"
              type="number"
              placeholder="User ID"
              value={actorIdInput}
              onChange={(e) => setActorIdInput(e.target.value)}
              className="w-full text-sm px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
            />
          </div>

          <div>
            <label htmlFor="filter-target" className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">
              Target Type
            </label>
            <input
              id="filter-target"
              type="text"
              placeholder="e.g. user, ngo, donation"
              value={targetTypeInput}
              onChange={(e) => setTargetTypeInput(e.target.value)}
              className="w-full text-sm px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
            />
          </div>

          <div>
            <label htmlFor="filter-from" className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">
              From Date
            </label>
            <input
              id="filter-from"
              type="date"
              value={fromInput}
              onChange={(e) => setFromInput(e.target.value)}
              className="w-full text-sm px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
            />
          </div>

          <div>
            <label htmlFor="filter-to" className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">
              To Date
            </label>
            <input
              id="filter-to"
              type="date"
              value={toInput}
              onChange={(e) => setToInput(e.target.value)}
              className="w-full text-sm px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
            />
          </div>
        </div>

        <div className="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800">
          <div className="text-xs text-slate-500 dark:text-slate-400">
            {total} total event{total === 1 ? '' : 's'} recorded
          </div>
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={handleResetFilters}
              id="audit-reset-filters-btn"
              className="px-3 py-1.5 text-xs font-medium rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
            >
              Reset
            </button>
            <button
              type="submit"
              id="audit-apply-filters-btn"
              className="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-medium rounded-lg bg-primary-600 text-white hover:bg-primary-700 transition shadow-sm"
            >
              <Filter className="w-3.5 h-3.5" />
              Apply Filters
            </button>
          </div>
        </div>
      </form>

      {/* Main Content */}
      {loading ? (
        <LoadingState message="Loading audit events..." />
      ) : error ? (
        <ErrorState error={error} onRetry={() => loadLogs(pageParam)} />
      ) : logs.length === 0 ? (
        <EmptyState
          icon={Shield}
          title="No audit events found"
          description="There are no audit records matching the specified criteria."
          actionLabel="Clear Filters"
          onAction={handleResetFilters}
        />
      ) : (
        <div className="space-y-4">
          {/* Responsive table container: guarantees horizontal scroll on mobile viewport */}
          <div
            className="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm"
            id="audit-logs-table-container"
            style={{ minWidth: '100%' }}
          >
            <table className="w-full text-left text-sm" role="table" aria-label="Audit Log Entries">
              <thead>
                <tr className="border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                  <th scope="col" className="w-8 px-4 py-3"></th>
                  <th scope="col" className="px-4 py-3">Timestamp</th>
                  <th scope="col" className="px-4 py-3">Action</th>
                  <th scope="col" className="px-4 py-3">Actor</th>
                  <th scope="col" className="px-4 py-3">Target</th>
                  <th scope="col" className="px-4 py-3">Result</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {logs.map((log) => {
                  const isExpanded = expandedRows.has(log.id);
                  const cleanMeta = sanitizeMetadata(log.metadata);
                  const hasMeta = cleanMeta && Object.keys(cleanMeta).length > 0;

                  return (
                    <React.Fragment key={log.id}>
                      <tr
                        className={`hover:bg-slate-50 dark:hover:bg-slate-800/50 transition cursor-pointer ${
                          isExpanded ? 'bg-slate-50/60 dark:bg-slate-800/30' : ''
                        }`}
                        onClick={() => toggleRowExpansion(log.id)}
                        id={`audit-row-${log.id}`}
                      >
                        <td className="px-4 py-3 text-slate-400">
                          {hasMeta ? (
                            <button
                              type="button"
                              aria-label={isExpanded ? 'Collapse details' : 'Expand details'}
                              className="p-1 rounded hover:bg-slate-200 dark:hover:bg-slate-700"
                              onClick={(e) => {
                                e.stopPropagation();
                                toggleRowExpansion(log.id);
                              }}
                            >
                              {isExpanded ? (
                                <ChevronDown className="w-4 h-4 text-slate-600 dark:text-slate-300" />
                              ) : (
                                <ChevronRight className="w-4 h-4 text-slate-400" />
                              )}
                            </button>
                          ) : null}
                        </td>
                        <td className="px-4 py-3 text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
                          {formatDate(log.created_at)}
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap">
                          <span
                            className="inline-block font-mono text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700"
                            data-testid={`action-${log.id}`}
                          >
                            {log.action}
                          </span>
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap">
                          <div className="flex flex-col">
                            <span className="text-xs font-medium text-slate-900 dark:text-slate-100">
                              {log.actor?.name || 'System'}
                            </span>
                            <span className="text-[11px] text-slate-500">
                              {log.actor?.role ? `${log.actor.role} ` : ''}
                              {log.actor?.id ? `(#${log.actor.id})` : ''}
                            </span>
                          </div>
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap">
                          <div className="text-xs">
                            <span className="font-medium text-slate-700 dark:text-slate-300">
                              {log.target?.type || 'none'}
                            </span>
                            {log.target?.id ? (
                              <span className="text-slate-500 ml-1">#{log.target.id}</span>
                            ) : null}
                          </div>
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap">
                          <span
                            className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${
                              log.result === 'success'
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                : 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300'
                            }`}
                          >
                            {log.result || 'success'}
                          </span>
                        </td>
                      </tr>

                      {/* Expandable metadata row */}
                      {isExpanded && (
                        <tr className="bg-slate-50/80 dark:bg-slate-800/50">
                          <td colSpan={6} className="px-6 py-3">
                            <div className="text-xs space-y-1">
                              <div className="font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Event Metadata:
                              </div>
                              <pre
                                className="font-mono text-xs bg-slate-900 text-slate-100 p-3 rounded-lg overflow-x-auto max-h-48"
                                data-testid={`metadata-${log.id}`}
                              >
                                {JSON.stringify(cleanMeta, null, 2)}
                              </pre>
                            </div>
                          </td>
                        </tr>
                      )}
                    </React.Fragment>
                  );
                })}
              </tbody>
            </table>
          </div>

          {totalPages > 1 && (
            <div className="flex justify-center pt-2">
              <Pagination
                currentPage={pageParam}
                totalPages={totalPages}
                onPageChange={handlePageChange}
              />
            </div>
          )}
        </div>
      )}
    </div>
  );
}
