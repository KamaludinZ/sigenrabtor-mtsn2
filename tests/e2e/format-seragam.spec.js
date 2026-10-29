import { expect, test } from '@playwright/test';

test.describe('Format Seragam', () => {
    test('tata letak: ringkasan, susunan baku, penomoran, pilihan template', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/format');
        await expect(page.getByRole('heading', { name: 'Format Seragam', level: 1 })).toBeVisible();
        const ringkasan = page.getByTestId('ringkasan-format');
        await expect(ringkasan.locator('[data-unsur]')).toHaveCount(4);
        await expect(ringkasan.locator('[data-unsur="susunan"]')).toHaveAttribute('data-status', 'siap');
        await expect(page.getByTestId('kop-format')).toContainText('Madrasah Tsanawiyah Negeri 2 Kota Malang');
        await page.screenshot({ path: `storage/playwright/shots/format-seragam-${testInfo.project.name}.png`, fullPage: true });

        await ringkasan.locator('[data-unsur="penomoran"]').getByRole('button', { name: /Lihat aturan/ }).click();
        await expect(page).toHaveURL(/tab=penomoran/);
        const aturan = page.locator('[data-testid="aturan-nomor"][data-jenis="KAK+TOR"]');
        await expect(aturan.getByTestId('contoh-nomor')).toHaveText(/KAK-TOR\/032\/MTsN2KotaMalang\/[IVX]+\/2026/);
        await expect(page.getByTestId('penanda-nomor')).toContainText('{urut}');
        await page.screenshot({ path: `storage/playwright/shots/format-penomoran-${testInfo.project.name}.png`, fullPage: true });

        await page.getByTestId('tab-format').locator('[data-tab="susunan"]').click();
        await expect(page.getByTestId('susunan-kak').locator('[data-bagian]')).toHaveCount(9);
        await expect(page.getByTestId('susunan-rab-kolom')).toContainText('Jumlah (Rp)');

        await page.getByTestId('tab-format').locator('[data-tab="template"]').click();
        await expect(page.getByTestId('template-jenis')).toHaveCount(5);
        await expect(page.getByTestId('pilih-kak-workshop').locator('option').first()).toHaveText('Susunan baku aplikasi');
        const pratinjau = page.getByTestId('pratinjau-template');
        await expect(pratinjau.getByTestId('asal-pratinjau-template')).toHaveAttribute('data-asal', 'bawaan');
        await expect(pratinjau.getByTestId('isi-pratinjau-template')).toContainText('Latar Belakang');
        await pratinjau.getByTestId('contoh-pratinjau-template').selectOption({ label: 'Supervisi Akademik dan Refleksi Pembelajaran' });
        await expect(pratinjau.getByTestId('asal-pratinjau-template')).toHaveAttribute('data-jenis', 'supervisi');
        await expect(pratinjau.getByTestId('isi-pratinjau-template')).toContainText('Supervisi Akademik');
        await pratinjau.screenshot({ path: `storage/playwright/shots/format-pratinjau-${testInfo.project.name}.png` });
        const aksi = pratinjau.getByTestId('aksi-pratinjau-template');
        await expect(aksi).toHaveAttribute('data-terkunci', '0');
        await expect(aksi.getByTestId('cetak-contoh-template')).toHaveAttribute('href', /\/kegiatan\/2\/cetak\?sumber=DIPA&otomatis=1/);
        await expect(aksi.getByTestId('unduh-word-contoh')).toBeVisible();

        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(lebar).toBeLessThanOrEqual(0);
    });

    test('pilihan template: baris khusus leading, galat, batalkan', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/format?tab=template');
        const form = page.getByTestId('template-format');
        await expect(form).toHaveAttribute('data-berubah', '0');
        await expect(page.getByTestId('template-leading-kosong')).toBeVisible();
        await page.getByTestId('tambah-template-leading').click();
        await page.getByTestId('pilih-leading-0').selectOption('1.6');
        await page.getByTestId('pilih-jenis-0').selectOption('supervisi');
        await expect(form).toHaveAttribute('data-berubah', '1');
        await expect(page.getByTestId('template-belum-disimpan')).toBeVisible();
        await page.getByTestId('simpan-template').click();
        await expect(page.getByTestId('pilih-kak-leading-0-galat')).toContainText('Pilih master KAK/TOR atau cover');
        await expect(page.locator('[data-testid="toast"][data-jenis="error"]')).toContainText('Pilihan template belum disimpan');
        await page.screenshot({ path: `storage/playwright/shots/format-template-${testInfo.project.name}.png`, fullPage: true });
        await page.getByTestId('batal-template').click();
        await expect(page.getByTestId('template-leading-kosong')).toBeVisible();
        await expect(form).toHaveAttribute('data-berubah', '0');
        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(lebar).toBeLessThanOrEqual(0);
    });

    test('formulir penomoran: sisip penanda, contoh seketika, galat, batalkan', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/format?tab=penomoran');
        const form = page.getByTestId('penomoran-format');
        const rab = page.locator('[data-testid="aturan-nomor"][data-jenis="RAB"]');
        const pola = page.getByTestId('pola-nomor-RAB');
        await expect(rab.getByTestId('contoh-nomor')).toHaveText('RAB/013/DIPA/1.1/2026');
        await pola.fill('RAB/{tahun}');
        await expect(page.getByTestId('galat-pola-RAB')).toContainText('{urut}');
        await pola.fill('RAB//{tahun}');
        await pola.evaluate((el) => el.setSelectionRange(4, 4));
        await page.getByTestId('penanda-nomor').locator('[data-penanda="{urut}"]').click();
        await expect(pola).toHaveValue('RAB/{urut}/{tahun}');
        await expect(page.getByTestId('galat-pola-RAB')).toHaveCount(0);
        await expect(rab.getByTestId('contoh-nomor')).toHaveText('RAB/013/2026');
        await page.getByTestId('digit-nomor-RAB').selectOption('5');
        await expect(rab.getByTestId('contoh-nomor')).toHaveText('RAB/00013/2026');
        await expect(form).toHaveAttribute('data-berubah', '1');
        await page.screenshot({ path: `storage/playwright/shots/format-penomoran-form-${testInfo.project.name}.png`, fullPage: true });
        await page.getByTestId('batal-nomor').click();
        await expect(form).toHaveAttribute('data-berubah', '0');
        await expect(pola).toHaveValue('RAB/{urut}/{sumber}/{leading}/{tahun}');
        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(lebar).toBeLessThanOrEqual(0);
    });
});
