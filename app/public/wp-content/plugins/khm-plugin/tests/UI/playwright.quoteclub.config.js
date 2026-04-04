// @ts-check
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: '.',
  timeout: 30_000,
  expect: {
    timeout: 10_000,
  },
  testMatch: ['quoteclub_invite_flow.spec.js'],
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8080/tests/UI',
    headless: true,
  },
  webServer: {
    command: 'python3 -m http.server 8080 --bind 127.0.0.1 --directory ../..',
    url: 'http://127.0.0.1:8080/tests/UI/quoteclub_harness.html',
    reuseExistingServer: true,
    timeout: 30_000,
  },
  reporter: [
    ['list'],
    ['html', { open: 'never' }],
  ],
});
