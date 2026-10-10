import React, { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, Link } from 'react-router-dom';
import { api, ApiError } from '../../api/client';
import { PageHeader } from '../../components/PageHeader';
import { StatusBadge } from '../../components/StatusBadge';
import { MatchCard } from '../../components/MatchCard';
import { MatchMap } from '../../components/MatchMap';
import { EmptyState } from '../../components/EmptyState';
import { LoadingState } from '../../components/LoadingState';
import { ErrorState } from '../../components/ErrorState';
import { Pagination } from '../../components/Pagination';
import { RequestDrawer } from '../../components/RequestDrawer';
import {
  HeartHandshake,
  List,
  Map,
  RefreshCw,
  ArrowLeft,
  Info,
  AlertTriangle,
} from 'lucide-react';

const PAGE_SIZE = 10;

export function MatchedDonations() {
  const { requirementId } = useParams();
  const [requirement, setRequirement] = useState(null);
  const [matches, setMatches] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [viewMode, setViewMode] = useState('list'); // 'list' | 'map'

  // Drawer state
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [selectedDonation, setSelectedDonation] = useState(null);

  const loadData = useCallback(async (pg = 1) => {
    setLoading(true);
    setError(null);
    try {
      const [reqRes, matchRes] = await Promise.all([
        api.get(`/api/requirements/${requirementId}`),
        api.get(
          `/api/matches?requirement_id=${requirementId}&page=${pg}&limit=${PAGE_SIZE}`
        ),
      ]);

      const r = reqRes?.data?.requirement || reqRes?.data;
      setRequirement(r || null);

      const data = matchRes?.data ?? [];
      setMatches(data);
      setTotalPages(matchRes?.meta?.total_pages ?? 1);
      setPage(pg);
    } catch (err) {
      setError(
        err instanceof ApiError ? err : new ApiError(500, 'LOAD_FAILED', 'Could not load matched donations.')
      );
    } finally {
      setLoading(false);
    }
  }, [requirementId]);

  // Refetch on window focus (spec §14.2 guidance)
  useEffect(() => {
    loadData(1);
    const onFocus = () => loadData(page);
    window.addEventListener('focus', onFocus);
    return () => window.removeEventListener('focus', onFocus);
  }, [loadData]); // eslint-disable-line react-hooks/exhaustive-deps

  const handleOpenDrawer = (donation) => {
    setSelectedDonation(donation);
    setDrawerOpen(true);
  };

  const handleRequestSuccess = (updatedDonation) => {
    setMatches((prev) =>
      prev.map((m) =>
        m.id === updatedDonation.id
          ? { ...m, available_quantity: updatedDonation.available_quantity }
          : m
      )
    );
  };

  const outstanding = requirement
    ? Math.max(
        0,
        (requirement.quantity_needed ?? 0) - (requirement.quantity_allocated ?? 0)
      )
    : null;

  // Build map items: donations with lat/lng from matches
  const mapItems = matches.map((m) => ({
    ...m,
    // Matches from donation side have donor lat/lng (privacy-snapped)
    latitude: m.latitude ?? m.donor_latitude,
    longitude: m.longitude ?? m.donor_longitude,
    title: m.donation_title ?? m.title,
    quantity_needed: m.available_quantity,
  }));

  const requirementCenter = requirement?.latitude && requirement?.longitude
    ? [parseFloat(requirement.latitude), parseFloat(requirement.longitude)]
    : null;

  if (loading) return <LoadingState message="Loading matched donations…" />;
  if (error) return <ErrorState error={error} onRetry={() => loadData(page)} />;

  return (
    <div className="space-y-6">
      <PageHeader
        title={requirement ? `Matches: ${requirement.title}` : 'Matched Donations'}
        subtitle="Ranked donations that best fit your requirement by score, distance, and condition."
        action={
          <div className="flex items-center gap-2">
            <Link
              to="/ngo/requirements"
              className="flex items-center gap-1.5 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-sm font-medium border border-slate-700 transition-all"
            >
              <ArrowLeft size={14} />
              Requirements
            </Link>
            <button
              type="button"
              onClick={() => loadData(page)}
              aria-label="Refresh matched donations"
              className="flex items-center gap-1.5 px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-sm border border-slate-700 transition-all"
            >
              <RefreshCw size={14} />
            </button>
          </div>
        }
      />

      {/* Requirement Summary Banner */}
      {requirement && (
        <div className="flex flex-wrap items-center gap-3 bg-slate-900 border border-slate-800 rounded-2xl px-5 py-4">
          <StatusBadge status={requirement.status} size="sm" />
          <span className="text-sm text-slate-300">
            Need:{' '}
            <strong className="text-white">{requirement.quantity_needed}</strong> ·
            Allocated:{' '}
            <strong className="text-blue-400">{requirement.quantity_allocated ?? 0}</strong> ·
            Outstanding:{' '}
            <strong className="text-amber-400">{outstanding}</strong>
          </span>
          {requirement.radius_km && (
            <span className="text-xs text-slate-400">· {requirement.radius_km} km radius</span>
          )}
        </div>
      )}

      {/* Privacy Notice */}
      <div className="flex items-start gap-2 text-xs text-slate-500">
        <Info size={13} className="shrink-0 mt-0.5 text-blue-400" />
        <span>
          Donation locations are privacy-snapped to a ~550 m grid until your request is accepted
          and a pickup is scheduled. Exact address is revealed only at that point.
        </span>
      </div>

      {/* View Toggle */}
      <div className="flex items-center gap-2">
        <button
          type="button"
          id="view-toggle-list"
          onClick={() => setViewMode('list')}
          className={`flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium border transition-all ${
            viewMode === 'list'
              ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30'
              : 'bg-slate-800 text-slate-400 border-slate-700 hover:border-slate-600'
          }`}
        >
          <List size={14} /> List
        </button>
        <button
          type="button"
          id="view-toggle-map"
          onClick={() => setViewMode('map')}
          className={`flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium border transition-all ${
            viewMode === 'map'
              ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30'
              : 'bg-slate-800 text-slate-400 border-slate-700 hover:border-slate-600'
          }`}
        >
          <Map size={14} /> Map
        </button>
        <span className="ml-auto text-xs text-slate-500">
          {matches.length} match{matches.length !== 1 ? 'es' : ''} found
        </span>
      </div>

      {matches.length === 0 ? (
        <EmptyState
          icon={HeartHandshake}
          title="No matches found"
          message="No active donations match this requirement yet. Tips: try widening the search radius in your requirement settings, or check back later as new donations are posted."
          action={
            <Link
              to={`/ngo/requirements/${requirementId}/edit`}
              className="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-medium rounded-xl transition-all"
            >
              Edit Requirement (Widen Radius / Category)
            </Link>
          }
        />
      ) : viewMode === 'map' ? (
        <div className="space-y-4">
          <MatchMap
            donorLocation={requirementCenter}
            matches={mapItems}
            height="480px"
            onSelectMatch={(m) => handleOpenDrawer(m)}
          />
          <p className="text-xs text-slate-500 text-center">
            Markers correspond exactly to list items. Click a marker to open the request drawer.
          </p>
        </div>
      ) : (
        <div className="space-y-4">
          {matches.map((match, idx) => {
            const available = match.available_quantity ?? 0;
            const hasPendingRequest = match.ngo_has_pending_request;

            return (
              <div key={match.id || idx} className="relative">
                <MatchCard
                  match={{
                    ...match,
                    organization_name: match.donation_title ?? match.title,
                    requirement_title: match.category_name,
                    total_score: match.total_score ?? match.score,
                    quantity_needed: available,
                  }}
                />
                {/* Request overlay */}
                <div className="absolute top-4 right-4 flex flex-col items-end gap-2">
                  <div className="flex items-center gap-2">
                    <span className="text-xs text-slate-400">
                      {available} available
                    </span>
                    {hasPendingRequest ? (
                      <span className="px-3 py-1.5 bg-amber-500/10 text-amber-400 border border-amber-500/20 rounded-xl text-xs font-medium">
                        Request Pending
                      </span>
                    ) : available > 0 && outstanding > 0 ? (
                      <button
                        type="button"
                        id={`request-btn-${match.id || idx}`}
                        onClick={() => handleOpenDrawer(match)}
                        className="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-semibold shadow-lg shadow-emerald-950/40 transition-all"
                      >
                        Request
                      </button>
                    ) : outstanding <= 0 ? (
                      <span className="px-3 py-1.5 bg-slate-700 text-slate-400 rounded-xl text-xs font-medium">
                        Requirement Filled
                      </span>
                    ) : (
                      <span className="px-3 py-1.5 bg-slate-700 text-slate-400 rounded-xl text-xs font-medium">
                        Out of Stock
                      </span>
                    )}
                  </div>
                  {match.updated_at && (
                    <p className="text-[10px] text-slate-500">
                      Updated {new Date(match.updated_at).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })}
                    </p>
                  )}
                </div>
              </div>
            );
          })}

          {totalPages > 1 && (
            <Pagination
              page={page}
              totalPages={totalPages}
              onPageChange={(p) => loadData(p)}
            />
          )}
        </div>
      )}

      {/* Request Drawer */}
      <RequestDrawer
        isOpen={drawerOpen}
        donation={selectedDonation}
        requirementId={Number(requirementId)}
        outstanding={outstanding}
        onClose={() => { setDrawerOpen(false); setSelectedDonation(null); }}
        onSuccess={handleRequestSuccess}
      />
    </div>
  );
}
