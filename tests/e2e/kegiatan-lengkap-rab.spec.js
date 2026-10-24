import { expect, test } from '@playwright/test';

test.describe('Kelengkapan data & transisi ke RAB', () => {
    test('isi kode, pelaksanaan, sumber dana; indikator kelengkapan; simpan & lanjut ke RAB', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/buat');

        await page.locator('#kode_rktm').selectOption('1.1.7');
        await page.getByRole('button', { name: 'Pakai sebagai nama kegiatan' }).click();
        await expect(page.locator('#nama_kegiatan')).toHaveValue('Workshop KTSP');
        await expect(page.locator('#nama_kegiatan_cover')).toHaveValue('WORKSHOP KTSP');

        await page.locator('#kode_sk').selectOption('SK.10');
        await expect(page.locator('#kode_iksk')).toBeEnabled();
        await page.locator('#kode_iksk').selectOption('IKSK 10.6');
        await page.locator('#kode_edm').selectOption('C1');
        await page.getByText('2. Profesionalitas').click();
        await expect(page.locator('#kode [data-kelengkapan]')).toHaveAttribute('data-kelengkapan', 'lengkap');

        await page.locator('#tempat').fill('Aula Madrasah');
        await page.locator('#peserta_jumlah').fill('56');
        await page.locator('#peserta_jenis').fill('Guru');
        await page.locator('#tempat').click();

        // Sumber dana: pagu diformat saat mengetik.
        await page.getByTestId('sumber-komite').getByRole('checkbox').check();
        await expect(page.locator('#pagu_komite')).toBeEnabled();
        await page.locator('#pagu_komite').pressSequentially('10000000');
        await expect(page.locator('#pagu_komite')).toHaveValue('10.000.000');
        await page.locator('#tempat').click();
        await expect(page.getByTestId('total-pagu')).toHaveText('Rp10.000.000');
        await expect(page.getByTestId('lanjut-rab')).toContainText('RAB Komite');

        await page.getByTestId('lanjut-rab').scrollIntoViewIfNeeded();
        await page.screenshot({ path: `storage/playwright/shots/lanjut-rab-${testInfo.project.name}.png` });

        await page.getByRole('button', { name: 'Simpan & lanjut ke RAB' }).click();
        await expect(page).toHaveURL(/\/kegiatan\/\d+\/edit$/);
        await expect(page.locator('#pagu_komite')).toHaveValue('10.000.000');
        await expect(page.locator('#kode_iksk')).toHaveValue('IKSK 10.6');
    });

    test('lanjut ke RAB tanpa sumber dana ditolak', async ({ page }) => {
        await page.goto('/kegiatan/buat');
        await page.locator('#nama_kegiatan').fill('Kegiatan Tanpa Sumber Dana');
        await page.getByRole('button', { name: 'Simpan & lanjut ke RAB' }).click();
        await expect(page.getByText('Pilih minimal satu sumber dana sebelum menyusun RAB.')).toBeVisible();
        await expect(page).toHaveURL(/\/kegiatan\/buat$/);
    });

    test('contoh kegiatan lengkap menampilkan 5/5 bagian wajib', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name === 'mobile', 'Ringkasan kelengkapan ada di kolom samping desktop.');
        await page.goto('/kegiatan/1/edit');
        await expect(page.getByTestId('ringkasan-kelengkapan')).toHaveText('5/5 lengkap');
        await page.screenshot({ path: 'storage/playwright/shots/contoh-lengkap-desktop.png', fullPage: true });
    });
});
