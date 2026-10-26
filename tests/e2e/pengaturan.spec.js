import { expect, test } from '@playwright/test';

test.describe('Pengaturan admin', () => {
    test('ikhtisar: khusus admin, peta bagian & kesiapan tahun anggaran', async ({ page }, testInfo) => {
        // Peran bawaan data tiruan: leading → ditolak.
        const ditolak = await page.goto('/pengaturan');
        expect(ditolak.status()).toBe(403);

        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan');
        await expect(page.getByRole('heading', { name: 'Pengaturan', level: 1 })).toBeVisible();
        await expect(page.getByTestId('kepala-pengaturan')).toContainText('Berlaku TA 2026');

        const kesiapan = page.getByTestId('kesiapan-pengaturan');
        await expect(kesiapan).toContainText('Kesiapan Tahun Anggaran 2026');
        await expect(page.getByTestId('jumlah-wajib-siap')).toHaveText('4/7 wajib siap');
        await expect(kesiapan.locator('li[data-siap="tidak"]')).toHaveCount(6);
        await expect(page.locator('article[data-testid^="bagian-"]')).toHaveCount(6);
        await expect(page.getByTestId('bagian-tahun')).toContainText('Segera disiapkan');

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);

        if (testInfo.project.name === 'mobile') {
            // Layar sempit: kesiapan (yang perlu dikerjakan) tampil sebelum peta bagian.
            const atasKesiapan = (await kesiapan.boundingBox()).y;
            const atasBagian = (await page.getByTestId('bagian-tahun').boundingBox()).y;
            expect(atasKesiapan).toBeLessThan(atasBagian);
        } else {
            const menu = page.getByRole('navigation', { name: 'Menu utama' }).first();
            await expect(menu.getByRole('link', { name: 'Ikhtisar Pengaturan' })).toHaveAttribute('aria-current', 'page');
        }

        // Butir kesiapan tanpa halaman menuju kartu bagiannya.
        await kesiapan.getByRole('link', { name: 'Master kode 2026' }).click();
        await expect(page).toHaveURL(/#bagian-master$/);
        await page.screenshot({ path: `storage/playwright/shots/pengaturan-ikhtisar-${testInfo.project.name}.png`, fullPage: true });
    });
});
