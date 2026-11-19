import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: 'e2e',
  testMatch: '**/*.spec.ts',
  timeout: Number(process.env.A11Y_TEST_TIMEOUT_MS ?? 60_000),
  expect: { timeout: Number(process.env.A11Y_EXPECT_TIMEOUT_MS ?? 5000) },
  fullyParallel: true,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
  globalSetup: './e2e/a11y/global-setup.ts',
  use: {
    baseURL: process.env.A11Y_BASE_URL ?? 'http://localhost:3000',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
