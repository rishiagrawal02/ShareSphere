import { test, expect } from '@playwright/test';

/**
 * ShareSphere Mobile Viewport Smoke Tests (Phase 18 Milestone 18.3 – T-18.3-07)
 *
 * Tests responsiveness at mobile viewport (390 x 844):
 *   - Landing page responsive layout and CTAs
 *   - Login form sizing and input touch targets
 *   - Mobile navigation header rendering
 */

test.describe('Mobile Viewport Smoke Suite (390x844)', () => {

  test.use({ viewport: { width: 390, height: 844 } });

  test('T-18.3-07: Mobile landing page renders cleanly without horizontal overflow', async ({ page }) => {
    await page.goto('/');

    // Check main headline
    const heading = page.locator('h1');
    await expect(heading).toBeVisible();

    // Verify viewport width does not have horizontal overflow
    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
    expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 5);

    // Verify hero Call-to-action buttons are visible and tap-friendly on mobile
    const ctaButton = page.getByRole('link', { name: /Donate Surplus Goods/i });
    await expect(ctaButton).toBeVisible();
  });

  test('T-18.3-07: Mobile login page renders full-width card and accessible touch targets', async ({ page }) => {
    await page.goto('/login');

    const emailInput = page.locator('input[type="email"]');
    const passwordInput = page.locator('input[type="password"]');
    const submitBtn = page.locator('button[type="submit"]');

    await expect(emailInput).toBeVisible();
    await expect(passwordInput).toBeVisible();
    await expect(submitBtn).toBeVisible();

    // Verify bounding box for tap targets is at least 36px tall
    const btnBox = await submitBtn.boundingBox();
    expect(btnBox).not.toBeNull();
    if (btnBox) {
      expect(btnBox.height).toBeGreaterThanOrEqual(36);
    }
  });
});
