import { expect, test } from '@playwright/test';

test.describe('Riwayat Dokumen', () => {
    test('layout: ringkasan, kelompok per tahun, tabel/kartu', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/dokumen');
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
});
