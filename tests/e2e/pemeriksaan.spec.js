import { expect, test } from '@playwright/test';

test.describe('Pemeriksaan', () => {
    test('layout: khusus admin, tab status dengan jumlah, cari & saringan di URL', async ({ page }, testInfo) => {
        const ditolak = await page.goto('/pemeriksaan');
        expect(ditolak.status()).toBe(403);

        await page.goto('/demo/peran/admin');
        await page.goto('/pemeriksaan');
        await expect(page.getByRole('heading', { name: 'Pemeriksaan', level: 1 })).toBeVisible();
        const tab = page.getByTestId('tab-pemeriksaan').getByRole('button');
        await expect(tab).toHaveCount(5);
        await expect(tab.first()).toHaveAttribute('aria-pressed', 'true');
        await expect(page.getByRole('heading', { name: 'Perlu diperiksa', level: 2 })).toBeVisible();

        // Data E2E dipakai bersama proyek lain: cukup pastikan contoh kegiatan ada di tab Belum diajukan.
        await page.getByTestId('tab-pemeriksaan').locator('[data-tab="draf"]').click();
        await expect(page).toHaveURL(/status=draf/);
        await expect(page.getByRole('heading', { name: 'Belum diajukan', level: 2 })).toBeVisible();
        const daftar = page.getByTestId('daftar-pemeriksaan');
        await expect(daftar.locator('li[data-kegiatan="1"]')).toBeVisible();
        await expect(daftar.locator('li[data-kegiatan="3"]').locator('[data-status-pagu="melebihi"]')).toBeVisible();

        await daftar.getByRole('searchbox', { name: 'Cari kegiatan' }).fill('Supervisi Akademik');
        await expect(page).toHaveURL(/cari=Supervisi/);
        await expect(daftar.locator('li[data-kegiatan="2"]')).toBeVisible();
        await expect(daftar.locator('li[data-kegiatan="1"]')).toHaveCount(0);

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/pemeriksaan-${testInfo.project.name}.png`, fullPage: true });

        await daftar.getByRole('searchbox', { name: 'Cari kegiatan' }).fill('tidak ada kegiatan seperti ini');
        await expect(page.getByTestId('pemeriksaan-kosong')).toContainText('Tidak ada kegiatan yang cocok.');
        await page.getByRole('button', { name: 'Hapus saringan' }).click();
        await expect(page).not.toHaveURL(/cari=/);
        await expect(daftar.locator('li[data-kegiatan="1"]')).toBeVisible();

        await page.goto('/demo/peran/leading');
    });
});
