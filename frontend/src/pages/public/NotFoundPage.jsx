import React from 'react';
import { Link } from 'react-router-dom';
import { FileQuestion, Home } from 'lucide-react';

export function NotFoundPage() {
  return (
    <div className="min-h-[60vh] flex items-center justify-center p-6 text-center">
      <div className="max-w-md w-full bg-slate-900 border border-slate-800 rounded-3xl p-8 sm:p-10 shadow-2xl">
        <div className="w-16 h-16 bg-slate-800 text-slate-400 rounded-2xl flex items-center justify-center mx-auto mb-4">
          <FileQuestion size={32} />
        </div>
        <h1 className="text-3xl font-bold text-white mb-2">404</h1>
        <h2 className="text-lg font-semibold text-slate-200 mb-2">Page Not Found</h2>
        <p className="text-xs text-slate-400 leading-relaxed mb-6">
          The requested URL does not exist or may have been moved.
        </p>
        <Link
          to="/"
          className="inline-flex items-center gap-2 px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-medium rounded-xl text-xs transition-all"
        >
          <Home size={15} />
          <span>Return Home</span>
        </Link>
      </div>
    </div>
  );
}
