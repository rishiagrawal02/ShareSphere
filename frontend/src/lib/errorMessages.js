/**
 * errorMessages.js — ShareSphere Standardized Error Code Mapper (Phase 17)
 *
 * Maps standardized backend API error codes and network errors to friendly,
 * user-facing messages conforming to WCAG plain language guidelines.
 */

export const ERROR_MESSAGES = {
  // Authentication & Authorization
  UNAUTHENTICATED: 'Your session has expired or you are not signed in. Please log in to continue.',
  FORBIDDEN: 'You do not have permission to access this resource or perform this operation.',
  INVALID_CREDENTIALS: 'The email address or password entered is incorrect.',
  TOKEN_EXPIRED: 'Your session token has expired. Please sign in again.',
  CSRF_FAILED: 'Security verification failed. Please refresh the page and try again.',
  CSRF_MISMATCH: 'Security verification failed. Please refresh the page and try again.',

  // Validation & Input Errors
  VALIDATION_FAILED: 'Validation failed. Please check the highlighted inputs and correct any errors.',
  PAYLOAD_TOO_LARGE: 'The uploaded file or submission is too large. Please select a smaller file.',
  UNSUPPORTED_MEDIA_TYPE: 'The uploaded file type is not supported. Please upload a PDF or image file.',

  // Resource & State Conflicts
  NOT_FOUND: 'The requested resource, donation, or page could not be found.',
  METHOD_NOT_ALLOWED: 'This operation is not supported for the specified endpoint.',
  INSUFFICIENT_QUANTITY: 'The requested quantity is no longer available in inventory.',
  INVALID_TRANSITION: 'This item cannot transition to the requested state from its current status.',
  CATEGORY_IN_USE: 'This category is currently referenced by active donations or requirements.',
  CONFLICT: 'A conflict occurred with current server state. Please refresh and try again.',

  // OTP Verification Codes
  OTP_EXPIRED: 'The verification code has expired. Please request a new code.',
  OTP_INVALID: 'The verification code entered is incorrect. Please double check and try again.',
  OTP_LOCKED: 'Too many incorrect attempts. Code verification is locked; please issue a new code.',
  OTP_COOLDOWN: 'Please wait for the cooldown timer before requesting another verification code.',

  // Rate Limiting & Server Errors
  RATE_LIMITED: 'Too many requests submitted. Please wait a moment before trying again.',
  INTERNAL_ERROR: 'An unexpected server error occurred. Our team has been notified.',
  SERVICE_UNAVAILABLE: 'The service is temporarily unavailable. Please try again shortly.',
  NETWORK_ERROR: 'Unable to reach the server. Please check your internet connection.',
};

/**
 * Get a friendly user-facing error message for a given error code or Error object.
 *
 * @param {string|Error} error - Error code string, ApiError, or generic Error instance
 * @param {string} [fallback] - Optional custom fallback message
 * @returns {string} User-friendly error message
 */
export function getErrorMessage(error, fallback = null) {
  if (!error) return fallback || 'An unexpected error occurred.';

  const code = typeof error === 'string'
    ? error
    : error.code || error.name;

  if (code && ERROR_MESSAGES[code]) {
    return ERROR_MESSAGES[code];
  }

  if (typeof error === 'object' && error.message) {
    // Return message if it doesn't look like raw technical stack trace
    if (!error.message.includes('at ') && !error.message.includes('SyntaxError')) {
      return error.message;
    }
  }

  return fallback || 'An unexpected error occurred. Please try again.';
}
