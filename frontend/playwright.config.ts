import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './e2e',
  globalSetup: './e2e/global-setup.ts',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: 0,
  workers: 1,
  reporter: [['list']],
  use: {
    baseURL: process.env.BASE_URL || 'http://127.0.0.1:5173',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },
    {
      name: 'mobile-chrome',
      use: {
        ...devices['Pixel 5'],
        viewport: { width: 390, height: 844 },
      },
    },
  ],
  webServer: [
    {
      command: 'php -S 127.0.0.1:8000 -t public public/index.php',
      cwd: '../backend',
      port: 8000,
      reuseExistingServer: true,
      timeout: 15000,
    },
    {
      command: 'npm run dev -- --port 5173 --host 127.0.0.1',
      port: 5173,
      reuseExistingServer: true,
      timeout: 20000,
    },
  ],
});
