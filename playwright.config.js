import { defineConfig, devices } from '@playwright/test';

// Tes E2E memakai Google Chrome yang terpasang (channel: 'chrome') sehingga tidak perlu mengunduh browser.
export default defineConfig({
    testDir: './tests/e2e',
    outputDir: './storage/playwright/results',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: [['list']],
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8010',
        channel: 'chrome',
        locale: 'id-ID',
        timezoneId: 'Asia/Jakarta',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'desktop', use: { viewport: { width: 1440, height: 900 } } },
        { name: 'mobile', use: { ...devices['Pixel 7'], channel: 'chrome' } },
    ],
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8010',
        url: 'http://127.0.0.1:8010/kegiatan/buat',
        reuseExistingServer: true,
        timeout: 60_000,
    },
});
