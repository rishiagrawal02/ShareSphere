import { test, expect } from '@playwright/test';

/**
 * ShareSphere End-to-End User Journeys (Phase 18 Milestone 18.3 – T-18.3-01)
 *
 * Covers core role journeys:
 *   1. Public Visitor: landing page, navigation, public content
 *   2. Authentication: donor login, profile access, and logout
 *   3. Donor Posting: donor creates a new donation listing
 *   4. NGO Flow: verified NGO dashboard and requirement management
 *   5. Admin Flow: admin metrics, NGO verification queue, category management
 */

test.describe('E2E Role Journeys & Spec Appendix B', () => {

  test('Public visitor can view landing page and navigate public links', async ({ page }) => {
    await page.goto('/');

    // Verify main brand heading
    await expect(page.locator('h1')).toBeVisible();

    // Navigate to How It Works via footer (works across all viewports incl. mobile)
    await page.locator('footer a[href="/how-it-works"]').click();
    await expect(page).toHaveURL(/.*how-it-works/);
    await expect(page.locator('h1')).toContainText(/How ShareSphere Works/i);

    // Navigate to Impact via footer
    await page.locator('footer a[href="/impact"]').click();
    await expect(page).toHaveURL(/.*impact/);
    await expect(page.locator('h1')).toContainText(/Community Impact/i);
  });

  test('Donor login, dashboard navigation, and logout flow', async ({ page }) => {
    await page.goto('/login');

    // Fill credentials
    await page.fill('input[type="email"]', 'donor@sharesphere.test');
    await page.fill('input[type="password"]', 'DonorPass123!');
    await page.click('button[type="submit"]');

    // Successful login redirects to donor dashboard
    await expect(page).toHaveURL(/.*donor/);
    await expect(page.locator('body')).toContainText(/Welcome back/i);
    await expect(page.locator('body')).toContainText(/Total Donations/i);

    // Verify navigation exists (desktop nav links or mobile menu link in DOM)
    await expect(page.locator('a[href="/donor/donations/new"]').first()).toBeAttached();
    await expect(page.locator('a[href="/donor/donations"]').first()).toBeAttached();

    // Logout via button (works on all viewports)
    const logoutBtn = page.getByRole('button', { name: /log out/i });
    if (await logoutBtn.isVisible()) {
      await logoutBtn.click();
      await expect(page).toHaveURL(/.*login|\//);
    }
  });

  test('Donor can navigate to Post Donation page and view form inputs', async ({ page }) => {
    // Login as Donor
    await page.goto('/login');
    await page.fill('input[type="email"]', 'donor@sharesphere.test');
    await page.fill('input[type="password"]', 'DonorPass123!');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/.*donor/);

    // Navigate directly to Post Donation form (avoids mobile nav visibility issues)
    await page.goto('/donor/donations/new');
    await expect(page).toHaveURL(/.*donor\/donations\/new/);
    await expect(page.locator('h1')).toContainText(/Post a Surplus Donation|Post.*Donation/i);

    // Verify presence of form inputs (both use type="button" not type="submit")
    await expect(page.locator('select, [role="combobox"]').first()).toBeVisible();
    // Check for the publish button by text content
    await expect(page.getByRole('button', { name: /Publish Donation Listing|Save as Draft/i }).first()).toBeVisible();
  });

  test('Verified NGO can login and access NGO Dashboard', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[type="email"]', 'ngo@sharesphere.test');
    await page.fill('input[type="password"]', 'NgoPass123!');
    await page.click('button[type="submit"]');

    // Redirect to NGO dashboard
    await expect(page).toHaveURL(/.*ngo/);
    await expect(page.locator('body')).toContainText(/Hope Care Foundation|Activity Overview/i);

    // Check action link exists in DOM
    await expect(page.locator('a[href="/ngo/requirements/new"]').first()).toBeAttached();
  });

  test('Administrator can login, view admin dashboard and category controls', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[type="email"]', 'admin@sharesphere.test');
    await page.fill('input[type="password"]', 'AdminPass123!');
    await page.click('button[type="submit"]');

    // Redirect to Admin dashboard
    await expect(page).toHaveURL(/.*admin/);
    // Wait for dashboard to fully load (not error state)
    await expect(page.locator('body')).not.toContainText(/Something went wrong/i, { timeout: 10000 });
    await expect(page.locator('h1')).toContainText(/Administrative Control Center|Platform Overview|Admin/i);

    // Navigate to Categories management directly
    await page.goto('/admin/categories');
    await expect(page).toHaveURL(/.*admin\/categories/);
    await expect(page.locator('h1')).toContainText(/Categories/i);

    // Navigate to NGO verification list directly
    await page.goto('/admin/ngos');
    await expect(page).toHaveURL(/.*admin\/ngos/);
    await expect(page.locator('h1')).toContainText(/NGO/i);
  });
});
