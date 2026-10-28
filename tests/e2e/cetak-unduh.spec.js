import { expect, test } from '@playwright/test';

test.describe('Cetak & Unduh', () => {
    test('daftar dokumen, pilih dokumen & lembar, buka halaman cetak', async ({ page, context }, testInfo) => {
        await context.addInitScript(() => {
            window.print = () => {
                window.__dicetak = (window.__dicetak ?? 0) + 1;
            };
        });
        await page.goto('/demo/peran/leading');
        await page.goto('/cetak');
        await expect(page.getByRole('heading', { name: 'Cetak & Unduh', level: 1 })).toBeVisible();

        const daftar = page.getByTestId('daftar-cetak');
        await expect(daftar.getByTestId('dokumen-cetak').first()).toBeVisible();
        await expect(page.getByTestId('panel-cetak-terpilih').getByTestId('judul-terpilih')).toBeVisible();

        await daftar.locator('[data-kunci="2|DIPA"]').click();
        await expect(page).toHaveURL(/pilih=2%7CDIPA/);
        const panel = page.getByTestId('panel-cetak-terpilih');
        await expect(panel.getByTestId('judul-terpilih')).toContainText('Supervisi');
        await expect(panel.getByTestId('judul-terpilih')).toBeInViewport();
        await expect(panel.getByTestId('keadaan-terpilih')).toHaveAttribute('data-keadaan', 'draf');
        await expect(panel.getByTestId('alasan-draf-cetak')).toBeVisible();
        await expect(panel.locator('[data-lembar-mini]')).toHaveCount(3);
        await expect(panel.getByTestId('unduh-excel-terpilih')).toBeVisible();

        await panel.locator('[data-lembar-pilih="cover"]').click();
        await expect(panel.locator('[data-lembar-pilih="cover"]')).toHaveAttribute('aria-pressed', 'false');
        await expect(page).toHaveURL(/dokumen=kak(,|%2C)rab/);
        await expect(panel.locator('[data-lembar-mini]')).toHaveCount(2);

        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(lebar).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/cetak-unduh-${testInfo.project.name}.png`, fullPage: true });

        const [cetak] = await Promise.all([context.waitForEvent('page'), panel.getByTestId('cetak-terpilih').click()]);
        await cetak.waitForLoadState();
        await expect(cetak).toHaveURL(/\/kegiatan\/2\/cetak\?.*otomatis=1/);
        await expect(cetak.locator('[data-lembar-cetak]')).toHaveCount(2);
        await expect.poll(() => cetak.evaluate(() => window.__dicetak ?? 0)).toBeGreaterThan(0);
        await cetak.close();

        await page.getByTestId('saring-sumber').selectOption('Komite');
        await expect(daftar.locator('[data-kunci="2|DIPA"]')).toHaveCount(0);
        await expect(daftar.locator('[data-kunci="1|Komite"]')).toBeVisible();
    });

    test('tombol aksi cetak di Data Kegiatan & ruang kerja', async ({ page }) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan');
        const aksi = page.getByTestId('tabel-kegiatan').locator('[data-testid="aksi-cetak"][data-kegiatan="2"]');
        await aksi.getByTestId('buka-aksi-cetak').click();
        const menu = aksi.getByTestId('menu-aksi-cetak');
        await expect(menu).toBeVisible();
        await expect(menu.locator('[data-sumber-aksi]')).toHaveCount(2);
        await expect(menu.getByTestId('aksi-cetak-set-DIPA')).toHaveAttribute('href', /\/kegiatan\/2\/cetak\?.*otomatis=1/);
        await page.keyboard.press('Escape');
        await expect(menu).toBeHidden();
        await aksi.getByTestId('buka-aksi-cetak').click();
        await menu.getByTestId('aksi-atur-cetak').click();
        await expect(page).toHaveURL(/\/cetak\?pilih=2(%7C|\|)DIPA/);
        await expect(page.getByTestId('judul-terpilih')).toContainText('Supervisi');

        await page.goto('/kegiatan/1/rab');
        const tab = page.locator('[data-testid="aksi-cetak"][data-kegiatan="1"]');
        await tab.getByTestId('buka-aksi-cetak').click();
        await expect(tab.getByTestId('aksi-cetak-set-Komite')).toBeVisible();
        await expect(tab.getByTestId('aksi-rab-excel-Komite')).toHaveAttribute('href', /\/kegiatan\/1\/rab\/excel\?sumber=Komite/);
        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(lebar).toBeLessThanOrEqual(0);
    });
});
