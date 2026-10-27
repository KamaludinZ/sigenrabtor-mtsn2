import { expect, test } from '@playwright/test';

test.describe('Pengaturan AI', () => {
    test('dari Ikhtisar: tab penyedia, prompt per bagian, dan batas pemakaian tanpa gulir mendatar', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan');
        await page.getByTestId('bagian-ai').getByRole('link').first().click();
        await expect(page).toHaveURL(/\/pengaturan\/ai$/);
        await expect(page.getByRole('heading', { name: 'Pengaturan AI', level: 1 })).toBeVisible();

        await expect(page.getByTestId('penyedia-aktif')).toBeVisible();
        await expect(page.getByTestId('pilihan-penyedia-ai').locator('[data-penyedia]')).toHaveCount(4);

        const tab = page.getByTestId('tab-pengaturan-ai');
        await tab.locator('[data-tab="prompt"]').click();
        await expect(page).toHaveURL(/tab=prompt/);
        await expect(page.getByTestId('panel-prompt-ai').locator('[data-prompt]')).toHaveCount(6);

        await tab.locator('[data-tab="pemakaian"]').click();
        await expect(page.getByTestId('batas-harian-ai')).toContainText('20');
        await expect(page.getByTestId('pemakaian-per-akun').locator('[data-akun]').first()).toBeVisible();

        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(lebar).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/pengaturan-ai-${testInfo.project.name}.png`, fullPage: true });

        await tab.locator('[data-tab="penyedia"]').click();
        await page.screenshot({ path: `storage/playwright/shots/pengaturan-ai-penyedia-${testInfo.project.name}.png`, fullPage: true });
    });
});
