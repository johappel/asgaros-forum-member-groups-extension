import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright-Konfiguration für AFSpaces E2E-Tests.
 * Basis-URL: lokale WP-Instanz (WP Local "forums").
 */
export default defineConfig({
  testDir: './tests',
  timeout: 60000,
  expect: { timeout: 15000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL: process.env.AFSPACES_BASE_URL || 'http://forums.test',
    trace: 'on-first-retry',
    actionTimeout: 10000,
    navigationTimeout: 20000,
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
