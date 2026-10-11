import { describe, it, expect } from 'vitest';
import { ERROR_MESSAGES, getErrorMessage } from './errorMessages';

describe('T-17.1-02: Code->message coverage & friendly copy', () => {
  const contractCodes = [
    'VALIDATION_FAILED',
    'UNAUTHENTICATED',
    'FORBIDDEN',
    'NOT_FOUND',
    'METHOD_NOT_ALLOWED',
    'INSUFFICIENT_QUANTITY',
    'INVALID_TRANSITION',
    'CATEGORY_IN_USE',
    'OTP_EXPIRED',
    'OTP_INVALID',
    'OTP_LOCKED',
    'OTP_COOLDOWN',
    'RATE_LIMITED',
    'PAYLOAD_TOO_LARGE',
    'UNSUPPORTED_MEDIA_TYPE',
    'NETWORK_ERROR',
    'INTERNAL_ERROR',
  ];

  it.each(contractCodes)('code "%s" maps to a friendly non-empty user string', (code) => {
    expect(ERROR_MESSAGES[code]).toBeDefined();
    expect(typeof ERROR_MESSAGES[code]).toBe('string');
    expect(ERROR_MESSAGES[code].length).toBeGreaterThan(15);
    expect(getErrorMessage(code)).toBe(ERROR_MESSAGES[code]);
  });

  it('handles ApiError objects with code property', () => {
    const mockApiError = { code: 'OTP_EXPIRED', message: 'Raw error string' };
    expect(getErrorMessage(mockApiError)).toBe(ERROR_MESSAGES.OTP_EXPIRED);
  });

  it('uses fallback message when error code is unknown', () => {
    expect(getErrorMessage('UNKNOWN_CUSTOM_CODE', 'Custom Fallback')).toBe('Custom Fallback');
  });
});
