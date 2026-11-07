import { expect, test } from '@playwright/test';

test.describe('Master kode & tanggal dokumen leading', () => {
    test('master kode: unduh template dan tab rencana aksi', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/master');
        await expect(page.getByRole('heading', { name: 'Master Kode', level: 1 })).toBeVisible();
        await expect(page.getByText('Sedang disiapkan')).toHaveCount(0);

        await page.getByTestId('unduh-template-master').click();
        const menu = page.getByTestId('menu-template-master');
        await expect(menu.getByTestId('template-berisi')).toBeVisible();
        const [unduhan] = await Promise.all([page.waitForEvent('download'), menu.getByTestId('template-kosong').click()]);
        expect(unduhan.suggestedFilename()).toBe('Template_Master_Kode_TA2026.xlsx');

        await page.getByRole('tab', { name: /Rencana Aksi/ }).click();
        await expect(page).toHaveURL(/jenis=rencana_aksi/);
        await expect(page.getByTestId('jenis-kosong')).toContainText('Belum ada rencana aksi');

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/master-kode-${testInfo.project.name}.png`, fullPage: true });
    });

    test('tab pejabat: tanggal dokumen per leading disimpan lalu dikosongkan', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/madrasah?tab=pejabat');
        const kartu = page.getByTestId('tanggal-dokumen-leading');
        await expect(kartu.locator('li[data-tanggal-leading]')).toHaveCount(8);
        const baris = kartu.locator('li[data-tanggal-leading="1.8"]');
        await expect(baris).toHaveAttribute('data-tanggal', '');

        await baris.getByTestId('tanggal-dokumen-1.8').fill('2026-01-07');
        await expect(kartu.locator('li[data-tanggal-leading="1.8"]')).toHaveAttribute('data-tanggal', '2026-01-07');
        await expect(kartu.locator('li[data-tanggal-leading="1.8"]')).toContainText('7 Januari 2026');
        await kartu.screenshot({ path: `storage/playwright/shots/tanggal-dokumen-leading-${testInfo.project.name}.png` });

        // Pulihkan: data E2E dipakai bersama proyek desktop & mobile.
        await kartu.getByTestId('kosongkan-tanggal-1.8').click();
        await expect(kartu.locator('li[data-tanggal-leading="1.8"]')).toHaveAttribute('data-tanggal', '');
    });
});
