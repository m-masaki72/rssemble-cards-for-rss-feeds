// @ts-check
const { defineConfig } = require('@playwright/test');
const path = require('path');

module.exports = defineConfig({
  testDir: './tests/e2e',
  timeout: 30000,
  use: {
    browserName: 'chromium',
    headless: true,
    baseURL: 'file://' + path.resolve(__dirname, 'docs') + '/',
  },
  reporter: [['list'], ['html', { open: 'never', outputFolder: 'tests/e2e/report' }]],
});
