import { expect, test } from '@playwright/test';

test.describe('Matrik & Jadwal Kegiatan', () => {
    test('tab ruang kerja membuka halaman: acuan bulan pelaksanaan, matrik tahapan, dan 6 baris jadwal', async ({ page }, testInfo) => {
        await page.goto('/kegiatan/1/edit');
        await page.getByRole('link', { name: 'Matrik & Jadwal' }).click();
        await expect(page).toHaveURL(/\/kegiatan\/1\/matrik$/, { timeout: 30_000 });
        await expect(page.getByRole('heading', { name: 'Matrik & Jadwal Kegiatan' })).toBeVisible();

        await expect(page.getByTestId('acuan-pelaksanaan')).toContainText('Bulan pelaksanaan Juli 2026');
        const matrik = page.getByTestId('matrik-tahapan');
        await expect(matrik.locator('tbody[data-tahapan]')).toHaveCount(3);
        await expect(matrik.locator('thead th[data-bulan]')).toHaveCount(12);
        await expect(matrik.locator('thead th[data-bulan="7"]')).toHaveClass(/bg-brand-50/);
        await expect(matrik.getByTestId('butir-persiapan-1')).toContainText('Rapat dan pembagian job deskripsi');

        const jadwal = page.getByTestId('jadwal-kegiatan');
        await expect(jadwal.locator('tbody tr')).toHaveCount(6);
        await expect(page.getByTestId('ringkasan-jadwal')).toHaveText('0 dari 6 baris terisi');
        await expect(page.getByTestId('kelengkapan-matrik')).toHaveAttribute('data-lengkap', 'tidak');

        // Tabel matrik digulir di dalam kartunya; halaman tidak bergeser ke samping di layar sempit.
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/matrik-jadwal-${testInfo.project.name}.png`, fullPage: true });

        await page.getByRole('link', { name: 'Ubah di Data Kegiatan' }).click();
        await expect(page).toHaveURL(/\/kegiatan\/1\/edit#pelaksanaan$/, { timeout: 30_000 });
    });
});
