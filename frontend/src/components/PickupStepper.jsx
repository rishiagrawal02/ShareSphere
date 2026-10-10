import React from 'react';
import {
  Calendar,
  Clock,
  KeyRound,
  PackageCheck,
  CheckCircle2,
  XCircle,
} from 'lucide-react';
import { formatDateTime } from '../utils/date';

const STEPS = [
  { key: 'proposed', label: 'Proposed', icon: Clock, desc: 'Awaiting confirmation' },
  { key: 'scheduled', label: 'Scheduled', icon: Calendar, desc: 'Time confirmed' },
  { key: 'otp_issued', label: 'Code Issued', icon: KeyRound, desc: 'Ready for handover' },
  { key: 'collected', label: 'Collected', icon: PackageCheck, desc: 'Items verified' },
  { key: 'completed', label: 'Completed', icon: CheckCircle2, desc: 'Receipt confirmed' },
];

export function PickupStepper({ state = 'proposed', pickup = {} }) {
  const isCancelled = state === 'cancelled';

  const stepOrder = ['proposed', 'scheduled', 'otp_issued', 'collected', 'completed'];
  const currentIndex = stepOrder.indexOf(state);

  return (
    <div className="w-full bg-slate-900/80 border border-slate-800 rounded-2xl p-5 sm:p-6">
      {/* Cancelled Banner if cancelled */}
      {isCancelled ? (
        <div className="flex items-center gap-3 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300">
          <XCircle size={20} className="shrink-0 text-red-400" />
          <div>
            <h4 className="font-semibold text-sm">Pickup Cancelled</h4>
            <p className="text-xs text-red-400/80 mt-0.5">
              This pickup was cancelled. You may propose a new pickup time if the donation allocation
              remains active.
            </p>
          </div>
        </div>
      ) : (
        <nav aria-label="Pickup progress" className="relative">
          <ol className="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 md:gap-2">
            {STEPS.map((step, idx) => {
              const Icon = step.icon;
              const isPast = idx < currentIndex;
              const isCurrent = idx === currentIndex;
              const isFuture = idx > currentIndex;

              let iconBg = 'bg-slate-800 border-slate-700 text-slate-500';
              let lineBg = 'bg-slate-800';

              if (isPast) {
                iconBg = 'bg-emerald-500/20 border-emerald-500 text-emerald-400';
                lineBg = 'bg-emerald-500';
              } else if (isCurrent) {
                iconBg = 'bg-emerald-600 border-emerald-400 text-white shadow-lg shadow-emerald-950/60 ring-4 ring-emerald-500/20';
              }

              return (
                <li
                  key={step.key}
                  className="flex-1 flex md:flex-col items-center gap-3 md:gap-2 relative w-full"
                  aria-current={isCurrent ? 'step' : undefined}
                >
                  {/* Connecting Line for desktop */}
                  {idx > 0 && (
                    <div
                      className={`hidden md:block absolute top-5 -left-1/2 w-full h-0.5 -z-0 transition-all ${
                        idx <= currentIndex ? 'bg-emerald-500' : 'bg-slate-800'
                      }`}
                    />
                  )}

                  {/* Icon Circle */}
                  <div
                    className={`w-10 h-10 rounded-xl border flex items-center justify-center shrink-0 z-10 transition-all ${iconBg}`}
                  >
                    {isPast ? <CheckCircle2 size={18} /> : <Icon size={18} />}
                  </div>

                  {/* Text details */}
                  <div className="flex-1 md:text-center min-w-0">
                    <p
                      className={`text-xs font-semibold ${
                        isCurrent
                          ? 'text-emerald-400'
                          : isPast
                          ? 'text-slate-200'
                          : 'text-slate-500'
                      }`}
                    >
                      {step.label}
                    </p>
                    <p className="text-[11px] text-slate-400 truncate">
                      {isCurrent
                        ? step.desc
                        : isPast && step.key === 'scheduled' && pickup.scheduled_at
                        ? formatDateTime(pickup.scheduled_at)
                        : isPast && step.key === 'collected' && pickup.collected_at
                        ? formatDateTime(pickup.collected_at)
                        : isPast && step.key === 'completed' && pickup.completed_at
                        ? formatDateTime(pickup.completed_at)
                        : isPast
                        ? 'Done'
                        : step.desc}
                    </p>
                  </div>
                </li>
              );
            })}
          </ol>
        </nav>
      )}
    </div>
  );
}
