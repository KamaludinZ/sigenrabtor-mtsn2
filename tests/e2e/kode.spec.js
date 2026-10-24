import { expect, test } from '@playwright/test';

test.describe('Kode & Klasifikasi', () => {
    test.afterEach(async ({ page }) => {
        await page.goto('/demo/peran/leading');
    });

    test('pemilih leading: akun leading satu kartu, admin semua & bisa memilih', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/1/kode');
        await expect(page.locator('[data-leading]')).toHaveCount(1);
        await expect(page.getByText('Akun Anda terikat ke leading ini.')).toBeVisible();

        await page.goto('/demo/peran/admin');
        await page.goto('/kegiatan/1/kode');
        await expect(page.locator('[data-leading]')).toHaveCount(8);
        await page.locator('[data-leading="1.4"]').click();
        await expect(page.getByRole('radio', { name: /Waka Humas/ })).toBeChecked();
        await expect(page.getByTestId('pratinjau-header')).toContainText('Waka Humas');
        await page.screenshot({ path: `storage/playwright/shots/pilih-leading-${testInfo.project.name}.png`, fullPage: true });

        // Kembalikan pilihan agar data contoh tetap seperti semula untuk tes lain.
        await page.locator('[data-leading="1.6"]').click();
        await expect(page.getByTestId('pratinjau-header')).toContainText('Tim Penjamin Mutu');
    });
});
