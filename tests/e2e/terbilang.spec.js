import { expect, test } from '@playwright/test';

test.describe('Terbilang terupdate saat data berubah', () => {
    test('pagu & harga satuan: terbilang ikut tiap ketukan, kosong lagi setelah simpan & tambah lagi', async ({ page }, testInfo) => {
        await page.goto('/kegiatan/buat');
        await page.locator('#nama_kegiatan').fill(`Terbilang Live ${testInfo.project.name}`);
        await page.getByTestId('sumber-dipa').getByRole('checkbox').check();
        await expect(page.locator('#pagu_dipa')).toBeEnabled();

        await page.locator('#pagu_dipa').pressSequentially('1500');
        await expect(page.getByTestId('pagu_dipa-terbilang')).toHaveText('Seribu Lima Ratus Rupiah');
        await page.locator('#pagu_dipa').pressSequentially('000');
        await expect(page.getByTestId('pagu_dipa-terbilang')).toHaveText('Satu Juta Lima Ratus Ribu Rupiah');

        await page.getByRole('button', { name: 'Simpan & lanjut ke RAB' }).click();
        await expect(page).toHaveURL(/\/rab/);

        await page.getByRole('button', { name: 'Tambah item belanja' }).click();
        await page.locator('#item_uraian').fill('Snack Peserta');
        await page.locator('#item_volume').fill('20');
        await page.locator('#item_satuan').fill('kotak');
        await page.locator('#item_harga_satuan').pressSequentially('15000');
        await expect(page.getByTestId('item_harga_satuan-terbilang')).toHaveText('Lima Belas Ribu Rupiah');
        await page.getByRole('button', { name: 'Simpan & tambah lagi' }).click();
        await expect(page.getByText('Item ditambahkan: Snack Peserta')).toBeVisible();
        await expect(page.getByTestId('item_harga_satuan-terbilang')).toHaveText('');

        // Ringkasan, header, dan kalimat pembiayaan ikut berubah.
        await expect(page.getByTestId('total-anggaran-terbilang').first()).toHaveText('“Tiga Ratus Ribu Rupiah”');
        await expect(page.getByTestId('header-terbilang')).toHaveText('Tiga Ratus Ribu Rupiah');
        await expect(page.getByTestId('kalimat-pembiayaan')).toContainText('(Tiga Ratus Ribu Rupiah)');
    });
});
