import { expect, test } from '@playwright/test';

test.describe('Data Pagu', () => {
    test('admin: tambah, tolak nama ganda, impor dengan deteksi nama sama, hapus', async ({ page }, testInfo) => {
        const nama = `Rapat Koordinasi E2E ${testInfo.project.name} ${testInfo.retry}`;
        await page.goto('/demo/peran/admin');
        await page.goto('/data-pagu');
        await expect(page.getByRole('heading', { name: 'Data Pagu', level: 1 })).toBeVisible();

        await page.getByRole('button', { name: 'Tambah data pagu' }).click();
        const formulir = page.getByTestId('formulir-pagu');
        await formulir.getByLabel('Nama kegiatan').fill('review rkjm dan penyusunan RKTM');
        await formulir.getByLabel('Pagu Komite').fill('1000000');
        await formulir.getByRole('button', { name: 'Simpan' }).click();
        await expect(formulir).toContainText('Nama ini sudah ada di Data Pagu.');
        await formulir.getByLabel('Nama kegiatan').fill(nama);
        await formulir.getByLabel('Leading').selectOption('1.4');
        await formulir.getByRole('button', { name: 'Simpan' }).click();
        await expect(page.getByText('Data pagu ditambahkan.')).toBeVisible();
        const daftar = page.getByTestId('daftar-pagu');
        await daftar.getByPlaceholder('Cari nama kegiatan…').fill(nama);
        await expect(daftar).toContainText('Rp1.000.000');

        // Impor: satu nama sudah ada (pagu berbeda → diperbarui), satu nama dobel di berkas.
        await daftar.getByPlaceholder('Cari nama kegiatan…').fill('');
        await page.getByRole('button', { name: 'Impor dari Excel' }).click();
        const csv = ['Nama Kegiatan *;Leading;Pagu DIPA;Pagu Komite', `${nama};1.4;;1.500.000`, `${nama.toUpperCase()};;;200000`].join('\n');
        await page.locator('#panel-impor-pagu input[type="file"]').setInputFiles({ name: 'pagu.csv', mimeType: 'text/csv', buffer: Buffer.from(csv) });
        await expect(page.locator('#panel-impor-pagu')).toContainText('Perbarui pagu');
        await expect(page.locator('#panel-impor-pagu')).toContainText('akan diperbarui: Pagu Komite: Rp1.000.000 → Rp1.500.000');
        await expect(page.locator('#panel-impor-pagu')).toContainText('Nama sama dengan baris 2 di berkas ini.');
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/data-pagu-impor-${testInfo.project.name}.png`, fullPage: true });
        await page.locator('#panel-impor-pagu').getByTestId('mulai-impor').click();
        await expect(page.locator('#panel-impor-pagu')).toContainText('Impor selesai');
        await daftar.getByPlaceholder('Cari nama kegiatan…').fill(nama);
        await expect(daftar).toContainText('Rp1.500.000');

        // Pulihkan (proyek mobile memakai data yang sama).
        await daftar.getByRole('button', { name: `Hapus ${nama}` }).locator('visible=true').click();
        await daftar.getByRole('group', { name: `Konfirmasi hapus ${nama}` }).locator('visible=true').getByRole('button', { name: 'Hapus' }).click();
        await expect(page.getByText('Data pagu dihapus.')).toBeVisible();
        await page.screenshot({ path: `storage/playwright/shots/data-pagu-${testInfo.project.name}.png`, fullPage: true });
    });

    test('kegiatan baru: ambil nama & pagu dari Data Pagu (combobox & tombol Buat kegiatan)', async ({ page }, testInfo) => {
        await page.goto('/kegiatan/buat');
        const ambil = page.getByTestId('ambil-data-pagu');
        await ambil.getByRole('combobox').click();
        await ambil.getByRole('searchbox').fill('karya ilmiah');
        await ambil.getByRole('option', { name: /Pelatihan Penulisan Karya Ilmiah Guru/ }).click();
        await expect(page.locator('#nama_kegiatan')).toHaveValue('Pelatihan Penulisan Karya Ilmiah Guru');
        await expect(page.getByTestId('data-pagu-tertaut')).toContainText('DIPA Rp4.500.000');
        await expect(page.locator('#pagu_dipa')).toHaveValue('4.500.000');
        await page.screenshot({ path: `storage/playwright/shots/kegiatan-dari-pagu-${testInfo.project.name}.png` });

        await page.goto('/data-pagu');
        await page.getByTestId('daftar-pagu').getByRole('link', { name: 'Buat kegiatan' }).first().click();
        await expect(page).toHaveURL(/\/kegiatan\/buat\?pagu=/);
        await expect(page.getByTestId('data-pagu-tertaut')).toBeVisible();
    });
});
