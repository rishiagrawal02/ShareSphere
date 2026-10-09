import React from 'react';
import { MapPin, CheckCircle, Award, AlertCircle, Clock, ArrowRight } from 'lucide-react';
import { StatusBadge } from './StatusBadge';

export function MatchCard({ match, onSelect, showActions = true }) {
  if (!match) return null;

  const score = Math.round(match.total_score ?? match.score ?? 0);
  
  // Calculate score band based on spec §8
  const getScoreBand = (val) => {
    if (val >= 85) return { label: 'Excellent Match', color: 'text-emerald-400 bg-emerald-950/70 border-emerald-800/80', dot: 'bg-emerald-400' };
    if (val >= 70) return { label: 'Strong Match', color: 'text-teal-400 bg-teal-950/70 border-teal-800/80', dot: 'bg-teal-400' };
    if (val >= 50) return { label: 'Good Match', color: 'text-amber-400 bg-amber-950/70 border-amber-800/80', dot: 'bg-amber-400' };
    return { label: 'Moderate Match', color: 'text-slate-400 bg-slate-900 border-slate-700', dot: 'bg-slate-400' };
  };

  const band = getScoreBand(score);
  const reasons = match.score_reasons || match.reasons || [];
  const distanceKm = match.distance_km != null ? Number(match.distance_km).toFixed(1) : null;

  return (
    <div className="bg-slate-900/90 border border-slate-800 hover:border-slate-700 rounded-2xl p-5 shadow-lg transition-all duration-200 flex flex-col justify-between group">
      <div>
        {/* Header: Score Band & Urgency */}
        <div className="flex items-center justify-between gap-3 mb-3">
          <div className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border ${band.color}`}>
            <span className={`w-2 h-2 rounded-full ${band.dot}`} aria-hidden="true" />
            <span>{band.label} ({score}%)</span>
          </div>

          {match.urgency && (
            <StatusBadge status={match.urgency} size="sm" />
          )}
        </div>

        {/* NGO / Requirement Title */}
        <h3 className="text-lg font-bold text-white group-hover:text-emerald-400 transition-colors line-clamp-1">
          {match.requirement_title || match.title || 'NGO Requirement'}
        </h3>
        
        <p className="text-sm text-emerald-400/90 font-medium mb-2">
          {match.organization_name || match.ngo_name || 'Verified Community NGO'}
        </p>

        {match.description && (
          <p className="text-xs text-slate-400 line-clamp-2 mb-3 leading-relaxed">
            {match.description}
          </p>
        )}

        {/* Metadata grid */}
        <div className="grid grid-cols-2 gap-2 text-xs text-slate-300 bg-slate-950/50 p-3 rounded-xl border border-slate-800/60 mb-3">
          <div className="flex items-center gap-1.5">
            <Award size={14} className="text-teal-400" />
            <span>Needed: <strong className="text-white">{match.quantity_needed ?? match.quantity ?? '—'} units</strong></span>
          </div>
          {distanceKm != null && (
            <div className="flex items-center gap-1.5">
              <MapPin size={14} className="text-amber-400" />
              <span>Distance: <strong className="text-white">~{distanceKm} km</strong></span>
            </div>
          )}
          {match.min_condition && (
            <div className="flex items-center gap-1.5 col-span-2">
              <CheckCircle size={14} className="text-emerald-400" />
              <span>Min Condition: <strong className="capitalize text-white">{match.min_condition.replace('_', ' ')}</strong></span>
            </div>
          )}
        </div>

        {/* Reasons list */}
        {reasons.length > 0 && (
          <div className="mb-4">
            <span className="text-[11px] font-semibold uppercase tracking-wider text-slate-500 block mb-1.5">
              Match Factors
            </span>
            <ul className="space-y-1">
              {reasons.map((reason, idx) => (
                <li key={idx} className="text-xs text-slate-300 flex items-start gap-1.5">
                  <CheckCircle size={13} className="text-emerald-500 mt-0.5 shrink-0" />
                  <span>{reason}</span>
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>

      {showActions && onSelect && (
        <div className="pt-3 border-t border-slate-800/80 mt-auto">
          <button
            type="button"
            onClick={() => onSelect(match)}
            className="w-full inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-semibold rounded-xl bg-slate-800 hover:bg-emerald-600 text-slate-200 hover:text-white transition-all shadow-sm group-hover:bg-emerald-600 cursor-pointer"
          >
            <span>View Requirement Details</span>
            <ArrowRight size={14} />
          </button>
        </div>
      )}
    </div>
  );
}
