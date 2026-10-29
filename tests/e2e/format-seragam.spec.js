import { expect, test } from '@playwright/test';

test.describe('Format Seragam', () => {
    test('tata letak: ringkasan, susunan baku, penomoran, pilihan template', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/format');
        await expect(page.getByRole('heading', { name: 'Format Seragam', level: 1 })).toBeVisible();
        const ringkasan = page.getByTestId('ringkasan-format');
        await expect(ringkasan.locator('[data-unsur]')).toHaveCount(4);
        await expect(ringkasan.locator('[data-unsur="susunan"]')).toHaveAttribute('data-status', 'siap');
        await expect(page.getByTestId('kop-format')).toContainText('Madrasah Tsanawiyah Negeri 2 Kota Malang');
        await page.screenshot({ path: `storage/playwright/shots/format-seragam-${testInfo.project.name}.png`, fullPage: true });

        await ringkasan.locator('[data-unsur="penomoran"]').getByRole('button', { name: /Lihat aturan/ }).click();
        await expect(page).toHaveURL(/tab=penomoran/);
        const mobile = testInfo.project.name === 'mobile';
        const aturan = page.locator(`[data-testid="${mobile ? 'kartu-aturan-nomor' : 'aturan-nomor'}"][data-jenis="KAK+TOR"]`);
        await expect(aturan).toContainText(/KAK-TOR\/032\/MTsN2KotaMalang\/[IVX]+\/2026/);
        await expect(page.getByTestId('penanda-nomor')).toContainText('{urut}');
        await page.screenshot({ path: `storage/playwright/shots/format-penomoran-${testInfo.project.name}.png`, fullPage: true });

        await page.getByTestId('tab-format').locator('[data-tab="susunan"]').click();
        await expect(page.getByTestId('susunan-kak').locator('[data-bagian]')).toHaveCount(9);
        await expect(page.getByTestId('susunan-rab-kolom')).toContainText('Jumlah (Rp)');

        await page.getByTestId('tab-format').locator('[data-tab="template"]').click();
        await expect(page.getByTestId('template-jenis')).toHaveCount(5);
        await expect(page.locator('[data-testid="template-leading"][data-leading="1.6"]')).toContainText('Master KAK/TOR Penjaminan Mutu');

        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(lebar).toBeLessThanOrEqual(0);
    });
});
