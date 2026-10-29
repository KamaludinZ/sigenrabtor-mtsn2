import { expect, test } from '@playwright/test';

test.describe('Masuk & Akun', () => {
    test('masuk: galat surel, akun contoh, akun saya, keluar', async ({ page }, testInfo) => {
        await page.goto('/masuk');
        await expect(page.getByRole('heading', { name: 'Masuk', level: 1 })).toBeVisible();
        const mobile = testInfo.project.name === 'mobile';
        await expect(page.getByTestId('panel-merek')).toBeVisible({ visible: ! mobile });
        await page.screenshot({ path: `storage/playwright/shots/masuk-${testInfo.project.name}.png`, fullPage: true });

        await page.getByTestId('tombol-masuk').click();
        await expect(page.getByTestId('galat-surel')).toContainText('Isi surel');
        await page.getByTestId('isian-surel').fill('tidak@ada.id');
        await page.getByTestId('isian-sandi').fill('rahasia');
        await page.getByTestId('lihat-sandi').click();
        await expect(page.getByTestId('isian-sandi')).toHaveAttribute('type', 'text');
        await page.getByTestId('tombol-masuk').click();
        await expect(page.getByTestId('galat-surel')).toContainText('belum terdaftar');

        await page.getByTestId('akun-demo').locator('[data-peran="leading"]').click();
        await expect(page).toHaveURL(/\/dashboard/);
        await expect(page.locator('[data-testid="toast"]').filter({ hasText: 'Selamat datang kembali.' })).toBeVisible();

        await page.goto('/akun');
        await expect(page.getByTestId('profil-akun')).toContainText('muslimin@example.com');
        await expect(page.getByTestId('leading-akun')).toContainText('1.6');
        await page.getByTestId('tab-akun').locator('[data-tab="aktivitas"]').click();
        await expect(page.getByTestId('riwayat-masuk').first()).toBeVisible();
        await page.screenshot({ path: `storage/playwright/shots/akun-saya-${testInfo.project.name}.png`, fullPage: true });
        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(lebar).toBeLessThanOrEqual(0);

        await page.locator('header').getByRole('button', { expanded: false }).last().click();
        await page.getByTestId('menu-keluar').click();
        await expect(page).toHaveURL(/\/masuk/);
        await expect(page.locator('[data-testid="toast"]').filter({ hasText: 'Anda sudah keluar.' })).toBeVisible();
    });
});
