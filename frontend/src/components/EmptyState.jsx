import React from 'react';
import { PackageOpen } from 'lucide-react';

export function EmptyState({
  title = 'No items found',
  description = 'There are no records to display at this time.',
  message,
  icon: Icon = PackageOpen,
  actionText,
  onAction,
  action,
  children,
}) {
  const desc = message || description;
  return (
    <div className="flex flex-col items-center justify-center p-12 text-center bg-slate-900/40 border border-slate-800/80 rounded-2xl">
      <div className="w-16 h-16 bg-slate-800/60 rounded-2xl flex items-center justify-center text-slate-400 mb-4">
        <Icon size={32} />
      </div>
      <h3 className="text-base font-semibold text-white mb-1">{title}</h3>
      <p className="text-sm text-slate-400 max-w-sm mb-6">{desc}</p>
      {action || children ? (
        action || children
      ) : actionText && onAction ? (
        <button
          type="button"
          onClick={onAction}
          className="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-medium rounded-xl text-sm transition-all focus:outline-none focus:ring-2 focus:ring-emerald-500/50"
        >
          {actionText}
        </button>
      ) : null}
    </div>
  );
}
