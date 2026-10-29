import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';

export const E2E_STUB_FILE = 'stub/kegiatan-e2e.json';
export const E2E_RAB_FILE = 'stub/rab-e2e.json';
export const E2E_MATRIK_FILE = 'stub/matrik-jadwal-e2e.json';
export const E2E_TAHUN_FILE = 'stub/tahun-anggaran-e2e.json';
export const E2E_COVER_FILE = 'stub/cover-template-e2e.json';
export const E2E_MASTER_FILE = 'stub/master-template-e2e.json';
export const E2E_MASTER_KODE_FILE = 'stub/master-kode-e2e.json';
export const E2E_DAFTAR_ISI_FILE = 'stub/daftar-isi-e2e.json';
export const E2E_NOMOR_HALAMAN_FILE = 'stub/nomor-halaman-e2e.json';
export const E2E_PILIHAN_TEMPLATE_FILE = 'stub/pilihan-template-e2e.json';
export const E2E_PENOMORAN_FILE = 'stub/penomoran-dokumen-e2e.json';
export const E2E_RIWAYAT_FILE = 'stub/riwayat-pengaturan-e2e.json';
export const E2E_PROFIL_FILE = 'stub/profil-madrasah-e2e.json';
export const E2E_PEJABAT_FILE = 'stub/pejabat-e2e.json';
export const E2E_DATA_PAGU_FILE = 'stub/data-pagu-e2e.json';
export const E2E_DASAR_HUKUM_FILE = 'stub/dasar-hukum-e2e.json';
export const E2E_LEADING_FILE = 'stub/leading-e2e.json';
export const E2E_PENGGUNA_FILE = 'stub/pengguna-e2e.json';
export const E2E_PERMOHONAN_AKUN_FILE = 'stub/permohonan-akun-e2e.json';
export const E2E_ARSIP_FILE = 'stub/arsip-final-e2e.json';
export const E2E_NOTIFIKASI_FILE = 'stub/notifikasi-e2e.json';
export const E2E_BUKU_FILE = 'stub/buku-dokumen-e2e.json';
export const E2E_PEMAKAIAN_AI_FILE = 'stub/pemakaian-ai-e2e.json';
export const E2E_RIWAYAT_SARAN_AI_FILE = 'stub/riwayat-saran-ai-e2e.json';
export const E2E_PENGATURAN_AI_FILE = 'stub/pengaturan-ai-e2e.json';
export const E2E_PROMPT_AI_FILE = 'stub/prompt-ai-e2e.json';
export const E2E_CATATAN_PEMERIKSAAN_FILE = 'stub/catatan-pemeriksaan-e2e.json';
export const E2E_RIWAYAT_KUNCI_FILE = 'stub/riwayat-kunci-e2e.json';
export const E2E_DUPLIKAT_FILE = 'stub/duplikat-kegiatan-e2e.json';
export const E2E_IMPOR_FILE = 'stub/impor-kegiatan-e2e.json';
export const E2E_DRAF_DOKUMEN_FILE = 'stub/draf-dokumen-e2e.json';
export const E2E_HASIL_DOKUMEN_FILE = 'stub/hasil-dokumen-e2e.json';
export const E2E_RIWAYAT_SUNTING_FILE = 'stub/riwayat-sunting-e2e.json';
export const E2E_DRAF_SUNTING_FILE = 'stub/draf-sunting-e2e.json';
export const E2E_RIWAYAT_DOKUMEN_FILE = 'stub/riwayat-dokumen-e2e.json';

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
            STUB_MASTER_KODE_FILE: E2E_MASTER_KODE_FILE,
            STUB_DAFTAR_ISI_FILE: E2E_DAFTAR_ISI_FILE,
            STUB_NOMOR_HALAMAN_FILE: E2E_NOMOR_HALAMAN_FILE,
            STUB_PILIHAN_TEMPLATE_FILE: E2E_PILIHAN_TEMPLATE_FILE,
            STUB_PENOMORAN_DOKUMEN_FILE: E2E_PENOMORAN_FILE,
            STUB_RIWAYAT_FILE: E2E_RIWAYAT_FILE,
            STUB_PROFIL_FILE: E2E_PROFIL_FILE,
            STUB_PEJABAT_FILE: E2E_PEJABAT_FILE,
            STUB_DATA_PAGU_FILE: E2E_DATA_PAGU_FILE,
            STUB_DASAR_HUKUM_FILE: E2E_DASAR_HUKUM_FILE,
            STUB_LEADING_FILE: E2E_LEADING_FILE,
            STUB_PENGGUNA_FILE: E2E_PENGGUNA_FILE,
            STUB_PERMOHONAN_AKUN_FILE: E2E_PERMOHONAN_AKUN_FILE,
            STUB_ARSIP_FILE: E2E_ARSIP_FILE,
            STUB_NOTIFIKASI_FILE: E2E_NOTIFIKASI_FILE,
            STUB_BUKU_FILE: E2E_BUKU_FILE,
            STUB_PEMAKAIAN_AI_FILE: E2E_PEMAKAIAN_AI_FILE,
            STUB_RIWAYAT_SARAN_AI_FILE: E2E_RIWAYAT_SARAN_AI_FILE,
            STUB_PENGATURAN_AI_FILE: E2E_PENGATURAN_AI_FILE,
            STUB_PROMPT_AI_FILE: E2E_PROMPT_AI_FILE,
            STUB_CATATAN_PEMERIKSAAN_FILE: E2E_CATATAN_PEMERIKSAAN_FILE,
            STUB_RIWAYAT_KUNCI_FILE: E2E_RIWAYAT_KUNCI_FILE,
            STUB_DUPLIKAT_FILE: E2E_DUPLIKAT_FILE,
            STUB_IMPOR_FILE: E2E_IMPOR_FILE,
            STUB_DRAF_DOKUMEN_FILE: E2E_DRAF_DOKUMEN_FILE,
            STUB_HASIL_DOKUMEN_FILE: E2E_HASIL_DOKUMEN_FILE,
            STUB_RIWAYAT_SUNTING_FILE: E2E_RIWAYAT_SUNTING_FILE,
            STUB_DRAF_SUNTING_FILE: E2E_DRAF_SUNTING_FILE,
            STUB_RIWAYAT_DOKUMEN_FILE: E2E_RIWAYAT_DOKUMEN_FILE,
            SIRABTOR_CATAT_UNDUHAN: 'false',
            VITE_HOT_FILE: path.resolve('storage/framework/e2e-tanpa-vite-dev.hot'),
        },
    },
});
