/**
 * SEC-02 Safe Navigation Guard
 * Prevents open redirects via backslashes (\\attacker.com), protocol-relative URLs (//attacker.com),
 * and javascript: URI schemes. Ensures only safe internal relative paths are used.
 */

export function sanitizeRedirectPath(path, fallback = '/') {
  if (typeof path !== 'string' || !path.trim()) {
    return fallback;
  }

  const trimmed = path.trim();

  // Block protocol-relative URLs or backslash bypasses (e.g., //evil.com, \\evil.com, /\evil.com)
  if (trimmed.startsWith('//') || trimmed.startsWith('\\') || trimmed.includes('\\')) {
    return fallback;
  }

  // Block javascript:, data:, vbscript: schemes
  if (/^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(trimmed)) {
    return fallback;
  }

  // Must begin with a single slash for relative navigation
  if (!trimmed.startsWith('/')) {
    return '/' + trimmed;
  }

  return trimmed;
}
