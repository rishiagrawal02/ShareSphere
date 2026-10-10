import React, { useState, useEffect, useCallback } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { api, ApiError } from '../../api/client';
import { PageHeader } from '../../components/PageHeader';
import { StatusBadge } from '../../components/StatusBadge';
import { EmptyState } from '../../components/EmptyState';
import { LoadingState } from '../../components/LoadingState';
import { ErrorState } from '../../components/ErrorState';
import { Pagination } from '../../components/Pagination';
import { formatDate } from '../../utils/date';
import {
  ShieldCheck,
  Search,
  Building2,
  CheckCircle2,
  Clock,
  XCircle,
  AlertTriangle,
  RefreshCw,
  ArrowRight,
  FileText,
} from 'lucide-react';

const PAGE_SIZE = 15;

const STATUS_TABS = [
  { key: '', label: 'All Organizations' },
  { key: 'pending', label: 'Pending Review' },
  { key: 'verified', label: 'Verified' },
  { key: 'correction_requested', label: 'Correction Requested' },
  { key: 'rejected', label: 'Rejected' },
  { key: 'suspended', label: 'Suspended' },
];

export function Ngos() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [ngos, setNgos] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalCount, setTotalCount] = useState(0);

  const currentStatus = searchParams.get('status') || '';
  const currentSearch = searchParams.get('q') || '';
  const [searchInput, setSearchInput] = useState(currentSearch);

  const loadNgos = useCallback(
    async (pg = 1, status = currentStatus, query = currentSearch) => {
      setLoading(true);
      setError(null);
      try {
        let url = `/api/admin/ngos?page=${pg}&limit=${PAGE_SIZE}`;
        if (status) url += `&status=${encodeURIComponent(status)}`;
        if (query) url += `&q=${encodeURIComponent(query)}`;

        const res = await api.get(url);
        setNgos(res?.data ?? []);
        setTotalPages(res?.meta?.total_pages ?? 1);
        setTotalCount(res?.meta?.total ?? 0);
        setPage(pg);
      } catch (err) {
        setError(
          err instanceof ApiError ? err : new ApiError(500, 'LOAD_FAILED', 'Could not load NGO queue.')
        );
      } finally {
        setLoading(false);
      }
    },
    [currentStatus, currentSearch]
  );

  useEffect(() => {
    loadNgos(1, currentStatus, currentSearch);
  }, [loadNgos, currentStatus, currentSearch]);

  const handleStatusChange = (status) => {
    const params = new URLSearchParams(searchParams);
    if (status) params.set('status', status);
    else params.delete('status');
    params.delete('page');
    setSearchParams(params);
  };

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    const params = new URLSearchParams(searchParams);
    if (searchInput.trim()) params.set('q', searchInput.trim());
    else params.delete('q');
    params.delete('page');
    setSearchParams(params);
  };

  return (
    <div className="space-y-6">
      <PageHeader
        title="NGO Verification Queue"
        subtitle="Review registration documents, approve verified non-profit partners, and manage operational status."
        action={
          <button
            type="button"
            onClick={() => loadNgos(page, currentStatus, currentSearch)}
            aria-label="Refresh NGO list"
            className="flex items-center gap-1.5 px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs border border-slate-700 transition-all"
          >
            <RefreshCw size={14} />
          </button>
        }
      />

      {/* Filter Tabs */}
      <div className="flex items-center gap-2 overflow-x-auto pb-1">
        {STATUS_TABS.map((tab) => {
          const isActive = currentStatus === tab.key;
          return (
            <button
              key={tab.key}
              type="button"
              onClick={() => handleStatusChange(tab.key)}
              className={`px-4 py-2 rounded-xl text-xs font-semibold whitespace-nowrap border transition-all ${
                isActive
                  ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30'
                  : 'bg-slate-900 border-slate-800 text-slate-400 hover:text-white'
              }`}
            >
              {tab.label}
            </button>
          );
        })}
      </div>

      {/* Search Input Bar */}
      <form onSubmit={handleSearchSubmit} className="flex items-center gap-3">
        <div className="relative flex-1">
          <Search size={16} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500" />
          <input
            type="text"
            id="search-ngos"
            placeholder="Search by organization name, registration number, or contact..."
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            className="w-full bg-slate-900 border border-slate-800 rounded-xl pl-10 pr-4 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
          />
        </div>
        <button
          type="submit"
          className="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-semibold border border-slate-700 transition-all"
        >
          Search
        </button>
        {currentSearch && (
          <button
            type="button"
            onClick={() => {
              setSearchInput('');
              handleStatusChange(currentStatus);
            }}
            className="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-slate-400 rounded-xl text-xs border border-slate-800"
          >
            Clear
          </button>
        )}
      </form>

      {/* Content */}
      {loading ? (
        <LoadingState message="Loading NGO queue…" />
      ) : error ? (
        <ErrorState error={error} onRetry={() => loadNgos(page, currentStatus, currentSearch)} />
      ) : ngos.length === 0 ? (
        <EmptyState
          icon={Building2}
          title="No organizations found"
          description={
            currentSearch || currentStatus
              ? 'No organizations match your current filters. Try resetting the search or status filter.'
              : 'There are no NGO organizations registered in the platform yet.'
          }
        />
      ) : (
        <div className="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-slate-300">
              <thead className="bg-slate-950/80 text-slate-400 uppercase font-semibold text-[11px] border-b border-slate-800">
                <tr>
                  <th scope="col" className="px-5 py-3.5">Organization</th>
                  <th scope="col" className="px-5 py-3.5">Registration #</th>
                  <th scope="col" className="px-5 py-3.5">Contact Person</th>
                  <th scope="col" className="px-5 py-3.5">Submitted</th>
                  <th scope="col" className="px-5 py-3.5">Status</th>
                  <th scope="col" className="px-5 py-3.5 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/60">
                {ngos.map((ngo) => (
                  <tr key={ngo.id} className="hover:bg-slate-800/40 transition-colors">
                    <td className="px-5 py-4">
                      <div>
                        {/* Render strictly as text to prevent stored XSS (T-16.1-04) */}
                        <span className="font-bold text-white text-sm block">
                          {ngo.organization_name}
                        </span>
                        <span className="text-[11px] text-slate-500">
                          ID: #{ngo.id} · User: {ngo.user_email || ngo.email}
                        </span>
                      </div>
                    </td>
                    <td className="px-5 py-4 font-mono text-slate-300">
                      {ngo.registration_number || '—'}
                    </td>
                    <td className="px-5 py-4">
                      <div>
                        <span className="text-slate-200 block">{ngo.contact_person || '—'}</span>
                        <span className="text-[11px] text-slate-500">{ngo.contact_phone || ''}</span>
                      </div>
                    </td>
                    <td className="px-5 py-4 text-slate-400 whitespace-nowrap">
                      {formatDate(ngo.created_at)}
                    </td>
                    <td className="px-5 py-4">
                      <StatusBadge status={ngo.verification_status} size="sm" />
                    </td>
                    <td className="px-5 py-4 text-right">
                      <Link
                        to={`/admin/ngos/${ngo.id}`}
                        id={`view-ngo-${ngo.id}`}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-emerald-600 hover:text-white text-slate-300 rounded-xl font-medium transition-all"
                      >
                        <span>Review</span>
                        <ArrowRight size={12} />
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {totalPages > 1 && (
            <div className="p-4 border-t border-slate-800">
              <Pagination
                page={page}
                totalPages={totalPages}
                onPageChange={(p) => loadNgos(p, currentStatus, currentSearch)}
              />
            </div>
          )}
        </div>
      )}
    </div>
  );
}
