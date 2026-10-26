import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';

export const E2E_STUB_FILE = 'stub/kegiatan-e2e.json';
export const E2E_RAB_FILE = 'stub/rab-e2e.json';
export const E2E_MATRIK_FILE = 'stub/matrik-jadwal-e2e.json';
export const E2E_TAHUN_FILE = 'stub/tahun-anggaran-e2e.json';
export const E2E_COVER_FILE = 'stub/cover-template-e2e.json';
export const E2E_MASTER_FILE = 'stub/master-template-e2e.json';
export const E2E_DAFTAR_ISI_FILE = 'stub/daftar-isi-e2e.json';
export const E2E_NOMOR_HALAMAN_FILE = 'stub/nomor-halaman-e2e.json';
export const E2E_RIWAYAT_FILE = 'stub/riwayat-pengaturan-e2e.json';
export const E2E_PROFIL_FILE = 'stub/profil-madrasah-e2e.json';
export const E2E_PEJABAT_FILE = 'stub/pejabat-e2e.json';
export const E2E_DATA_PAGU_FILE = 'stub/data-pagu-e2e.json';

// Tes E2E memakai Google Chrome yang terpasang (channel: 'chrome') sehingga tidak perlu mengunduh browser.
export default defineConfig({
    testDir: './tests/e2e',
    outputDir: './storage/playwright/results',
    fullyParallel: false,
    workers: 1,
    // Server PHP bawaan di Windows hanya satu proses; beri ruang waktu & satu kali ulang (tercatat sebagai "flaky").
    retries: 1,
    timeout: 60_000,
    expect: { timeout: 15_000 },
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
    globalSetup: './tests/e2e/global-setup.js',
    // Server PHP bawaan dipanggil langsung (bukan `artisan serve`) agar variabel lingkungan E2E ikut terbawa.
    webServer: {
        command: 'php -S 127.0.0.1:8010 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
        cwd: 'public',
        url: 'http://127.0.0.1:8010/kegiatan/buat',
        reuseExistingServer: true,
        timeout: 60_000,
        // VITE_HOT_FILE menunjuk berkas yang tidak ada: aset build dipakai walau `npm run dev` sedang berjalan.
        env: {
            STUB_KEGIATAN_FILE: E2E_STUB_FILE,
            STUB_RAB_FILE: E2E_RAB_FILE,
            STUB_MATRIK_FILE: E2E_MATRIK_FILE,
            STUB_TAHUN_FILE: E2E_TAHUN_FILE,
            STUB_COVER_FILE: E2E_COVER_FILE,
            STUB_MASTER_FILE: E2E_MASTER_FILE,
            STUB_DAFTAR_ISI_FILE: E2E_DAFTAR_ISI_FILE,
            STUB_NOMOR_HALAMAN_FILE: E2E_NOMOR_HALAMAN_FILE,
            STUB_RIWAYAT_FILE: E2E_RIWAYAT_FILE,
            STUB_PROFIL_FILE: E2E_PROFIL_FILE,
            STUB_PEJABAT_FILE: E2E_PEJABAT_FILE,
            STUB_DATA_PAGU_FILE: E2E_DATA_PAGU_FILE,
            VITE_HOT_FILE: path.resolve('storage/framework/e2e-tanpa-vite-dev.hot'),
        },
    },
});
