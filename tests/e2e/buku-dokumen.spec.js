import { expect, test } from '@playwright/test';

test.describe('Buku dokumen', () => {
    test('layout: pilih leading & sumber dana, susunan buku, peta semua buku', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/dashboard');
        // Di layar sempit menu samping ada di laci yang dibuka tombol menu.
        const tombolMenu = page.getByRole('button', { name: 'Buka menu' });
        if (await tombolMenu.isVisible()) {
            await tombolMenu.click();
        }
        await page.getByRole('link', { name: 'Buku Dokumen' }).filter({ visible: true }).first().click();
        await expect(page).toHaveURL(/\/buku$/);
        await expect(page.getByRole('heading', { name: 'Buku Dokumen', level: 1 })).toBeVisible();

        const susunan = page.getByTestId('susunan-buku');
        await expect(susunan.getByRole('heading', { level: 2 })).toContainText('1.6 · Tim Penjamin Mutu — DIPA');
        await expect(susunan.locator('[data-bagian-buku]')).toHaveCount(2);
        await expect(susunan.locator('li[data-kegiatan]')).not.toHaveCount(0);
        await expect(page.getByTestId('nama-berkas-buku')).toContainText('TOR_RAB_Tim_Penjamin_Mutu_DIPA_2026.pdf');
        // Halaman daftar kegiatan: baris sama dengan susunan buku, kolom Halaman berisi halaman awal perkiraan.
        const daftarIsi = page.getByTestId('halaman-daftar-isi').getByTestId('pratinjau-daftar-isi');
        await expect(daftarIsi.getByTestId('di-judul-tercetak')).toHaveText('DAFTAR KEGIATAN');
        await expect(daftarIsi.locator('[data-baris-daftar-isi]')).toHaveCount(await susunan.locator('li[data-kegiatan]').count());
        await expect(daftarIsi.locator('[data-halaman-tercetak]').first()).toHaveText(/^\s*\d+\s*$/);
        await expect(daftarIsi.getByTestId('nomor-halaman-daftar-isi')).toContainText('Halaman');

        const cover = page.getByTestId('cover-buku');
        await expect(cover.locator('[data-field="judul"]').first()).toHaveText('KEGIATAN');
        await expect(cover.locator('[data-field="nama_kegiatan"]').first()).toHaveText('TIM PENJAMIN MUTU');
        await cover.getByTestId('perbesar-cover-buku').click();
        await expect(page.getByTestId('cover-buku-besar')).toBeVisible();
        await page.screenshot({ path: `storage/playwright/shots/cover-buku-${testInfo.project.name}.png` });
        await page.keyboard.press('Escape');
        await expect(page.getByTestId('cover-buku-besar')).toBeHidden();

        await page.getByTestId('parameter-buku').getByRole('group', { name: 'Sumber dana' }).getByRole('button', { name: 'Komite' }).click();
        await expect(page).toHaveURL(/sumber=Komite/);
        await expect(susunan.getByRole('heading', { level: 2 })).toContainText('— Komite');
        await expect(cover.locator('[data-field="sumber_anggaran"]').first()).toHaveText('SUMBER DANA KOMITE');

        const peta = page.getByTestId('peta-buku');
        await expect(peta.locator('[data-buku]')).toHaveCount(2);
        await peta.locator('[data-buku="1.6|DIPA"]').click();
        await expect(susunan.getByRole('heading', { level: 2 })).toContainText('— DIPA');

        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(lebar).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/buku-dokumen-${testInfo.project.name}.png`, fullPage: true });

        // Susun buku: kegiatan diambil bertahap, hasilnya tercatat di riwayat buku ini.
        const riwayat = page.getByTestId('riwayat-buku').locator('[data-riwayat-buku]');
        const sebelum = await riwayat.count();
        await page.getByTestId('susun-buku').click();
        const proses = page.getByTestId('proses-buku');
        await expect(proses).toHaveAttribute('data-tahap', 'selesai', { timeout: 30_000 });
        await expect(proses.getByTestId('ringkasan-susun')).toContainText(/\d+ dari \d+ kegiatan diambil/);
        await expect(proses.getByTestId('log-susun').locator('[data-hasil]')).not.toHaveCount(0);
        await expect(riwayat).toHaveCount(Math.min(sebelum + 1, 5));
        await proses.getByRole('button', { name: 'Tutup' }).click();
        await expect(proses).toHaveCount(0);

        // Gabung PDF: status dipantau berkala sampai selesai (unduh) atau gagal (data E2E bersama bisa berisi kegiatan
        // tanpa rincian RAB dari tes lain → pesan & Coba lagi).
        await page.getByTestId('gabung-pdf').click();
        const statusPdf = page.getByTestId('status-pdf');
        // Tunggu pekerjaan baru berjalan dulu (kartu pekerjaan sebelumnya dari proyek lain bisa masih tampil).
        await expect(statusPdf).toHaveAttribute('data-status', /^(antre|proses)$/);
        await expect(statusPdf.getByTestId('tahap-pdf').locator('[data-tahap]')).toHaveCount(5);
        await expect(statusPdf).toHaveAttribute('data-status', /^(selesai|gagal)$/, { timeout: 30_000 });
        if ((await statusPdf.getAttribute('data-status')) === 'selesai') {
            const [unduhan] = await Promise.all([page.waitForEvent('download'), statusPdf.getByTestId('unduh-pdf-buku').click()]);
            expect(unduhan.suggestedFilename()).toBe('TOR_RAB_Tim_Penjamin_Mutu_DIPA_2026.pdf');
        } else {
            await expect(statusPdf.getByTestId('pesan-pdf')).toContainText('belum berisi rincian');
            await expect(statusPdf.getByTestId('ulangi-pdf-buku')).toBeVisible();
        }

        // Dokumen satu kegiatan di dalam buku: cover, KAK/TOR, RAB dengan rentang halaman buku; pindah ke kegiatan berikutnya.
        const pertama = await susunan.locator('li[data-kegiatan]').first().getAttribute('data-kegiatan');
        await susunan.locator(`[data-lihat-kegiatan="${pertama}"]`).click();
        await expect(page).toHaveURL(new RegExp(`kegiatan=${pertama}`));
        const lihat = page.getByTestId('lihat-kegiatan-buku');
        await expect(lihat).toContainText(/Kegiatan 1 dari \d+ di buku ini/);
        await expect(lihat.locator('[data-lembar]')).toHaveCount(3);
        await expect(lihat.locator('[data-lembar="kak"] [data-rentang]')).toHaveText(/^hal\. \d+–\d+$/);
        await expect(lihat).toBeInViewport();
        if (await lihat.getByRole('button', { name: 'Kegiatan berikutnya' }).count()) {
            await lihat.getByRole('button', { name: 'Kegiatan berikutnya' }).click();
            await expect(lihat).toContainText(/Kegiatan 2 dari \d+ di buku ini/);
        }
        await lihat.getByRole('button', { name: 'Tutup dokumen kegiatan' }).click();
        await expect(lihat).toHaveCount(0);

        // Kegiatan di buku membuka pratinjau dokumennya untuk sumber dana buku itu.
        await susunan.locator('li[data-kegiatan] a').first().click();
        await expect(page).toHaveURL(/\/kegiatan\/\d+\/pratinjau\?sumber=DIPA$/);
    });

    test('admin: semua leading, buku tanpa kegiatan', async ({ page }) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/buku');
        await page.getByTestId('parameter-buku').getByLabel('Leading').selectOption('1.1');
        await expect(page).toHaveURL(/leading=1\.1/);
        await expect(page.getByTestId('buku-tanpa-kegiatan')).toBeVisible();
        await expect(page.getByTestId('peta-buku').locator('[data-buku]')).toHaveCount(16);
        await page.goto('/demo/peran/leading');
    });
});
