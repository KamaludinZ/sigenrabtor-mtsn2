import { expect, test } from '@playwright/test';

test.describe('Monitoring Anggaran', () => {
    test('layout: khusus admin, saringan di URL, kartu ringkasan, kerangka rekap', async ({ page }, testInfo) => {
        const ditolak = await page.goto('/monitoring');
        expect(ditolak.status()).toBe(403);

        await page.goto('/demo/peran/admin');
        await page.goto('/monitoring');
        await expect(page.getByRole('heading', { name: 'Monitoring Anggaran', level: 1 })).toBeVisible();
        const ringkasan = page.getByTestId('ringkasan-monitoring');
        await expect(ringkasan.locator('article')).toHaveCount(4);
        await expect(page.getByTestId('kartu-pagu')).toContainText('DIPA');
        await expect(page.getByTestId('kartu-pagu')).toContainText('Komite');
        await expect(page.locator('section[data-testid^="bagian-monitoring-"]')).toHaveCount(4);

        await page.getByTestId('saringan-monitoring').getByLabel('Sumber dana').selectOption('Komite');
        await expect(page).toHaveURL(/sumber=Komite/);
        await expect(page.getByTestId('saringan-aktif')).toContainText('Dana Komite');
        await expect(page.getByTestId('kartu-pagu').locator('dl > div')).toHaveCount(1);

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/monitoring-${testInfo.project.name}.png`, fullPage: true });

        await page.reload();
        await expect(page.getByTestId('saringan-aktif')).toContainText('Dana Komite');
        await page.getByRole('button', { name: 'Hapus semua' }).click();
        await expect(page.getByTestId('saringan-aktif')).toHaveCount(0);
    });
});
