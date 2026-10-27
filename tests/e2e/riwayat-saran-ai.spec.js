import { expect, test } from '@playwright/test';

test.describe('Riwayat Saran AI', () => {
    test('dari Kalimat KAK: riwayat kegiatan, saringan bagian, bahan terkirim, tanpa gulir mendatar', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/1/kalimat');
        await page.getByTestId('tautan-riwayat-saran').click();
        await expect(page).toHaveURL(/\/riwayat-saran-ai\?kegiatan=1$/);
        await expect(page.getByRole('heading', { name: 'Riwayat Saran AI', level: 1 })).toBeVisible();
        await expect(page.getByTestId('ringkasan-saran-ai').locator('dt')).toHaveCount(4);

        // Data E2E bertambah dari tes Kalimat KAK: cocokkan dengan pola, bukan jumlah pasti.
        const kartu = page.getByTestId('daftar-saran-ai').locator('[data-permintaan]');
        await expect(kartu.first()).toBeVisible();
        expect(await kartu.count()).toBeGreaterThanOrEqual(2);

        await page.getByLabel('Bagian').selectOption('sasaran');
        await expect(page).toHaveURL(/bagian=sasaran/);
        await expect(kartu.first()).toHaveAttribute('data-bagian', 'sasaran');
        expect(await page.locator('[data-permintaan]:not([data-bagian="sasaran"])').count()).toBe(0);

        await kartu.first().getByText('Bahan yang dikirim ke AI').click();
        await expect(kartu.first().getByTestId('prompt-saran')).toContainText('Bagian: Sasaran');

        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(lebar).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/riwayat-saran-ai-${testInfo.project.name}.png`, fullPage: true });
    });
});
