import React from 'react';
import { AlertCircle, RotateCcw } from 'lucide-react';
import { getErrorMessage } from '../lib/errorMessages';

/**
 * ErrorState — standardized full-page error display (Phase 17).
 *
 * Accepts either:
 *  - `error` (ApiError | Error): Automatically resolved to a friendly message
 *    via getErrorMessage(). The requestId is surfaced from ApiError.requestId.
 *  - `message` (string): Raw string override (used when error is not an object).
 *
 * All remaining props (title, requestId, onRetry) can still override defaults.
 */
export function ErrorState({
  title = 'Something went wrong',
  message,
  error = null,
  requestId = null,
  onRetry = null,
}) {
  // Resolve friendly message: error object takes priority over message string
  const displayMessage = error
    ? getErrorMessage(error)
    : (message || 'An unexpected error occurred while loading this view.');

  // Surface requestId from ApiError automatically if not overridden
  const displayRequestId = requestId || (error?.requestId ?? null);

  return (
    <div
      role="alert"
      className="p-8 bg-red-950/20 border border-red-900/40 rounded-2xl text-center max-w-lg mx-auto my-6"
    >
      <div className="w-12 h-12 bg-red-500/10 text-red-400 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <AlertCircle size={28} />
      </div>
      <h3 className="text-base font-bold text-white mb-2">{title}</h3>
      <p className="text-sm text-red-200/80 mb-4">{displayMessage}</p>

      {displayRequestId && (
        <p className="text-xs text-slate-400 font-mono mb-6 bg-slate-900/80 py-1.5 px-3 rounded-lg inline-block border border-slate-800">
          Request ID: {displayRequestId}
        </p>
      )}

      {onRetry && (
        <div>
          <button
            type="button"
            onClick={onRetry}
            className="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white font-medium rounded-xl text-sm transition-all focus:outline-none focus:ring-2 focus:ring-slate-600"
          >
            <RotateCcw size={16} />
            Try Again
          </button>
        </div>
      )}
    </div>
  );
}
