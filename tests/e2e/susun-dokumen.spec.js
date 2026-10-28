import { expect, test } from '@playwright/test';

test.describe('Susun KAK/TOR otomatis', () => {
    test('layout: tab ruang kerja, sembilan bagian berstatus & berasal-isi, RAB otomatis, panel samping, tanpa gulir mendatar', async ({ page }, testInfo) => {
        const mobile = testInfo.project.name.includes('mobile');
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/1/edit');
        await page.getByRole('navigation', { name: 'Bagian kegiatan' }).getByRole('link', { name: 'Susun KAK/TOR' }).click();
        await expect(page).toHaveURL(/\/kegiatan\/1\/dokumen$/);
        await expect(page.getByRole('heading', { name: 'Susun KAK/TOR', level: 1 })).toBeVisible();

        const bagian = page.getByTestId('bagian-susun');
        await expect(bagian).toHaveCount(9);
        await expect(bagian.first()).toContainText('Latar Belakang');
        await expect(bagian.nth(8)).toContainText('Lembar Pengesahan');
        await expect(bagian.locator('[data-testid="pratinjau-kak"]')).toHaveCount(9);
        await expect(page.getByTestId('ringkasan-susun')).toContainText(/\d dari 9 bagian lengkap/);

        // Asal isi bertaut ke tempat mengisi; yang diatur admin terkunci bagi akun leading.
        await expect(page.locator('#susun-bagian-1 [data-asal="identitas"]')).toHaveAttribute('href', /\/kegiatan\/1\/edit#identitas$/);
        await expect(page.locator('#susun-bagian-2 a[data-asal="dasar_hukum"]')).toHaveCount(0);

        // Ciutkan & buka semua bagian.
        await page.getByTestId('ciutkan-semua-bagian').click();
        await expect(page.locator('#isi-susun-3')).toBeHidden();
        await page.getByTestId('buka-semua-bagian').click();
        await expect(page.locator('#isi-susun-3')).toBeVisible();

        // Daftar bagian (layar sempit: bilah atas; layar lebar: panel samping) menuju kartunya.
        const navigasi = mobile ? page.getByRole('navigation', { name: 'Bagian dokumen' }) : page.getByTestId('kemajuan-susun');
        await navigasi.getByRole('link', { name: /7\. Jadwal Kegiatan/ }).click();
        await expect(page).toHaveURL(/#susun-bagian-7$/);

        await expect(page.getByTestId('susun-cover')).toBeVisible();
        await expect(page.getByTestId('susun-rab')).toContainText('RAB otomatis');
        await expect(page.getByTestId('kepala-rab').first()).toContainText('Kementerian');
        await expect(page.getByTestId('draf-dokumen')).toHaveAttribute('data-ada', 'ya');
        await expect(page.getByTestId('template-susun')).toContainText('Master template');
        await expect(page.getByTestId('buka-pratinjau')).toHaveAttribute('href', /\/kegiatan\/1\/pratinjau/);

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
        await page.screenshot({ path: `storage/playwright/shots/susun-kak-tor-${testInfo.project.name}.png`, fullPage: true });
    });

    test('dua sumber dana: pilihan sumber mengganti RAB otomatis', async ({ page }) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/2/dokumen');
        const sumber = page.getByRole('group', { name: 'Sumber dana' });
        await sumber.getByRole('button', { name: 'Komite' }).click();
        await expect(page).toHaveURL(/sumber=Komite/);
        await expect(sumber.getByRole('button', { name: 'Komite' })).toHaveAttribute('aria-pressed', 'true');
        await expect(page.getByTestId('susun-rab')).toContainText('Komite');
        await expect(page.getByTestId('draf-dokumen')).toHaveAttribute('data-ada', 'tidak');
    });

    test('jenis dokumen: RAB saja lalu KAK lewat keyboard, nama berkas & bagian mengikuti', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/1/dokumen');
        const pilihan = page.getByTestId('pilih-jenis-dokumen');
        await expect(pilihan.locator('[data-jenis]')).toHaveCount(5);
        await expect(pilihan.locator('[data-jenis="lengkap"]')).toHaveAttribute('data-terpilih', 'ya');
        await expect(page.getByTestId('nama-berkas')).toHaveText(/^KAK-TOR_RAB_/);

        await pilihan.locator('[data-jenis="RAB"]').click();
        await expect(page).toHaveURL(/jenis=RAB/);
        await expect(pilihan.locator('[data-jenis="RAB"]')).toHaveAttribute('data-terpilih', 'ya');
        await expect(page.getByTestId('bagian-susun')).toHaveCount(0);
        await expect(page.getByTestId('susun-cover')).toHaveCount(0);
        await expect(page.getByTestId('susun-rab')).toBeVisible();
        await expect(page.getByTestId('nama-berkas')).toHaveText(/^RAB_/);
        await pilihan.screenshot({ path: `storage/playwright/shots/pilih-jenis-dokumen-${testInfo.project.name}.png` });

        // Radio asli: panah kiri berpindah ke jenis sebelumnya (TOR → KAK).
        await pilihan.locator('[data-jenis="TOR"]').click();
        await expect(pilihan.locator('[data-jenis="TOR"]')).toHaveAttribute('data-terpilih', 'ya');
        await pilihan.locator('input[value="TOR"]').press('ArrowLeft');
        await expect(page).toHaveURL(/jenis=KAK(&|$)/);
        await expect(page.getByTestId('bagian-susun')).toHaveCount(9);
        await expect(page.getByTestId('susun-rab')).toHaveCount(0);
        await expect(page.getByTestId('nama-berkas')).toHaveText(/^KAK_/);
    });

    test('lengkapi isian di tempat: tempat pelaksanaan tersimpan lalu dipulihkan; tambah rincian menolak isian kosong', async ({ page }) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/1/dokumen?jenis=KAK');
        const bagian5 = page.locator('#susun-bagian-5');
        await bagian5.getByTestId('lengkapi-5').click();
        const form = page.getByTestId('isian-bagian-5');
        await expect(form).toBeVisible();
        const tempat = form.locator('#isian-tempat');
        const awal = await tempat.inputValue();

        // Esc menutup tanpa menyimpan.
        await tempat.fill('Ruang uji E2E');
        await tempat.press('Escape');
        await expect(form).toHaveCount(0);
        await expect(bagian5.getByTestId('pratinjau-kak')).not.toContainText('Ruang uji E2E');

        await bagian5.getByTestId('lengkapi-5').click();
        await page.locator('#isian-tempat').fill('Ruang Multimedia MTsN 2 Kota Malang');
        await page.getByTestId('simpan-isian').click();
        await expect(page.getByTestId('isian-bagian-5')).toHaveCount(0);
        await expect(bagian5.getByTestId('pratinjau-kak')).toContainText('Ruang Multimedia MTsN 2 Kota Malang');

        // Pulihkan data bersama.
        await bagian5.getByTestId('lengkapi-5').click();
        await page.locator('#isian-tempat').fill(awal);
        await page.getByTestId('simpan-isian').click();
        await expect(page.getByTestId('isian-bagian-5')).toHaveCount(0);
        await expect(bagian5.getByTestId('pratinjau-kak')).not.toContainText('Ruang Multimedia');

        await page.goto('/kegiatan/1/dokumen');
        const tambah = page.getByTestId('tambah-rincian-susun');
        await tambah.getByTestId('simpan-rincian-susun').click();
        await expect(tambah.locator('.form-error').first()).toBeVisible();
        await expect(tambah).toContainText(/uraian/i);
    });
});
