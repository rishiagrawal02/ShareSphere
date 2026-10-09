/**
 * ShareSphere API Client
 *
 * Implements native fetch with credentials: 'include',
 * automated CSRF token management, 1-time retry on CSRF_FAILED,
 * error envelope normalization into ApiError, and 401 handling.
 */

export class ApiError extends Error {
  constructor(status, code, message, fields = {}, requestId = null) {
    super(message || 'An unexpected error occurred');
    this.name = 'ApiError';
    this.status = status;
    this.code = code;
    this.fields = fields;
    this.requestId = requestId;
  }
}

let csrfTokenCache = null;
let isFetchingCsrf = null;

/**
 * Fetch a fresh CSRF token from the server.
 */
export async function getCsrfToken(forceRefresh = false) {
  if (csrfTokenCache && !forceRefresh) {
    return csrfTokenCache;
  }

  if (isFetchingCsrf) {
    return isFetchingCsrf;
  }

  isFetchingCsrf = (async () => {
    try {
      const res = await fetch('/api/auth/csrf-token', {
        method: 'GET',
        credentials: 'include',
        headers: { 'Accept': 'application/json' },
      });

      if (!res.ok) {
        throw new Error('Failed to obtain CSRF token');
      }

      const json = await res.json();
      csrfTokenCache = json.data?.csrf_token || json.csrf_token || null;
      return csrfTokenCache;
    } finally {
      isFetchingCsrf = null;
    }
  })();

  return isFetchingCsrf;
}

/**
 * Clear cached CSRF token (e.g. on logout or session change).
 */
export function clearCsrfToken() {
  csrfTokenCache = null;
}

/**
 * Core API request function.
 */
export async function apiRequest(endpoint, options = {}, isRetry = false) {
  const url = endpoint.startsWith('http') ? endpoint : endpoint;
  const method = (options.method || 'GET').toUpperCase();
  const isStateChanging = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method);

  const headers = new Headers(options.headers || {});
  headers.set('Accept', 'application/json');

  // Attach CSRF Token for state-changing requests
  if (isStateChanging) {
    try {
      const token = await getCsrfToken();
      if (token) {
        headers.set('X-CSRF-Token', token);
      }
    } catch {
      // Proceed without blocking; server will respond with CSRF_FAILED if required
    }
  }

  // Handle JSON body if not FormData
  let body = options.body;
  if (body && typeof body === 'object' && !(body instanceof FormData) && !(body instanceof Blob)) {
    headers.set('Content-Type', 'application/json; charset=UTF-8');
    body = JSON.stringify(body);
  }

  const fetchOptions = {
    ...options,
    method,
    headers,
    body,
    credentials: 'include',
  };

  let response;
  try {
    response = await fetch(url, fetchOptions);
  } catch (err) {
    throw new ApiError(0, 'NETWORK_ERROR', 'Unable to connect to the server. Please check your network.', {}, null);
  }

  // Handle CSRF retry if token expired (1 retry only)
  if (response.status === 403 && !isRetry) {
    try {
      const clone = response.clone();
      const errJson = await clone.json();
      if (errJson?.error?.code === 'CSRF_FAILED') {
        clearCsrfToken();
        await getCsrfToken(true);
        return apiRequest(endpoint, options, true);
      }
    } catch {
      // JSON parse failed; fall through to standard error handling
    }
  }

  // Handle 401 Unauthorized (Session Expired / Account Suspended)
  if (response.status === 401 && typeof window !== 'undefined') {
    const isAuthEndpoint = endpoint.includes('/api/auth/login') || endpoint.includes('/api/auth/register');
    if (!isAuthEndpoint) {
      window.dispatchEvent(new CustomEvent('sharesphere:unauthorized', { detail: { endpoint } }));
    }
  }

  // Parse JSON response
  let json = null;
  const contentType = response.headers.get('content-type') || '';
  if (contentType.includes('application/json')) {
    try {
      json = await response.json();
    } catch {
      json = null;
    }
  }

  if (!response.ok) {
    const errCode = json?.error?.code || 'HTTP_ERROR_' + response.status;
    const errMsg = json?.error?.message || response.statusText || 'Request failed';
    const errFields = json?.error?.fields || {};
    const reqId = json?.request_id || response.headers.get('X-Request-Id') || null;

    throw new ApiError(response.status, errCode, errMsg, errFields, reqId);
  }

  return json;
}

/**
 * Helper convenience methods
 */
export const api = {
  get: (url, options = {}) => apiRequest(url, { ...options, method: 'GET' }),
  post: (url, body, options = {}) => apiRequest(url, { ...options, method: 'POST', body }),
  put: (url, body, options = {}) => apiRequest(url, { ...options, method: 'PUT', body }),
  patch: (url, body, options = {}) => apiRequest(url, { ...options, method: 'PATCH', body }),
  delete: (url, options = {}) => apiRequest(url, { ...options, method: 'DELETE' }),
};
