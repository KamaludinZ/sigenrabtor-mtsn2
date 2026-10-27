import { expect, test } from '@playwright/test';

test.describe('Duplikat & impor cascade Perkin', () => {
    test('layout: dari Data Kegiatan, tab duplikat tahun lalu & impor Perkin', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan');
        await page.getByTestId('tautan-salin-impor').click();
        await expect(page).toHaveURL(/\/kegiatan\/salin-impor$/);
        await expect(page.getByRole('heading', { name: 'Duplikat & Impor', level: 1 })).toBeVisible();

        const duplikat = page.getByTestId('panel-duplikat');
        await expect(duplikat.getByLabel('Tahun sumber')).toHaveValue('0');
        await expect(duplikat.getByRole('heading', { level: 2 }).first()).toContainText('TA 2025');
        await expect(page.getByTestId('perubahan-duplikat').locator('[data-perubahan]')).toHaveCount(6);
        await expect(page.getByTestId('jumlah-pilihan')).toHaveText('0 kegiatan dipilih');
        // Data E2E belum punya kegiatan TA 2025 → tombol duplikat nonaktif sampai ada pilihan.
        await expect(duplikat.getByRole('button', { name: 'Duplikat ke TA 2026' })).toBeDisabled();
        await expect(page.getByTestId('dialog-duplikat')).toHaveCount(0);

        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(lebar).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/salin-impor-${testInfo.project.name}.png`, fullPage: true });

        await page.getByTestId('tab-salin-impor').locator('[data-tab="perkin"]').click();
        await expect(page).toHaveURL(/tab=perkin/);
        await expect(page.getByTestId('kolom-perkin').locator('tr')).toHaveCount(6);

        await page.reload();
        await expect(page.getByTestId('panel-perkin')).toBeVisible();
    });

    test('impor Perkin: unggah berkas cascade lalu pratinjau per baris & susunan SK → IKSK', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/salin-impor?tab=perkin');

        const impor = page.getByTestId('panel-perkin').getByTestId('impor-bertahap');
        await expect(impor).toHaveAttribute('data-tahap', 'unggah');
        await impor.getByTestId('input-berkas-impor').setInputFiles('tests/fixtures/perkin-cascade-contoh.xlsx');
        await expect(impor).toHaveAttribute('data-tahap', 'periksa');

        // Baris gagal tampil lebih dulu bersama alasannya.
        await expect(impor.getByTestId('saring-gagal')).toHaveAttribute('aria-selected', 'true');
        await expect(impor.getByTestId('pratinjau-impor')).toContainText('IKSK 9.3 adalah indikator SK.9, bukan SK.8.');
        await impor.getByTestId('saring-siap').click();
        // Tabel di layar lebar, kartu di layar sempit — keduanya memakai saringan & pencarian yang sama.
        const tampil = impor.getByTestId('tabel-perkin').locator('[data-status]:visible');
        await expect(tampil).toHaveCount(4);
        await impor.getByTestId('cari-perkin').fill('sarana');
        await expect(tampil).toHaveCount(1);
        await expect(tampil.first()).toContainText('Pemeliharaan Sarana Kelas');
        await expect(tampil.first()).toContainText('dari sel gabung di atas');
        await impor.getByTestId('cari-perkin').fill('');
        await expect(tampil).toHaveCount(4);
        const jumlah = impor.getByTestId(testInfo.project.name === 'mobile' ? 'jumlah-perkin-ringkas' : 'jumlah-perkin');
        await expect(jumlah).toContainText('4 kegiatan');
        await expect(jumlah).toContainText('Rp12.500.000');

        const cascade = impor.getByTestId('cascade-perkin');
        await expect(cascade).toContainText('Sheet Cascade Perkin');
        await expect(cascade.locator('[data-sk]')).toHaveCount(2);
        await expect(cascade.locator('[data-sk="SK.9"] [data-iksk]')).toHaveCount(2);
        await expect(cascade.locator('[data-sk="SK.9"]')).toContainText('3 kegiatan');
        await expect(impor.getByTestId('mulai-impor')).toBeEnabled();

        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(lebar).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/impor-perkin-${testInfo.project.name}.png`, fullPage: true });

        // Konfirmasi impor: ringkasan draf yang akan dibuat; Esc kembali ke pratinjau. (E2E tidak mengimpor: data
        // kegiatan dipakai bersama proyek mobile — proses & simpan diuji di PHPUnit.)
        await impor.getByTestId('mulai-impor').click();
        const konfirmasi = impor.getByTestId('konfirmasi-impor-perkin');
        await expect(konfirmasi).toContainText('Buat 4 draf kegiatan TA 2026?');
        await expect(konfirmasi).toContainText('1 baris dilewati dan 4 baris gagal');
        await expect(impor.getByTestId('ya-impor-perkin')).toBeFocused();
        await konfirmasi.screenshot({ path: `storage/playwright/shots/konfirmasi-impor-perkin-${testInfo.project.name}.png` });
        await page.keyboard.press('Escape');
        await expect(konfirmasi).toHaveCount(0);

        // Berganti tab tidak membuang pratinjau.
        await page.getByTestId('tab-salin-impor').locator('[data-tab="duplikat"]').click();
        await expect(page.getByTestId('panel-duplikat')).toBeVisible();
        await expect(page.getByTestId('panel-perkin')).toBeHidden();
        await page.getByTestId('tab-salin-impor').locator('[data-tab="perkin"]').click();
        await expect(impor).toHaveAttribute('data-tahap', 'periksa');

        // Meninggalkan halaman selama pratinjau meminta konfirmasi; ditolak → tetap di sini.
        page.once('dialog', async (dialog) => {
            expect(dialog.message()).toContain('Pratinjau impor belum diimpor');
            await dialog.dismiss();
        });
        await page.getByTestId('panel-perkin').getByRole('link', { name: 'Buka Data Kegiatan' }).click();
        await expect(page).toHaveURL(/\/kegiatan\/salin-impor/);
        await expect(impor).toHaveAttribute('data-tahap', 'periksa');

        // Batalkan: konfirmasi inline, lalu pratinjau dibuang tanpa membuat kegiatan.
        await impor.getByTestId('batalkan-impor-perkin').click();
        await expect(impor.getByTestId('konfirmasi-batal-perkin')).toContainText('Pratinjau 4 kegiatan siap dibuang');
        await impor.getByRole('button', { name: 'Lanjutkan pratinjau' }).click();
        await expect(impor.getByTestId('konfirmasi-batal-perkin')).toHaveCount(0);
        await impor.getByTestId('batalkan-impor-perkin').click();
        await impor.getByTestId('ya-batal-perkin').click();
        await expect(impor).toHaveAttribute('data-tahap', 'unggah');
        await expect(page.getByText('Impor dibatalkan — tidak ada kegiatan yang dibuat.')).toBeVisible();
    });
});
