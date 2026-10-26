import { expect, test } from '@playwright/test';

test.describe('Dashboard', () => {
    test('halaman utama: ringkasan, pemakaian pagu, status dokumen, kegiatan per leading', async ({ page }, testInfo) => {
        await page.goto('/');
        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.getByRole('heading', { name: 'Dashboard', level: 1 })).toBeVisible();
        await expect(page.getByTestId('cakupan-dashboard')).toContainText('1.6 · Tim Penjamin Mutu');
        await expect(page.getByTestId('ringkasan-monitoring').locator('article')).toHaveCount(4);
        await expect(page.getByTestId('pemakaian-pagu').getByRole('meter')).toHaveCount(2);
        await expect(page.getByTestId('status-dokumen').locator('a[data-status-dokumen]')).toHaveCount(5);

        // Akun leading hanya melihat leading-nya sendiri.
        const baris = page.getByTestId('kegiatan-per-leading').locator('tbody tr');
        await expect(baris).toHaveCount(1);
        await expect(baris.first()).toHaveAttribute('data-leading', '1.6');
        await expect(page.getByRole('link', { name: 'Monitoring Anggaran' })).toHaveCount(0);

        let overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/dashboard-leading-${testInfo.project.name}.png`, fullPage: true });

        await page.getByTestId('status-dokumen').locator('a[data-status-dokumen="draf"]').click();
        await expect(page).toHaveURL(/\/kegiatan\?status=draf/);

        await page.goto('/demo/peran/admin');
        await page.goto('/dashboard');
        await expect(page.getByTestId('cakupan-dashboard')).toContainText('Semua leading');
        expect(await baris.count()).toBeGreaterThan(1);
        await expect(page.getByTestId('kegiatan-per-leading').locator('tbody tr[data-leading="1.6"]')).toBeVisible();
        await expect(page.getByRole('link', { name: 'Monitoring Anggaran' }).last()).toBeVisible();

        overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/dashboard-admin-${testInfo.project.name}.png`, fullPage: true });
    });
});
