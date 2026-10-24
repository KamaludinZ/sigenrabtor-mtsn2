import { expect, test } from '@playwright/test';

test.describe('Kode & Klasifikasi', () => {
    test.afterEach(async ({ page }) => {
        await page.goto('/demo/peran/leading');
    });

    test('pemilih leading: akun leading satu kartu, admin semua & bisa memilih', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/1/kode');
        await expect(page.locator('[data-leading]')).toHaveCount(1);
        await expect(page.getByText('Akun Anda terikat ke leading ini.')).toBeVisible();

        await page.goto('/demo/peran/admin');
        await page.goto('/kegiatan/1/kode');
        await expect(page.locator('[data-leading]')).toHaveCount(8);
        await page.locator('[data-leading="1.4"]').click();
        await expect(page.getByRole('radio', { name: /Waka Humas/ })).toBeChecked();
        await expect(page.getByTestId('pratinjau-header')).toContainText('Waka Humas');
        await page.screenshot({ path: `storage/playwright/shots/pilih-leading-${testInfo.project.name}.png`, fullPage: true });

        // Kembalikan pilihan agar data contoh tetap seperti semula untuk tes lain.
        await page.locator('[data-leading="1.6"]').click();
        await expect(page.getByTestId('pratinjau-header')).toContainText('Tim Penjamin Mutu');
    });

    test('combobox RKTM & EDM: cari, keyboard, kosongkan', async ({ page }, testInfo) => {
        await page.goto('/kegiatan/1/kode');
        const rktm = page.locator('#kode_rktm');
        await expect(rktm).toContainText('6.17.12');

        await rktm.click();
        await expect(page.locator('#kode_rktm-cari')).toBeFocused();
        await page.locator('#kode_rktm-cari').fill('tahfiz');
        await expect(page.locator('#kode_rktm-daftar [role=option]')).toHaveCount(1);
        await expect(page.locator('#kode_rktm-daftar')).toContainText('Program Tahfiz');
        await page.screenshot({ path: `storage/playwright/shots/combobox-rktm-${testInfo.project.name}.png` });
        await page.locator('#kode_rktm-cari').press('Enter');
        await expect(rktm).toContainText('2.2.13');
        await expect(page.getByTestId('pratinjau-header')).toContainText('(2.2.13)');

        // Keyboard: buka dengan panah bawah, Esc menutup tanpa mengubah.
        await rktm.focus();
        await page.keyboard.press('ArrowDown');
        await expect(page.locator('#kode_rktm-daftar')).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.locator('#kode_rktm-daftar')).toBeHidden();
        await expect(rktm).toContainText('2.2.13');

        // EDM: cari per komponen lalu kosongkan.
        await page.locator('#kode_edm').click();
        await page.locator('#kode_edm-cari').fill('pembiayaan');
        await expect(page.locator('#kode_edm-daftar [role=option]')).toHaveCount(3);
        await page.locator('#kode_edm-daftar [data-nilai="E1"]').click();
        await expect(page.locator('#kode_edm')).toContainText('E1');
        await page.getByRole('button', { name: 'Kosongkan pilihan' }).nth(1).click();
        await expect(page.locator('#kode_edm')).toContainText('Pilih kode EDM');

        // Pulihkan data contoh untuk tes lain.
        await page.reload();
    });

    test('halaman kode tidak menimbulkan gulir horizontal (teks kode panjang terpotong rapi)', async ({ page }) => {
        await page.goto('/kegiatan/1/kode');
        const luber = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(luber).toBeLessThanOrEqual(0);
    });

    test('IKSK terfilter: mengikuti SK dan terpilih otomatis bila hanya satu', async ({ page }) => {
        await page.goto('/kegiatan/1/kode');
        await expect(page.locator('#kode_iksk')).toContainText('IKSK 10.6');

        await page.locator('#kode_sk').click();
        await page.locator('#kode_sk-cari').fill('SK.12');
        await page.locator('#kode_sk-cari').press('Enter');
        await expect(page.getByText('IKSK IKSK 12.1 dipilih otomatis')).toBeVisible();
        await expect(page.locator('#kode_iksk')).toContainText('IKSK 12.1');

        await page.locator('#kode_sk').click();
        await page.locator('#kode_sk-cari').fill('SK.9');
        await page.locator('#kode_sk-cari').press('Enter');
        await expect(page.locator('#kode_iksk')).toContainText('Pilih kode IKSK');
        await page.locator('#kode_iksk').click();
        await expect(page.locator('#kode_iksk-daftar [role=option]')).toHaveCount(4);

        await page.reload();
    });
});
