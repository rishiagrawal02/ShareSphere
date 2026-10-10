import React, { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { api, ApiError } from '../../api/client';
import { useAuth } from '../../auth/AuthContext';
import { PageHeader } from '../../components/PageHeader';
import { StatusBadge } from '../../components/StatusBadge';
import { EmptyState } from '../../components/EmptyState';
import { LoadingState } from '../../components/LoadingState';
import { ErrorState } from '../../components/ErrorState';
import { Pagination } from '../../components/Pagination';
import { formatDateTime } from '../../utils/date';
import {
  Calendar,
  Clock,
  Package,
  MapPin,
  ArrowRight,
  RefreshCw,
  Plus,
} from 'lucide-react';

const PAGE_SIZE = 12;

export function PickupsList() {
  const { user } = useAuth();
  const [pickups, setPickups] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [filterState, setFilterState] = useState(''); // '' | 'proposed' | 'scheduled' | 'otp_issued' | 'collected' | 'completed' | 'cancelled'

  const loadPickups = useCallback(
    async (pg = 1, state = filterState) => {
      setLoading(true);
      setError(null);
      try {
        const query = `page=${pg}&per_page=${PAGE_SIZE}${state ? `&state=${state}` : ''}`;
        const res = await api.get(`/api/pickups?${query}`);
        setPickups(res?.data ?? []);
        setTotalPages(res?.meta?.total_pages ?? 1);
        setPage(pg);
      } catch (err) {
        setError(
          err instanceof ApiError
            ? err
            : new ApiError(500, 'LOAD_FAILED', 'Could not load scheduled pickups.')
        );
      } finally {
        setLoading(false);
      }
    },
    [filterState]
  );

  useEffect(() => {
    loadPickups(1, filterState);
  }, [loadPickups, filterState]);

  const isNgo = user?.role === 'ngo';

  const filterTabs = [
    { key: '', label: 'All' },
    { key: 'proposed', label: 'Proposed' },
    { key: 'scheduled', label: 'Scheduled' },
    { key: 'otp_issued', label: 'In Progress' },
    { key: 'completed', label: 'Completed' },
    { key: 'cancelled', label: 'Cancelled' },
  ];

  return (
    <div className="space-y-6">
      <PageHeader
        title="Scheduled Pickups"
        subtitle="Coordinate physical handovers, verify verification codes, and track donation transfers."
        action={
          <button
            type="button"
            onClick={() => loadPickups(page)}
            aria-label="Refresh pickups list"
            className="flex items-center gap-1.5 px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs border border-slate-700 transition-all"
          >
            <RefreshCw size={14} />
          </button>
        }
      />

      {/* Filter Tabs */}
      <div className="flex items-center gap-2 overflow-x-auto pb-1">
        {filterTabs.map((tab) => (
          <button
            key={tab.key}
            type="button"
            onClick={() => {
              setFilterState(tab.key);
              setPage(1);
            }}
            className={`px-4 py-2 rounded-xl text-xs font-semibold whitespace-nowrap border transition-all ${
              filterState === tab.key
                ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30'
                : 'bg-slate-900 border-slate-800 text-slate-400 hover:text-white'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {loading ? (
        <LoadingState message="Loading pickups…" />
      ) : error ? (
        <ErrorState error={error} onRetry={() => loadPickups(page)} />
      ) : pickups.length === 0 ? (
        <EmptyState
          icon={Calendar}
          title="No pickups found"
          description={
            filterState
              ? `There are no pickups matching the "${filterState}" status filter.`
              : 'You do not have any active or past pickups yet. Accepted donation requests will show up here.'
          }
          action={
            isNgo ? (
              <Link
                to="/ngo/requests"
                className="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium rounded-xl transition-all"
              >
                View My Requests
              </Link>
            ) : (
              <Link
                to="/donor/requests"
                className="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium rounded-xl transition-all"
              >
                View Received Requests
              </Link>
            )
          }
        />
      ) : (
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {pickups.map((p) => {
              const counterpartyName = isNgo ? 'Donor' : p.ngo?.organization_name || 'Verified NGO';

              return (
                <div
                  key={p.id}
                  className="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 hover:border-slate-700/80 transition-all flex flex-col justify-between gap-4 shadow-lg"
                >
                  <div className="space-y-3">
                    <div className="flex items-center justify-between gap-2">
                      <span className="text-xs text-slate-500 font-mono">#{p.id}</span>
                      <StatusBadge status={p.state} size="sm" />
                    </div>

                    <div>
                      <h4 className="text-base font-bold text-white line-clamp-1">
                        {p.donation?.title || 'Donation Item'}
                      </h4>
                      <p className="text-xs text-slate-400 mt-0.5">
                        Quantity:{' '}
                        <strong className="text-emerald-400">
                          {p.allocation?.allocated_quantity} units
                        </strong>{' '}
                        · With: <strong className="text-white">{counterpartyName}</strong>
                      </p>
                    </div>

                    <div className="flex items-center gap-2 text-xs text-slate-300 bg-slate-950 px-3 py-2 rounded-xl border border-slate-800/80">
                      <Clock size={14} className="text-emerald-400 shrink-0" />
                      <span className="truncate">{formatDateTime(p.scheduled_at)}</span>
                    </div>

                    {p.location_details && (
                      <p className="text-xs text-slate-400 line-clamp-1 italic">
                        &quot;{p.location_details}&quot;
                      </p>
                    )}
                  </div>

                  <div className="pt-3 border-t border-slate-800/80 flex items-center justify-end">
                    <Link
                      to={`/pickups/${p.id}`}
                      id={`pickup-card-${p.id}`}
                      className="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-semibold shadow-md transition-all"
                    >
                      <span>View Handover</span>
                      <ArrowRight size={13} />
                    </Link>
                  </div>
                </div>
              );
            })}
          </div>

          {totalPages > 1 && (
            <Pagination
              page={page}
              totalPages={totalPages}
              onPageChange={(pg) => loadPickups(pg)}
            />
          )}
        </div>
      )}
    </div>
  );
}
