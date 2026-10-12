import { describe, it, expect } from 'vitest';
import { sanitizeRedirectPath } from './safeRedirect';

describe('SEC-02 Safe Navigation Guard', () => {
  it('allows safe relative paths', () => {
    expect(sanitizeRedirectPath('/donor/dashboard')).toBe('/donor/dashboard');
    expect(sanitizeRedirectPath('/donations/42')).toBe('/donations/42');
    expect(sanitizeRedirectPath('profile')).toBe('/profile');
  });

  it('neutralizes backslash open redirect attacks', () => {
    expect(sanitizeRedirectPath('\\\\attacker.com')).toBe('/');
    expect(sanitizeRedirectPath('\\evil.com/path')).toBe('/');
    expect(sanitizeRedirectPath('/safe\\unsafe')).toBe('/');
  });

  it('neutralizes protocol-relative open redirect attacks', () => {
    expect(sanitizeRedirectPath('//attacker.com')).toBe('/');
    expect(sanitizeRedirectPath('//evil.org/phish')).toBe('/');
  });

  it('neutralizes dangerous URI schemes', () => {
    expect(sanitizeRedirectPath('javascript:alert(1)')).toBe('/');
    expect(sanitizeRedirectPath('data:text/html,<script>alert(1)</script>')).toBe('/');
    expect(sanitizeRedirectPath('https://external-phishing-site.com')).toBe('/');
  });

  it('handles null, undefined, and empty inputs gracefully', () => {
    expect(sanitizeRedirectPath(null)).toBe('/');
    expect(sanitizeRedirectPath(undefined)).toBe('/');
    expect(sanitizeRedirectPath('')).toBe('/');
    expect(sanitizeRedirectPath('   ', '/custom')).toBe('/custom');
  });
});
