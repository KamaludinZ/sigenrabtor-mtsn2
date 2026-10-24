import { expect, test } from '@playwright/test';

test.describe('Halaman RAB (data tiruan)', () => {
    test('header, rincian, total, dan ringkasan pagu tampil', async ({ page }, testInfo) => {
        await page.goto('/kegiatan/1/rab');
        await expect(page.getByRole('heading', { name: 'Rencana Anggaran Biaya' })).toBeVisible();
        await expect(page.getByRole('tab', { name: /RAB Komite/ })).toHaveAttribute('aria-selected', 'true');
        await expect(page.getByTestId('header-rab')).toContainText('Tim Penjamin Mutu');
        await expect(page.getByTestId('header-rab')).toContainText('6.17.12');
        await expect(page.getByTestId('ringkasan-rab')).toContainText('Rp10.000.000');
        await expect(page.getByTestId('ringkasan-rab')).toContainText('Sisa pagu');
        if (testInfo.project.name === 'desktop') {
            await expect(page.getByTestId('total-rab')).toHaveText('10.000.000');
        }

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/rab-${testInfo.project.name}.png`, fullPage: true });
    });

    test('tab ruang kerja berpindah antara Data Kegiatan dan RAB', async ({ page }) => {
        await page.goto('/kegiatan/1/edit');
        await page.getByRole('link', { name: 'RAB', exact: true }).click();
        await expect(page).toHaveURL(/\/kegiatan\/1\/rab/);
        await page.getByRole('link', { name: 'Data Kegiatan' }).last().click();
        await expect(page).toHaveURL(/\/kegiatan\/1\/edit$/);
    });
});
