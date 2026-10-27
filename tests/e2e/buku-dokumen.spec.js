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
