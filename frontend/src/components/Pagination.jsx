import React from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

export function Pagination({ page = 1, pages = 1, total = 0, onPageChange }) {
  if (pages <= 1) return null;

  return (
    <nav
      aria-label="Pagination Navigation"
      className="flex flex-col sm:flex-row items-center justify-between gap-4 py-4 px-2 text-xs text-slate-400"
    >
      <div>
        Showing page <span className="font-semibold text-white">{page}</span> of{' '}
        <span className="font-semibold text-white">{pages}</span> ({total} total results)
      </div>

      <div className="flex items-center gap-2">
        <button
          type="button"
          onClick={() => onPageChange(page - 1)}
          disabled={page <= 1}
          aria-label="Previous Page"
          className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 disabled:opacity-40 disabled:cursor-not-allowed transition-all"
        >
          <ChevronLeft size={14} />
          Previous
        </button>

        <span className="px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-800 text-emerald-400 font-semibold">
          {page}
        </span>

        <button
          type="button"
          onClick={() => onPageChange(page + 1)}
          disabled={page >= pages}
          aria-label="Next Page"
          className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 disabled:opacity-40 disabled:cursor-not-allowed transition-all"
        >
          Next
          <ChevronRight size={14} />
        </button>
      </div>
    </nav>
  );
}
