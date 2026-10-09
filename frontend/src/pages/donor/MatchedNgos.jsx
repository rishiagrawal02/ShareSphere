import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { api, ApiError } from '../../api/client';
import { PageHeader } from '../../components/PageHeader';
import { MatchCard } from '../../components/MatchCard';
import { MatchMap } from '../../components/MatchMap';
import { LoadingState } from '../../components/LoadingState';
import { ErrorState } from '../../components/ErrorState';
import { EmptyState } from '../../components/EmptyState';
import {
  Sparkles,
  List,
  Map,
  ArrowLeft,
  Info,
  Package,
  Layers,
  Award,
  AlertCircle,
  HelpCircle,
} from 'lucide-react';

export function MatchedNgos() {
  const { id } = useParams();

  const [donation, setDonation] = useState(null);
  const [matches, setMatches] = useState([]);
  const [viewMode, setViewMode] = useState('list'); // 'list' | 'map'
  const [selectedMatch, setSelectedMatch] = useState(null);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const loadMatches = async () => {
    setLoading(true);
    setError(null);
    try {
      const [donRes, matchRes] = await Promise.all([
        api.get(`/api/donations/${id}`),
        api.get(`/api/matches?donation_id=${id}`),
      ]);

      if (donRes?.donation) {
        setDonation(donRes.donation);
      } else {
        throw new ApiError(404, 'NOT_FOUND', 'Donation listing not found.');
      }

      if (Array.isArray(matchRes?.data)) {
        setMatches(matchRes.data);
      }
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err);
      } else {
        setError(new ApiError(500, 'MATCH_FAILED', 'Failed to retrieve matched NGO needs.'));
      }
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadMatches();
  }, [id]);

  if (loading) {
    return <LoadingState message="Running smart spatial matching algorithm..." />;
  }

  if (error) {
    return <ErrorState error={error} onRetry={loadMatches} />;
  }

  const donorCoords = donation?.latitude != null && donation?.longitude != null
    ? [parseFloat(donation.latitude), parseFloat(donation.longitude)]
    : null;

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8 animate-fadeIn">
      {/* Navigation Breadcrumb */}
      <div className="flex items-center justify-between">
        <Link
          to={`/donor/donations/${id}`}
          className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-400 hover:text-white transition-colors"
        >
          <ArrowLeft size={14} />
          <span>Back to "{donation?.title || 'Donation'}"</span>
        </Link>
      </div>

      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 text-xs font-semibold text-emerald-400 mb-1">
            <Sparkles size={15} />
            <span>Smart Spatial Matching Engine</span>
          </div>
          <h1 className="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
            Matched NGO Requirements
          </h1>
          <p className="text-xs sm:text-sm text-slate-400 mt-1 max-w-2xl">
            Ranked community organizations that currently have open needs matching your "{donation?.title}" listing.
          </p>
        </div>

        {/* View Switcher: List vs Map */}
        <div className="flex items-center gap-1.5 bg-slate-900 border border-slate-800 p-1.5 rounded-2xl shadow-inner shrink-0">
          <button
            type="button"
            onClick={() => setViewMode('list')}
            className={`inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer ${
              viewMode === 'list'
                ? 'bg-emerald-600 text-white shadow-sm'
                : 'text-slate-400 hover:text-white'
            }`}
          >
            <List size={14} />
            <span>Ranked List</span>
          </button>
          <button
            type="button"
            onClick={() => setViewMode('map')}
            className={`inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer ${
              viewMode === 'map'
                ? 'bg-emerald-600 text-white shadow-sm'
                : 'text-slate-400 hover:text-white'
            }`}
          >
            <Map size={14} />
            <span>Match Map</span>
          </button>
        </div>
      </div>

      {/* Listing Summary Bar */}
      <div className="bg-slate-900/90 border border-slate-800 rounded-2xl p-4 sm:p-5 flex flex-wrap items-center justify-between gap-4 shadow-lg">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-emerald-950/80 border border-emerald-800/80 flex items-center justify-center text-emerald-400 shrink-0">
            <Package size={20} />
          </div>
          <div>
            <h4 className="text-sm font-bold text-white">{donation?.title}</h4>
            <div className="flex items-center gap-2 text-xs text-slate-400 mt-0.5">
              <span>Category: <strong className="text-slate-200">{donation?.category_name || 'General'}</strong></span>
              <span>•</span>
              <span>Available: <strong className="text-emerald-400">{donation?.available_quantity} units</strong></span>
              <span>•</span>
              <span>Condition: <strong className="text-slate-200 capitalize">{(donation?.condition || 'good').replace('_', ' ')}</strong></span>
            </div>
          </div>
        </div>

        <div className="text-xs font-semibold px-3 py-1.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-300">
          {matches.length} Verified {matches.length === 1 ? 'Match' : 'Matches'} Found
        </div>
      </div>

      {/* Main Content Area */}
      {matches.length === 0 ? (
        <EmptyState
          icon={Sparkles}
          title="No nearby NGO requirements found"
          description="There are currently no active NGO needs that align with this item category or geographical radius. NGOs post new needs daily!"
          actionLabel="View All My Donations"
          onAction={() => window.location.assign('/donor/donations')}
        />
      ) : (
        <div className="space-y-6">
          {viewMode === 'list' ? (
            /* List View */
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {matches.map((match, idx) => (
                <MatchCard
                  key={match.requirement_id || match.id || idx}
                  match={match}
                  onSelect={(m) => setSelectedMatch(m)}
                />
              ))}
            </div>
          ) : (
            /* Map View */
            <div className="space-y-6">
              <MatchMap
                donorLocation={donorCoords}
                matches={matches}
                height="500px"
                onSelectMatch={(m) => setSelectedMatch(m)}
              />

              {/* Selected Marker Detail Card if clicked */}
              {selectedMatch && (
                <div className="max-w-md mx-auto">
                  <MatchCard
                    match={selectedMatch}
                    onSelect={() => {}}
                    showActions={false}
                  />
                </div>
              )}
            </div>
          )}

          {/* Algorithm Guidance Footnote (Spec §8.5 Requirement) */}
          <div className="p-4 rounded-2xl bg-slate-900/60 border border-slate-800/80 flex items-start gap-3 text-xs text-slate-400">
            <Info size={16} className="text-teal-400 shrink-0 mt-0.5" />
            <p className="leading-relaxed">
              <strong>Ranking Guidance Note:</strong> Match scores are calculated using spatial proximity (35%), category compatibility (40%), condition suitability (15%), and quantity relevance (10%). Ranking is algorithmic guidance to assist coordination and does not constitute a reservation or guarantee.
            </p>
          </div>
        </div>
      )}

      {/* Match Detail Modal Dialog */}
      {selectedMatch && viewMode === 'list' && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm animate-fadeIn">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-5">
            <div className="flex items-center justify-between border-b border-slate-800 pb-3">
              <div>
                <h3 className="text-base font-bold text-white">
                  {selectedMatch.requirement_title || 'NGO Requirement Details'}
                </h3>
                <p className="text-xs text-emerald-400 font-semibold">
                  {selectedMatch.organization_name || 'Verified NGO'}
                </p>
              </div>
              <button
                type="button"
                onClick={() => setSelectedMatch(null)}
                className="text-slate-400 hover:text-white text-xs font-semibold px-2 py-1 rounded-lg bg-slate-800"
              >
                Close
              </button>
            </div>

            <div className="space-y-3 text-xs text-slate-300">
              <p className="leading-relaxed">{selectedMatch.description || 'No additional details provided.'}</p>
              
              <div className="bg-slate-950 p-3 rounded-xl border border-slate-800 space-y-1.5">
                <div className="flex justify-between">
                  <span className="text-slate-500">Units Needed:</span>
                  <strong className="text-white">{selectedMatch.quantity_needed || selectedMatch.quantity} units</strong>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500">Approximate Distance:</span>
                  <strong className="text-white">~{Number(selectedMatch.distance_km || 0).toFixed(1)} km</strong>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500">Urgency Level:</span>
                  <strong className="text-amber-400 uppercase">{selectedMatch.urgency || 'Standard'}</strong>
                </div>
              </div>
            </div>

            <div className="pt-2">
              <button
                type="button"
                onClick={() => setSelectedMatch(null)}
                className="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs transition-colors"
              >
                Done
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
