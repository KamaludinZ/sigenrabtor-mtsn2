import { expect, test } from '@playwright/test';

// Regresi: ketikan harga yang sedang berlangsung tidak boleh ditimpa respons request kolom lain yang lambat.
test.describe('Ketikan tidak hilang saat server lambat', () => {
    test('harga satuan tersimpan sesuai yang tampil walau request sebelumnya tertunda', async ({ page }, testInfo) => {
        await page.goto('/kegiatan/buat');
        await page.locator('#nama_kegiatan').fill(`Regresi Ketikan ${testInfo.project.name}`);
        await page.getByTestId('sumber-dipa').getByRole('checkbox').check();
        await expect(page.locator('#pagu_dipa')).toBeEnabled();
        await page.locator('#pagu_dipa').pressSequentially('5000000');
        await page.getByRole('button', { name: 'Simpan & lanjut ke RAB' }).click();
        await expect(page).toHaveURL(/\/rab/);

        await page.getByRole('button', { name: 'Tambah item belanja' }).click();
        await expect(page.locator('#item_uraian')).toBeFocused();

        // Server lambat: tiap respons Livewire tertunda 800 ms. Request blur kolom-kolom sebelumnya mengantre,
        // dan salah satunya terkirim DI TENGAH pengetikan harga sehingga membawa ketikan yang belum selesai.
        await page.route('**/livewire-*/update', async (route) => {
            await new Promise((r) => setTimeout(r, 800));
            await route.continue();
        });
        await page.locator('#item_uraian').fill('Honorarium Narasumber');
        await page.locator('#item_volume').fill('3');
        await page.locator('#item_satuan').fill('OJ');
        await page.locator('#item_harga_satuan').click();
        await page.locator('#item_harga_satuan').pressSequentially('600000', { delay: 200 });
        await expect(page.locator('#item_harga_satuan')).toHaveValue('600.000');
        await page.waitForTimeout(3500);
        await page.unroute('**/livewire-*/update');

        await page.getByRole('button', { name: 'Simpan item' }).click();
        await expect(page.getByText('Item ditambahkan: Honorarium Narasumber (Rp1.800.000).')).toBeVisible();
        await expect(page.getByTestId('ringkasan-rab')).toContainText('Rp1.800.000');
    });
});
