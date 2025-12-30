import { defineConfig, devices } from '@playwright/test';

/**
 * TYPO3 llms-txt extension Playwright configuration
 *
 * This configuration assumes TYPO3 is running locally on http://localhost:8000
 * Start the environment with: ./Build/Scripts/runTests.sh -t 13 -p 8.2 -s lintTypoScript
 */
export default defineConfig({
  testDir: './E2E',

  /* Run tests in files in parallel */
  fullyParallel: true,

  /* Fail the build on CI if you accidentally left test.only in the source code */
  forbidOnly: !!process.env.CI,

  /* Retry on CI only */
  retries: process.env.CI ? 2 : 0,

  /* Opt out of parallel tests on CI */
  workers: process.env.CI ? 1 : undefined,

  /* Reporter to use */
  reporter: 'html',

  /* Shared settings for all the projects below */
  use: {
    /* Base URL to use in actions like `await page.goto('/')` */
    baseURL: 'https://llms-txt.ddev.site',

    /* Collect trace when retrying the failed test */
    trace: 'on-first-retry',

    /* Screenshot on failure */
    screenshot: 'only-on-failure',
  },

  /* Configure projects for major browsers */
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
      name: 'webkit',
      use: { ...devices['Desktop Safari'] },
    },

    /* Test against mobile viewports */
    {
      name: 'Mobile Chrome',
      use: { ...devices['Pixel 5'] },
    },
    {
      name: 'Mobile Safari',
      use: { ...devices['iPhone 12'] },
    },
  ],

  /*
   * Note: TYPO3 server must be started separately before running tests
   * Start with: ./Build/Scripts/runTests.sh -t 13 -p 8.2 -s lintTypoScript
   * Or use the helper script: ./Build/Scripts/playwrightTests.sh
   */
  // webServer is not used - TYPO3 must be started manually
  webServer: undefined,
});
