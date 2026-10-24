import { expect, test } from '@playwright/test';

test.describe('Form Data Kegiatan', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/kegiatan/buat');
        await expect(page.getByRole('heading', { name: 'Kegiatan Baru' })).toBeVisible();
    });

    test('tidak ada scroll horizontal dan semua bagian tampil', async ({ page }) => {
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);

        for (const judul of ['Identitas Kegiatan', 'Kode & Klasifikasi', 'Pelaksanaan', 'Konteks Kegiatan', 'Pelaksana & Panitia', 'Sumber Dana']) {
            await expect(page.locator('section h2', { hasText: judul })).toBeVisible();
        }
    });

    test('identitas: nama cover kapital otomatis dan kalimat terpotong ditolak', async ({ page }) => {
        await page.locator('#nama_kegiatan').fill('Workshop Penyusunan Kurikulum Madrasah');
        await expect(page.locator('#nama_kegiatan_cover')).toHaveValue('WORKSHOP PENYUSUNAN KURIKULUM MADRASAH');

        await page.locator('#sasaran').fill('dokumen perencanaan yang dibutuhkan oleh');
        await page.locator('#tujuan').click();
        await expect(page.getByText('tampak terpotong (diakhiri kata “oleh”)')).toBeVisible();
    });

    test('uraian: tombol rapikan spasi menormalkan teks', async ({ page }) => {
        const tujuan = page.locator('#tujuan');
        await tujuan.fill('menghasilkan   dokumen    kurikulum');
        await page.locator('#tujuan').locator('xpath=ancestor::div[@x-data][1]').getByRole('button', { name: 'Rapikan spasi' }).click();
        await expect(tujuan).toHaveValue('menghasilkan dokumen kurikulum');
    });

    test('panitia: susunan baku, isi nama, geser, hapus', async ({ page }) => {
        const bagian = page.locator('#panitia');
        await bagian.getByRole('button', { name: 'Pakai susunan baku' }).click();
        await expect(bagian.locator('[data-panitia-row]')).toHaveCount(4);
        await expect(page.locator('#panitia-0-nama')).toBeFocused();

        await page.locator('#panitia-0-nama').fill('Ahmad Fauzi, S.Pd.');
        await page.locator('#panitia-0-jabatan').fill('Waka Kurikulum');
        await bagian.getByRole('button', { name: 'Turunkan anggota 1' }).click();
        await expect(page.locator('#panitia-1-nama')).toHaveValue('Ahmad Fauzi, S.Pd.');
        await expect(page.locator('#panitia-1-peran')).toHaveValue('Ketua');

        await bagian.getByRole('button', { name: 'Hapus anggota 4' }).click();
        await expect(bagian.locator('[data-panitia-row]')).toHaveCount(3);

        await page.screenshot({ path: `storage/playwright/shots/panitia-${test.info().project.name}.png`, fullPage: false });
    });

    test('panitia: seret-lepas, urungkan hapus, dan urutkan sesuai peran', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name === 'mobile', 'Seret-lepas diuji di desktop; mobile memakai tombol naik/turun.');
        const bagian = page.locator('#panitia');
        await bagian.getByRole('button', { name: 'Pakai susunan baku' }).click();
        await expect(bagian.locator('[data-panitia-row]')).toHaveCount(4);

        // Seret baris "Anggota" (4) ke paling atas.
        const handle = bagian.locator('[data-panitia-row]').nth(3).locator('[wire\\:sort\\:handle]');
        await handle.dragTo(bagian.locator('[data-panitia-row]').nth(0).locator('[wire\\:sort\\:handle]'));
        await expect(page.locator('#panitia-0-peran')).toHaveValue('Anggota');

        await bagian.getByRole('button', { name: 'Urutkan sesuai peran' }).click();
        await expect(page.locator('#panitia-0-peran')).toHaveValue('Ketua');
        await expect(page.locator('#panitia-3-peran')).toHaveValue('Anggota');

        await bagian.getByRole('button', { name: 'Hapus anggota 2' }).click();
        await expect(bagian.locator('[data-panitia-row]')).toHaveCount(3);
        await bagian.getByRole('button', { name: 'Urungkan' }).click();
        await expect(bagian.locator('[data-panitia-row]')).toHaveCount(4);
        await expect(page.locator('#panitia-1-peran')).toHaveValue('Sekretaris');
    });

    test('jadwal: tanggal selesai, bulan, dan batas laporan dihitung otomatis', async ({ page }) => {
        await page.locator('#tanggal_mulai').fill('2026-07-30');
        await page.locator('#lama_hari').fill('4');
        await expect(page.getByTestId('tanggal-selesai')).toHaveText('Minggu, 2 Agustus 2026');
        await expect(page.locator('#pelaksanaan').getByRole('button', { name: 'Jul' })).toHaveAttribute('aria-pressed', 'true');
        await expect(page.locator('#pelaksanaan').getByRole('button', { name: 'Agu' })).toHaveAttribute('aria-pressed', 'true');
        await expect(page.locator('#tanggal_laporan')).toHaveValue('2026-08-12');

        await page.getByLabel('Hitung hari kerja saja').check();
        await expect(page.getByTestId('tanggal-selesai')).toHaveText('Selasa, 4 Agustus 2026');
        await expect(page.locator('#tanggal_laporan')).toHaveValue('2026-08-14');

        await page.locator('#pelaksanaan').scrollIntoViewIfNeeded();
        await page.screenshot({ path: `storage/playwright/shots/jadwal-${test.info().project.name}.png` });
    });

    test('validasi tanggal: dokumen setelah mulai & bulan tidak mencakup kegiatan', async ({ page }) => {
        await page.locator('#pelaksanaan').getByRole('button', { name: 'Mei' }).click();
        await page.locator('#tanggal_mulai').fill('2026-07-14');
        await page.locator('#lama_hari').fill('2');
        await expect(page.getByText('Bulan pelaksanaan belum mencakup bulan kegiatan berlangsung: Juli.')).toBeVisible();

        await page.locator('#tanggal_dokumen').fill('2026-07-20');
        await expect(page.getByText('harus sebelum atau sama dengan tanggal mulai kegiatan (14 Juli 2026)')).toBeVisible();
        await expect(page.locator('#tanggal_dokumen')).toHaveAttribute('max', '2026-07-14');

        await page.locator('#tanggal_dokumen').fill('2026-07-01');
        await expect(page.getByText('harus sebelum atau sama dengan tanggal mulai kegiatan')).toHaveCount(0);
    });

    test('simpan draf kosong menampilkan error nama kegiatan', async ({ page }) => {
        await page.getByRole('button', { name: 'Simpan Draf' }).first().click();
        await expect(page.getByText('Kolom nama kegiatan wajib diisi.')).toBeVisible();
        await expect(page.getByText('Periksa kembali isian yang ditandai merah.')).toBeVisible();
    });
});
