import { expect, test } from '@playwright/test';

test.describe('Daftar & impor kegiatan dari Excel', () => {
    test('unduh template, impor berkas, lihat proses & laporan berhasil/gagal', async ({ page }, testInfo) => {
        await page.goto('/kegiatan');
        await expect(page.getByRole('heading', { name: 'Data Kegiatan' })).toBeVisible();

        // Unduh template (.xlsx tahun anggaran aktif).
        const [template] = await Promise.all([page.waitForEvent('download'), page.getByTestId('unduh-template').click()]);
        expect(template.suggestedFilename()).toBe('template-impor-kegiatan-2026.xlsx');
        const pathTemplate = testInfo.outputPath('template-impor-kegiatan-2026.xlsx');
        await template.saveAs(pathTemplate);

        await page.getByRole('button', { name: 'Impor dari Excel' }).click();
        const impor = page.getByTestId('impor-bertahap');
        await expect(impor).toHaveAttribute('data-tahap', 'unggah');

        // Template yang belum diisi ditolak dengan alasan yang jelas.
        await page.getByTestId('input-berkas-impor').setInputFiles(pathTemplate);
        await expect(page.getByTestId('galat-impor')).toContainText('Berkas tidak berisi data kegiatan');

        // Berkas berisi baris benar, baris tanpa nama, dan nama yang sudah ada (CSV titik koma seperti ekspor Excel).
        const akhiran = `${testInfo.project.name}-${Date.now()}`;
        const baris = ['No;Nama Kegiatan *;Leading;Pagu DIPA;Pagu Komite;Bulan Pelaksanaan'];
        for (let i = 1; i <= 7; i++) {
            baris.push(`${i};Kegiatan Impor ${i} ${akhiran};1.6;;${i}000000;${i + 1}`);
        }
        baris.push('8;;1.6;;;');
        baris.push('9;Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran;;;;Juli');
        await page.getByTestId('input-berkas-impor').setInputFiles({ name: 'kegiatan.csv', mimeType: 'text/csv', buffer: Buffer.from(baris.join('\n')) });

        await expect(impor).toHaveAttribute('data-tahap', 'periksa');
        await expect(page.getByTestId('saring-siap')).toHaveText('Siap (7)');
        await expect(page.getByTestId('saring-gagal')).toHaveText('Gagal (1)');
        await expect(page.getByTestId('saring-dilewati')).toHaveText('Dilewati (1)');
        // Saringan awal menampilkan baris gagal lebih dulu beserta alasannya.
        await expect(page.getByTestId('pratinjau-impor')).toContainText('Nama kegiatan wajib diisi.');
        await page.getByTestId('saring-semua').click();
        await expect(page.getByTestId('pratinjau-impor')).toContainText(`Kegiatan Impor 3 ${akhiran}`);
        await impor.screenshot({ path: `storage/playwright/shots/impor-periksa-${testInfo.project.name}.png` });

        await page.getByTestId('mulai-impor').click();
        await expect(impor).toHaveAttribute('data-tahap', 'selesai', { timeout: 60_000 });
        await expect(page.getByTestId('ringkasan-impor')).toHaveText('Impor selesai: 7 kegiatan berhasil ditambahkan.');
        await expect(impor).toContainText('7 berhasil · 1 dilewati · 1 gagal dari 9 baris');
        await expect(page.getByTestId('rincian-laporan-impor').locator('[data-status="gagal"]')).toContainText('Nama kegiatan wajib diisi.');
        await expect(page.getByTestId('rincian-laporan-impor').locator('[data-status="dilewati"]')).toContainText('sudah ada di aplikasi');
        await impor.screenshot({ path: `storage/playwright/shots/impor-laporan-${testInfo.project.name}.png` });

        const [laporan] = await Promise.all([page.waitForEvent('download'), page.getByTestId('unduh-laporan-impor').click()]);
        expect(laporan.suggestedFilename()).toMatch(/^laporan-impor-kegiatan-\d{8}-\d{6}\.xlsx$/);

        // Daftar ikut diperbarui; kegiatan hasil impor bisa langsung dibuka.
        const daftar = page.getByTestId('daftar-kegiatan');
        await expect(daftar).toContainText(`Kegiatan Impor 7 ${akhiran}`);
        await page.locator('#cari-kegiatan').fill(`Kegiatan Impor 2 ${akhiran}`);
        await expect(daftar.locator('li')).toHaveCount(1);
        await expect(daftar).toContainText('IMPOR');
        await expect(daftar).toContainText('Rp2.000.000');
        await daftar.getByRole('link', { name: `Kegiatan Impor 2 ${akhiran}` }).click();
        await expect(page.locator('#nama_kegiatan')).toHaveValue(`Kegiatan Impor 2 ${akhiran}`);
        await expect(page.locator('#nama_kegiatan_cover')).toHaveValue(`KEGIATAN IMPOR 2 ${akhiran.toUpperCase()}`);
    });
});
