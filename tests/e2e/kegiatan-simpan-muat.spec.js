import { expect, test } from '@playwright/test';

test.describe('Simpan & muat kegiatan (data tiruan)', () => {
    test('simpan kegiatan baru, muat ulang, lalu buka draf lain dari menu', async ({ page }) => {
        const nama = `Pelatihan Moderasi Beragama ${test.info().project.name}`;

        await page.goto('/kegiatan/buat');
        await page.locator('#nama_kegiatan').fill(nama);
        await page.locator('#tujuan').fill('meningkatkan pemahaman moderasi beragama warga madrasah.');
        await page.locator('#nama_kegiatan').click();
        await expect(page.getByTestId('belum-disimpan')).toBeVisible();

        await page.getByRole('button', { name: 'Simpan Draf' }).first().click();
        await expect(page).toHaveURL(/\/kegiatan\/\d+\/edit$/);
        await expect(page.getByText('Kegiatan baru tersimpan sebagai draf.')).toBeVisible();
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(nama);
        await expect(page.getByTestId('info-simpan')).toContainText('Terakhir disimpan');

        // Data tetap ada setelah halaman dimuat ulang.
        await page.reload();
        await expect(page.locator('#nama_kegiatan')).toHaveValue(nama);
        await expect(page.locator('#tujuan')).toHaveValue('meningkatkan pemahaman moderasi beragama warga madrasah.');
        await expect(page.locator('#nama_kegiatan_cover')).toHaveValue(nama.toUpperCase());

        // Menu "Buka draf": kegiatan yang sedang dibuka ditandai, draf lain bisa dibuka dari sana.
        const urlSekarang = page.url();
        await page.getByRole('button', { name: 'Buka draf' }).click();
        await expect(page.getByText('Draf terbaru')).toBeVisible();
        await page.waitForTimeout(250); // tunggu transisi menu selesai sebelum tangkapan layar
        await page.screenshot({ path: `storage/playwright/shots/buka-draf-${test.info().project.name}.png` });
        const tautanDraf = page.locator('a[href*="/kegiatan/"][href$="/edit"]').filter({ hasNotText: nama });
        const tujuan = await tautanDraf.first().getAttribute('href');
        await tautanDraf.first().click();
        await expect(page).toHaveURL(tujuan);
        expect(page.url()).not.toBe(urlSekarang);

        // Draf contoh tetap bisa dimuat lengkap.
        await page.goto('/kegiatan/1/edit');
        await expect(page.locator('#tanggal_mulai')).toHaveValue('2026-07-14');
        await expect(page.locator('#panitia [data-panitia-row]')).toHaveCount(3);
    });

    test('simpan ulang draf yang ada tidak membuat kegiatan baru', async ({ page }) => {
        await page.goto('/kegiatan/1/edit');
        await page.locator('#sasaran').fill('dokumen kurikulum yang disahkan kepala madrasah.');
        await page.locator('#tujuan').click();
        await page.getByRole('button', { name: 'Simpan Draf' }).first().click();
        await expect(page.getByText(/Draf tersimpan pukul/)).toBeVisible();
        await expect(page).toHaveURL(/\/kegiatan\/1\/edit$/);
        await expect(page.getByTestId('belum-disimpan')).toHaveCount(0);
    });
});
