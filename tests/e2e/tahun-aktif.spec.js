import { expect, test } from '@playwright/test';

test.describe('Kontrol tahun aktif', () => {
    test('leading: melihat apa saja yang mengikuti tahun aktif, tanpa ganti tahun', async ({ page }) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan');
        await page.getByTestId('chip-tahun-aktif').click();
        const panel = page.getByTestId('panel-tahun-aktif');
        await expect(panel).toBeVisible();
        await expect(panel.getByTestId('dipakai-tahun').locator('[data-dipakai]')).toHaveCount(6);
        await expect(panel.getByTestId('ganti-tahun')).toHaveCount(0);
        await expect(panel).toContainText('Tahun anggaran aktif diatur admin.');
        const kotak = await panel.boundingBox();
        expect(kotak.x).toBeGreaterThanOrEqual(0);
        expect(kotak.x + kotak.width).toBeLessThanOrEqual(page.viewportSize().width);
        await page.keyboard.press('Escape');
        await expect(panel).toBeHidden();
    });

    test('admin: ganti tahun aktif dari bilah atas lewat konfirmasi, halaman yang sama dimuat ulang', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/kegiatan');
        const chip = page.getByTestId('chip-tahun-aktif');
        const panel = page.getByTestId('panel-tahun-aktif');

        try {
            await chip.click();
            await panel.getByRole('button', { name: 'Aktifkan Tahun Anggaran 2025' }).click();
            const konfirmasi = panel.getByTestId('konfirmasi-ganti-tahun');
            await expect(konfirmasi).toContainText('Aktifkan Tahun Anggaran 2025?');
            await page.screenshot({ path: `storage/playwright/shots/tahun-aktif-${testInfo.project.name}.png` });
            await konfirmasi.getByRole('button', { name: 'Ya, aktifkan 2025' }).click();
            await expect(page.getByText('Tahun Anggaran 2025 aktif. Seluruh dokumen kini memakai tahun 2025.')).toBeVisible({ timeout: 30_000 });
            await expect(page).toHaveURL(/\/kegiatan$/);
            await expect(chip).toContainText('2025');
        } finally {
            // Kembalikan 2026 agar tes lain tetap memakai tahun contoh.
            await page.goto('/kegiatan');
            if (!(await chip.textContent()).includes('2026')) {
                await chip.click();
                await panel.getByRole('button', { name: 'Aktifkan Tahun Anggaran 2026' }).click();
                await panel.getByTestId('konfirmasi-ganti-tahun').getByRole('button', { name: 'Ya, aktifkan 2026' }).click();
                await expect(chip).toContainText('2026', { timeout: 30_000 });
            }
        }
        await page.goto('/demo/peran/leading');
    });
});
