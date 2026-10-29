import { expect, test } from '@playwright/test';

test.describe('Riwayat Dokumen', () => {
    test('layout: ringkasan, kelompok per tahun, tabel/kartu (kronologis)', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/dokumen?tampilan=kronologis');
        await expect(page.getByRole('heading', { name: 'Riwayat Dokumen', level: 1 })).toBeVisible();
        await expect(page.getByTestId('ringkasan-riwayat').locator('[data-ringkasan="arsip"]')).toContainText('1');

        const tahun = page.getByTestId('riwayat-tahun');
        await expect(tahun).toHaveCount(2);
        await expect(tahun.first()).toHaveAttribute('data-tahun', '2026');
        const mobile = testInfo.project.name === 'mobile';
        const butir = page.getByTestId(mobile ? 'kartu-riwayat' : 'dokumen-riwayat');
        await expect(butir.first()).toBeVisible();
        await expect(page.getByTestId(mobile ? 'dokumen-riwayat' : 'kartu-riwayat').first()).toBeHidden();
        await expect(page.locator(`[data-testid="${mobile ? 'kartu-riwayat' : 'dokumen-riwayat'}"][data-id="rd-2025-5"]`)).toContainText(mobile ? 'Lomba Kreativitas Siswa' : 'KAK/031/2025');

        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(lebar).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/riwayat-dokumen-${testInfo.project.name}.png`, fullPage: true });

    });

    test('tampilan per kegiatan & kronologis', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/dokumen');
        await expect(page.getByTestId('tampilan-riwayat')).toHaveAttribute('data-tampilan', 'kegiatan');
        const lomba = page.getByTestId('kegiatan-riwayat').filter({ hasText: 'Lomba Kreativitas Siswa Akhir Tahun' });
        await expect(lomba.getByTestId('kegiatan-tidak-tersimpan')).toBeVisible();
        await expect(lomba.getByTestId('dokumen-kegiatan')).toHaveAttribute('data-versi', '2');
        await expect(lomba.getByTestId('versi-sebelumnya')).toBeHidden();
        await lomba.getByTestId('versi-lama').locator('summary').click();
        await expect(lomba.getByTestId('versi-sebelumnya')).toHaveAttribute('data-versi', '1');
        await expect(page.getByTestId('kegiatan-riwayat').filter({ hasText: 'Supervisi Akademik' }).getByTestId('buka-kegiatan-riwayat')).toHaveAttribute('href', /\/kegiatan\/2\/edit$/);
        await page.screenshot({ path: `storage/playwright/shots/riwayat-per-kegiatan-${testInfo.project.name}.png`, fullPage: true });

        await page.getByTestId('tampilan-riwayat').locator('[data-pilih-tampilan="kronologis"]').click();
        await expect(page).toHaveURL(/tampilan=kronologis/);
        await page.getByTestId('urut-riwayat').selectOption('terlama');
        await expect(page).toHaveURL(/urut=terlama/);
        await expect(page.getByTestId('riwayat-tahun').first()).toHaveAttribute('data-tahun', '2025');
        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(lebar).toBeLessThanOrEqual(0);
    });
});
