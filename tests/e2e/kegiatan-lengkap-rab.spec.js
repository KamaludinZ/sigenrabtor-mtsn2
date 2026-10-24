import { expect, test } from '@playwright/test';

test.describe('Kelengkapan data & transisi ke RAB', () => {
    test('isi pelaksanaan & sumber dana, lalu kode di tabnya; indikator kelengkapan; lanjut ke RAB', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/buat');

        await page.locator('#nama_kegiatan').fill(`Workshop KTSP ${testInfo.project.name}`);

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
        await expect(page).toHaveURL(/\/kegiatan\/\d+\/rab(\?.*)?$/);
        await expect(page.getByRole('heading', { name: 'Rencana Anggaran Biaya' })).toBeVisible();
        await expect(page.getByRole('tab', { name: /RAB Komite/ })).toHaveAttribute('aria-selected', 'true');

        // Kode & klasifikasi diatur di tab tersendiri.
        await page.getByRole('link', { name: 'Kode & Klasifikasi' }).click();
        await expect(page.getByRole('heading', { name: 'Kode & Klasifikasi' })).toBeVisible();
        await page.locator('#kode_rktm').click();
        await page.locator('#kode_rktm-cari').fill('Workshop KTSP');
        await page.locator('#kode_rktm-daftar [data-nilai="1.1.7"]').click();
        await page.locator('#kode_sk').selectOption('SK.10');
        await expect(page.locator('#kode_iksk')).toBeEnabled();
        await page.locator('#kode_iksk').selectOption('IKSK 10.6');
        await page.locator('#kode_edm').click();
        await page.locator('#kode_edm-cari').fill('C1');
        await page.locator('#kode_edm-cari').press('Enter');
        await page.getByText('2. Profesionalitas').click();
        await expect(page.getByTestId('pratinjau-header')).toContainText('IKSK 10.6');
        await expect(page.getByTestId('belum-disimpan')).toBeVisible();
        await page.screenshot({ path: `storage/playwright/shots/klasifikasi-${testInfo.project.name}.png`, fullPage: true });
        await page.getByRole('button', { name: 'Simpan & lanjut ke RAB' }).click();
        await expect(page).toHaveURL(/\/rab/);
        await expect(page.getByTestId('header-rab')).toContainText('1.1.7');

        // Data tersimpan: kembali ke Data Kegiatan lewat tab.
        await page.getByRole('link', { name: 'Data Kegiatan' }).last().click();
        await expect(page.locator('#pagu_komite')).toHaveValue('10.000.000');
        await expect(page.getByTestId('ringkasan-kode')).toContainText('IKSK 10.6');
        await expect(page.locator('#kode [data-kelengkapan]')).toHaveAttribute('data-kelengkapan', 'lengkap');
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
