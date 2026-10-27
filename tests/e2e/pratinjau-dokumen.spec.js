import { expect, test } from '@playwright/test';

test.describe('Pratinjau dokumen', () => {
    test('layout: cover, KAK/TOR, RAB per sumber dana, panel kaidah dokumen', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/2/edit');
        await page.getByRole('link', { name: 'Pratinjau Dokumen', exact: true }).click();
        await expect(page).toHaveURL(/\/kegiatan\/2\/pratinjau$/);
        await expect(page.getByRole('heading', { name: 'Pratinjau Dokumen', level: 1 })).toBeVisible();

        const dokumen = page.getByTestId('dokumen-pratinjau');
        await expect(dokumen.getByTestId('pratinjau-cover')).toContainText('SUMBER DANA DIPA');
        await expect(dokumen.getByTestId('pratinjau-kak')).toBeVisible();
        await expect(dokumen.getByTestId('tabel-rab-dipa')).toBeVisible();
        await expect(page.getByTestId('keadaan-cetak')).toBeVisible();

        const kaidah = page.getByTestId('panel-kaidah');
        await expect(kaidah.locator('[data-kaidah]')).toHaveCount(9);
        await expect(kaidah.locator('[data-kaidah="total"]')).toHaveAttribute('data-status', 'lulus');
        await expect(kaidah.locator('[data-kaidah="total"]')).toContainText('Rp5.400.000,-');

        await page.getByTestId('bilah-pratinjau').getByRole('group', { name: 'Sumber dana' }).getByRole('button', { name: 'Komite' }).click();
        await expect(page).toHaveURL(/sumber=Komite/);
        await expect(dokumen.getByTestId('pratinjau-cover')).toContainText('SUMBER DANA KOMITE');
        await expect(dokumen.getByTestId('tabel-rab-komite')).toBeVisible();
        await expect(kaidah.locator('[data-kaidah="total"]')).toContainText('Rp2.500.000,-');

        await page.getByRole('navigation', { name: 'Dokumen' }).getByRole('link', { name: 'RAB' }).click();
        await expect(page.locator('#dok-rab')).toBeInViewport();

        // Rupiah & terbilang: tiap tempat cetak menautkan letaknya di dokumen.
        const tempat = kaidah.getByTestId('sinkron-dokumen');
        await expect(tempat.getByTestId('sinkron-dokumen-status')).toHaveText('Sinkron');
        await tempat.locator('[data-tempat="rab-diajukan"]').getByRole('link').click();
        await expect(page).toHaveURL(/sumber=Komite#cetak-rab-diajukan$/);
        await expect(page.locator('#cetak-rab-diajukan')).toBeInViewport();
        await expect(page.locator('#cetak-rab-diajukan')).toContainText('Rp2.500.000,- (Dua Juta Lima Ratus Ribu Rupiah)');
        await tempat.locator('[data-tempat="kak-pembiayaan"]').getByRole('link').click();
        await expect(page.locator('#cetak-kak-pembiayaan')).toBeInViewport();
        await expect(page.locator('#cetak-kak-pembiayaan')).toContainText('Rp2.500.000,- (Dua Juta Lima Ratus Ribu Rupiah)');

        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(lebar).toBeLessThanOrEqual(0);
        await page.goto('/kegiatan/2/pratinjau?sumber=Komite');
        await page.screenshot({ path: `storage/playwright/shots/pratinjau-dokumen-${testInfo.project.name}.png`, fullPage: true });
    });

    test('kaidah: RAB yang melebihi pagu ditandai gagal', async ({ page }) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/3/pratinjau?sumber=Komite');
        const pagu = page.getByTestId('panel-kaidah').locator('[data-kaidah="pagu"]');
        await expect(pagu).toHaveAttribute('data-status', 'gagal');
        await expect(pagu).toContainText('Melebihi pagu Rp450.000.');
        await expect(page.getByTestId('ringkasan-kaidah')).toContainText('perlu perhatian');
    });

    test('halaman RAB membuka letak tempat cetak di pratinjau', async ({ page }) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/1/rab');
        await page.getByTestId('sinkron-dokumen').locator('[data-tempat="rab-total"]').getByRole('link').click();
        await expect(page).toHaveURL(/\/kegiatan\/1\/pratinjau\?sumber=Komite#cetak-rab-total$/);
        await expect(page.locator('#cetak-rab-total')).toBeInViewport();
        await expect(page.locator('#cetak-rab-total')).toContainText('10.000.000');
    });
});
