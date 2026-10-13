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
  const displayMessage = error
    ? getErrorMessage(error)
    : (message || 'An unexpected error occurred while loading this view.');

  const displayRequestId = requestId || (error?.requestId ?? null);

  return (
    <div
      role="alert"
      className="ss-card"
      style={{ maxWidth: 460, margin: '2rem auto', padding: '2rem', textAlign: 'center' }}
    >
      <div style={{ width: 48, height: 48, background: 'var(--ss-red-bg)', borderRadius: 12, display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto .875rem' }}>
        <AlertCircle size={24} style={{ color: 'var(--ss-red)' }} />
      </div>
      <h3 style={{ fontSize: '1rem', fontWeight: 700, color: 'var(--ss-text)', marginBottom: '.5rem' }}>{title}</h3>
      <p style={{ fontSize: '.8125rem', color: 'var(--ss-muted)', marginBottom: '1rem' }}>{displayMessage}</p>

      {displayRequestId && (
        <p style={{ fontSize: '.6875rem', color: 'var(--ss-muted)', fontFamily: 'monospace', background: 'var(--ss-bg)', padding: '.375rem .75rem', borderRadius: 6, display: 'inline-block', marginBottom: '1rem', border: '1px solid var(--ss-border)' }}>
          Request ID: {displayRequestId}
        </p>
      )}

      {onRetry && (
        <button
          type="button"
          onClick={onRetry}
          className="ss-btn ss-btn-ghost"
        >
          <RotateCcw size={15} />
          Try Again
        </button>
      )}
    </div>
  );
}
