import { expect, test } from '@playwright/test';
import fs from 'node:fs';
import JSZip from 'jszip';

test.describe('Cetak & Unduh', () => {
    test('daftar dokumen, pilih dokumen & lembar, buka halaman cetak', async ({ page, context }, testInfo) => {
        await context.addInitScript(() => {
            window.print = () => {
                window.__dicetak = (window.__dicetak ?? 0) + 1;
            };
        });
        await page.goto('/demo/peran/leading');
        await page.goto('/cetak');
        await expect(page.getByRole('heading', { name: 'Cetak & Unduh', level: 1 })).toBeVisible();

        const daftar = page.getByTestId('daftar-cetak');
        await expect(daftar.getByTestId('dokumen-cetak').first()).toBeVisible();
        await expect(page.getByTestId('panel-cetak-terpilih').getByTestId('judul-terpilih')).toBeVisible();

        await daftar.locator('[data-kunci="2|DIPA"]').click();
        await expect(page).toHaveURL(/pilih=2%7CDIPA/);
        const panel = page.getByTestId('panel-cetak-terpilih');
        await expect(panel.getByTestId('judul-terpilih')).toContainText('Supervisi');
        await expect(panel.getByTestId('judul-terpilih')).toBeInViewport();
        await expect(panel.getByTestId('keadaan-terpilih')).toHaveAttribute('data-keadaan', 'draf');
        await expect(panel.getByTestId('alasan-draf-cetak')).toBeVisible();
        await expect(panel.locator('[data-lembar-mini]')).toHaveCount(3);
        await expect(panel.getByTestId('unduh-excel-terpilih')).toBeVisible();

        await panel.locator('[data-lembar-pilih="cover"]').click();
        await expect(panel.locator('[data-lembar-pilih="cover"]')).toHaveAttribute('aria-pressed', 'false');
        await expect(page).toHaveURL(/dokumen=kak(,|%2C)rab/);
        await expect(panel.locator('[data-lembar-mini]')).toHaveCount(2);

        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(lebar).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/cetak-unduh-${testInfo.project.name}.png`, fullPage: true });

        const [cetak] = await Promise.all([context.waitForEvent('page'), panel.getByTestId('cetak-terpilih').click()]);
        await cetak.waitForLoadState();
        await expect(cetak).toHaveURL(/\/kegiatan\/2\/cetak\?.*otomatis=1/);
        await expect(cetak.locator('[data-lembar-cetak]')).toHaveCount(2);
        await expect.poll(() => cetak.evaluate(() => window.__dicetak ?? 0)).toBeGreaterThan(0);
        await cetak.close();

        await page.getByTestId('saring-sumber').selectOption('Komite');
        await expect(daftar.locator('[data-kunci="2|DIPA"]')).toHaveCount(0);
        await expect(daftar.locator('[data-kunci="1|Komite"]')).toBeVisible();
    });

    test('tombol aksi cetak di Data Kegiatan & ruang kerja', async ({ page }) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan');
        const aksi = page.getByTestId('tabel-kegiatan').locator('[data-testid="aksi-cetak"][data-kegiatan="2"]');
        await aksi.getByTestId('buka-aksi-cetak').click();
        const menu = aksi.getByTestId('menu-aksi-cetak');
        await expect(menu).toBeVisible();
        await expect(menu.locator('[data-sumber-aksi]')).toHaveCount(2);
        await expect(menu.getByTestId('aksi-cetak-set-DIPA')).toHaveAttribute('href', /\/kegiatan\/2\/cetak\?.*otomatis=1/);
        await page.keyboard.press('Escape');
        await expect(menu).toBeHidden();
        await aksi.getByTestId('buka-aksi-cetak').click();
        await menu.getByTestId('aksi-atur-cetak').click();
        await expect(page).toHaveURL(/\/cetak\?pilih=2(%7C|\|)DIPA/);
        await expect(page.getByTestId('judul-terpilih')).toContainText('Supervisi');

        await page.goto('/kegiatan/1/rab');
        const tab = page.locator('[data-testid="aksi-cetak"][data-kegiatan="1"]');
        await tab.getByTestId('buka-aksi-cetak').click();
        await expect(tab.getByTestId('aksi-cetak-set-Komite')).toBeVisible();
        await expect(tab.getByTestId('aksi-rab-excel-Komite')).toBeVisible();
        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(lebar).toBeLessThanOrEqual(0);
    });

    test('cetak peramban bersih dari Pratinjau & Cetak dan Unduh (Ctrl+P)', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/2/pratinjau?sumber=DIPA');
        await expect(page.locator('[data-lembar="kak"]')).toBeVisible();
        await page.evaluate(() => window.dispatchEvent(new Event('beforeprint')));
        await page.emulateMedia({ media: 'print' });

        for (const id of ['panel-kaidah', 'bilah-pratinjau', 'kendali-penampil']) {
            await expect(page.getByTestId(id)).toBeHidden();
        }
        await expect(page.locator('header.sticky').first()).toBeHidden();
        await expect(page.getByRole('heading', { name: 'Pratinjau Dokumen', level: 1 })).toBeHidden();
        const kak = page.locator('[data-lembar="kak"]');
        await expect(kak).toHaveClass(/lembar-cetak/);
        expect(await kak.evaluate((el) => getComputedStyle(el).zoom)).toBe('1');
        await expect(page.locator('[data-tanda-air-cetak]')).toBeVisible();
        await expect(page.locator('[data-area-cetak] [data-testid="cap-draf"]').first()).toBeHidden();
        // Tabel RAB: kepala berulang & berlatar, total sekali di akhir, wadah tabel bukan wadah gulir.
        const tabelRab = page.locator('[data-lembar="rab"] [data-testid="tabel-rab-dipa"]');
        expect(await tabelRab.locator('thead').evaluate((el) => [getComputedStyle(el).display, getComputedStyle(el).printColorAdjust])).toEqual(['table-header-group', 'exact']);
        expect(await tabelRab.getByTestId('kaki-tabel-rab').evaluate((el) => getComputedStyle(el).display)).toBe('table-row-group');
        expect(await page.locator('#cetak-rab-rincian').evaluate((el) => getComputedStyle(el).overflowX)).toBe('visible');

        if (testInfo.project.name === 'desktop') {
            const pdf = await page.pdf({ preferCSSPageSize: true, path: 'storage/playwright/shots/cetak-peramban-pratinjau.pdf' });
            const halaman = (pdf.toString('latin1').match(/\/Type\s*\/Page[^s]/g) ?? []).length;
            expect(halaman).toBeGreaterThanOrEqual(4);
        }

        await page.emulateMedia({ media: 'screen' });
        await page.evaluate(() => window.dispatchEvent(new Event('afterprint')));
        await expect(page.getByTestId('panel-kaidah')).toBeVisible();

        await page.goto('/cetak?pilih=2%7CDIPA');
        await expect(page.getByTestId('judul-terpilih')).toBeVisible();
        await page.emulateMedia({ media: 'print' });
        await expect(page.getByTestId('daftar-cetak')).toBeHidden();
        await expect(page.getByTestId('aksi-cetak')).toBeHidden();
        const mini = page.locator('[data-lembar-wadah="kak"]');
        await expect(mini).toBeVisible();
        expect(await mini.evaluate((el) => getComputedStyle(el).zoom)).toBe('1');
        await expect(page.locator('[data-tanda-air-cetak]')).toBeVisible();
        await page.emulateMedia({ media: 'screen' });
    });

    test('unduh PDF langsung: nama berkas baku, kegagalan dengan jalan keluar', async ({ page }) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/cetak?pilih=2%7CDIPA');
        const panel = page.getByTestId('panel-cetak-terpilih');
        const nama = (await panel.getByTestId('nama-berkas-terpilih').textContent()).trim();

        const [unduhan] = await Promise.all([page.waitForEvent('download'), panel.getByTestId('unduh-pdf-set').click()]);
        expect(unduhan.suggestedFilename()).toBe(nama);
        await expect(panel.getByTestId('unduh-pdf-set')).toHaveAttribute('data-keadaan', /selesai|siap/);

        // RAB PDF lewat LibreOffice di server.
        const [rab] = await Promise.all([page.waitForEvent('download', { timeout: 60_000 }), panel.getByTestId('unduh-pdf-terpilih').click()]);
        expect(rab.suggestedFilename()).toMatch(/^RAB_.*_DIPA_2026(_DRAF)?\.pdf$/);

        // Server gagal menyusun: pesan, Coba lagi, dan jalan keluar lewat halaman cetak.
        await page.route('**/kegiatan/2/cetak/pdf**', (rute) => rute.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ pesan: 'Penyusun PDF sedang sibuk — coba lagi.', tautan_cetak: '/kegiatan/2/cetak?sumber=DIPA&otomatis=1' }) }));
        await panel.getByTestId('unduh-pdf-set').click();
        const gagal = panel.getByTestId('unduh-pdf-set-gagal');
        await expect(gagal).toContainText('Penyusun PDF sedang sibuk');
        await expect(gagal.getByRole('link', { name: 'Simpan lewat halaman cetak' })).toHaveAttribute('href', /\/kegiatan\/2\/cetak\?sumber=DIPA&otomatis=1/);
        await page.unroute('**/kegiatan/2/cetak/pdf**');
        const [ulang] = await Promise.all([page.waitForEvent('download'), gagal.getByRole('button', { name: 'Coba lagi' }).click()]);
        expect(ulang.suggestedFilename()).toBe(nama);
        await expect(gagal).toBeHidden();

        // Pratinjau: unduh PDF set dari panel Unduh.
        await page.goto('/kegiatan/1/pratinjau');
        await page.getByTestId('buka-unduh').click();
        const [pratinjau] = await Promise.all([page.waitForEvent('download'), page.getByTestId('panel-unduh').getByTestId('unduh-pdf-set').click()]);
        expect(pratinjau.suggestedFilename()).toMatch(/^KAK-TOR_RAB_.*_KOMITE_2026(_DRAF)?\.pdf$/);
    });

    test('unduh Word KAK/TOR disusun di peramban', async ({ page }) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/cetak?pilih=2%7CDIPA');
        const panel = page.getByTestId('panel-cetak-terpilih');
        const [unduhan] = await Promise.all([page.waitForEvent('download'), panel.getByTestId('unduh-word-terpilih').click()]);
        expect(unduhan.suggestedFilename()).toMatch(/^KAK-TOR_Supervisi_.*_DIPA_2026(_DRAF)?\.docx$/);
        const zip = await JSZip.loadAsync(fs.readFileSync(await unduhan.path()));
        const isi = await zip.file('word/document.xml').async('string');
        for (const teks of ['KERANGKA ACUAN KERJA', '1. LATAR BELAKANG', '9. LEMBAR PENGESAHAN', 'Menyetujui,', 'Supervisi Akademik']) {
            expect(isi).toContain(teks);
        }
        const kaki = Object.keys(zip.files).filter((nama) => nama.startsWith('word/footer'));
        expect(kaki.length).toBeGreaterThan(0);
        expect(await zip.file(kaki[0]).async('string')).toContain('Halaman');
        await expect(panel.getByTestId('unduh-word-terpilih')).toHaveAttribute('data-keadaan', /selesai|siap/);

        // Menu Cetak di tabel Data Kegiatan: Word per sumber dana.
        await page.goto('/kegiatan');
        const aksi = page.getByTestId('tabel-kegiatan').locator('[data-testid="aksi-cetak"][data-kegiatan="1"]');
        await aksi.getByTestId('buka-aksi-cetak').click();
        const [dariMenu] = await Promise.all([page.waitForEvent('download'), aksi.getByTestId('aksi-word-Komite').click()]);
        expect(dariMenu.suggestedFilename()).toMatch(/^KAK-TOR_.*_KOMITE_2026(_DRAF)?\.docx$/);
    });

    test('unduh Word: cadangan server bila penyusun peramban gagal dimuat', async ({ page }) => {
        await page.route('**/build/assets/word-kak-*.js', (rute) => rute.abort());
        await page.goto('/demo/peran/leading');
        await page.goto('/cetak?pilih=2%7CDIPA');
        const panel = page.getByTestId('panel-cetak-terpilih');
        const [unduhan] = await Promise.all([page.waitForEvent('download'), panel.getByTestId('unduh-word-terpilih').click()]);
        expect(unduhan.suggestedFilename()).toMatch(/^KAK-TOR_Supervisi_.*_DIPA_2026(_DRAF)?\.docx$/);
        const zip = await JSZip.loadAsync(fs.readFileSync(await unduhan.path()));
        const isi = await zip.file('word/document.xml').async('string');
        for (const teks of ['KERANGKA ACUAN KERJA', '1. LATAR BELAKANG', '9. LEMBAR PENGESAHAN', 'Supervisi Akademik']) {
            expect(isi).toContain(teks);
        }
        expect(await zip.file('word/footer2.xml').async('string')).toContain('NUMPAGES');
        await expect(page.getByTestId('toast').filter({ hasText: 'disusun server' })).toHaveAttribute('data-jenis', 'success');
        await expect(panel.getByTestId('unduh-word-terpilih-gagal')).toBeHidden();
    });

    test('unduh Excel RAB disusun di peramban', async ({ page }) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/cetak?pilih=2%7CDIPA');
        const panel = page.getByTestId('panel-cetak-terpilih');
        const [unduhan] = await Promise.all([page.waitForEvent('download'), panel.getByTestId('unduh-excel-terpilih').click()]);
        expect(unduhan.suggestedFilename()).toMatch(/^RAB_Supervisi_.*_DIPA_2026(_DRAF)?\.xlsx$/);
        const zip = await JSZip.loadAsync(fs.readFileSync(await unduhan.path()));
        const lembar = await zip.file('xl/worksheets/sheet1.xml').async('string');
        const teks = await zip.file('xl/sharedStrings.xml').async('string');
        expect(lembar).toMatch(/<f>SUM\([A-Z]+\d+:[A-Z]+\d+\)<\/f><v>5400000<\/v>/);
        expect(lembar).toMatch(/<f>ROUND\([A-Z]\d+\*[A-Z]\d+,0\)<\/f><v>450000<\/v>/);
        for (const isi of ['TOTAL KESELURUHAN', 'Terbilang: Lima Juta Empat Ratus Ribu Rupiah', 'Penggandaan instrumen supervisi akademik', 'Menyetujui,']) {
            expect(teks).toContain(isi);
        }
        expect(await zip.file('xl/workbook.xml').async('string')).toContain('RAB DIPA');

        // Menu Cetak di ruang kerja: Excel RAB per sumber dana.
        await page.goto('/kegiatan/1/rab');
        const tab = page.locator('[data-testid="aksi-cetak"][data-kegiatan="1"]');
        await tab.getByTestId('buka-aksi-cetak').click();
        const [dariMenu] = await Promise.all([page.waitForEvent('download'), tab.getByTestId('aksi-rab-excel-Komite').click()]);
        expect(dariMenu.suggestedFilename()).toMatch(/^RAB_.*_KOMITE_2026(_DRAF)?\.xlsx$/);
    });

    test('penanganan galat & notifikasi unduhan', async ({ page, context }) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/cetak?pilih=2%7CDIPA');
        const panel = page.getByTestId('panel-cetak-terpilih');
        const toast = page.getByTestId('toast');

        expect(await page.evaluate(() => [401, 419, 403, 404, 429, 500, 504].map((s) => window.SIRABTOR.jenisGalatUnduhan(s))))
            .toEqual(['sesi', 'sesi', 'akses', 'tidak_ada', 'sibuk', 'server', 'waktu_habis']);

        // Luring: pesan jelas di tempat + notifikasi galat; begitu daring lagi, Coba lagi berhasil.
        await context.setOffline(true);
        await panel.getByTestId('unduh-pdf-set').click();
        const gagalPdf = panel.getByTestId('unduh-pdf-set-gagal');
        await expect(gagalPdf).toHaveAttribute('data-galat', 'luring');
        await expect(gagalPdf).toContainText('Koneksi internet terputus');
        await expect(toast.filter({ hasText: 'Unduhan PDF gagal' })).toHaveAttribute('data-jenis', 'error');
        await context.setOffline(false);
        const [unduhan] = await Promise.all([page.waitForEvent('download'), gagalPdf.getByRole('button', { name: 'Coba lagi' }).click()]);
        await expect(toast.filter({ hasText: `${unduhan.suggestedFilename()} diunduh` })).toHaveAttribute('data-jenis', 'success');
        await expect(gagalPdf).toBeHidden();

        // Sesi berakhir (419): pesan baku + Muat ulang halaman.
        await page.route('**/kegiatan/2/pratinjau/data**', (rute) => rute.fulfill({ status: 419, contentType: 'application/json', body: JSON.stringify({ message: 'CSRF token mismatch.' }) }));
        await panel.getByTestId('unduh-word-terpilih').click();
        const gagalWord = panel.getByTestId('unduh-word-terpilih-gagal');
        await expect(gagalWord).toHaveAttribute('data-galat', 'sesi');
        await expect(gagalWord).toContainText('Sesi Anda sudah berakhir');
        await expect(gagalWord.getByTestId('unduh-word-terpilih-muat-ulang')).toBeVisible();
        await page.unroute('**/kegiatan/2/pratinjau/data**');

        // Pustaka penyusun gagal dimuat (mis. sesudah aplikasi diperbarui).
        await page.route('**/build/assets/excel-rab-*.js', (rute) => rute.abort());
        await panel.getByTestId('unduh-excel-terpilih').click();
        const gagalExcel = panel.getByTestId('unduh-excel-terpilih-gagal');
        await expect(gagalExcel).toHaveAttribute('data-galat', 'pustaka');
        await expect(gagalExcel.getByTestId('unduh-excel-terpilih-muat-ulang')).toBeVisible();
        await expect(gagalExcel.getByRole('link', { name: 'Unduh dari server' })).toHaveAttribute('href', /\/kegiatan\/2\/rab\/excel\?sumber=DIPA/);

        // Batal di tengah unduhan: tanpa pesan galat, notifikasi info.
        await page.route('**/kegiatan/2/rab/pdf**', async (rute) => {
            await new Promise((selesai) => setTimeout(selesai, 2500));
            await rute.fulfill({ status: 200, contentType: 'application/pdf', body: '%PDF-1.4' }).catch(() => {});
        });
        await panel.getByTestId('unduh-pdf-terpilih').click();
        await panel.getByTestId('unduh-pdf-terpilih-batal').click();
        await expect(panel.getByTestId('unduh-pdf-terpilih')).toHaveAttribute('data-keadaan', 'siap');
        await expect(panel.getByTestId('unduh-pdf-terpilih-gagal')).toBeHidden();
        await expect(toast.filter({ hasText: 'Unduhan PDF dibatalkan.' })).toHaveAttribute('data-jenis', 'info');
    });
});
