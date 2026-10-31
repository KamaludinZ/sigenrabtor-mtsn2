import { expect, test } from '@playwright/test';
import path from 'node:path';

// Halaman utama Template Master & form unggah template Word berpenanda — tidak menyimpan (data E2E dipakai bersama).
test.describe('Template Master', () => {
    test('halaman utama & form unggah berpenanda', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/template/master');
        await expect(page.getByRole('heading', { name: 'Template Master KAK/TOR', level: 1 })).toBeVisible();
        await expect(page.getByTestId('ringkasan-template-master').locator('[data-ringkasan]')).toHaveCount(4);
        await expect(page.getByTestId('leading-tanpa-template')).toBeVisible();

        await page.getByTestId('unggah-template-master').click();
        const form = page.getByTestId('form-unggah-master');
        await expect(form).toBeVisible();
        await expect(form.getByTestId('simpan-template-belum')).toBeDisabled();

        // Penanda salah tulis ditandai beserta sarannya; template belum bisa disimpan.
        await form.getByTestId('input-berkas-template').setInputFiles(path.resolve('tests/fixtures/master-kak-salah.docx'));
        await expect(form.getByTestId('penanda-tidak-terdaftar')).toBeVisible();
        await expect(form.locator('[data-terdaftar="tidak"]').first()).toBeVisible();
        await expect(form.getByTestId('simpan-template-belum')).toBeDisabled();

        // Berkas benar: semua penanda terdaftar, isi dokumen berpenanda bisa dilihat, pemilik & default.
        await form.locator('#ganti-berkas-template').setInputFiles(path.resolve('tests/fixtures/master-kak-contoh.docx'));
        await expect(form.getByTestId('ringkasan-penanda')).toHaveAttribute('data-tidak-terdaftar', '0');
        await expect(form.getByTestId('simpan-template')).toBeEnabled();
        await expect(form.getByTestId('nama-template')).toHaveValue('master-kak-contoh');
        await form.getByTestId('pemilik-template').selectOption('1.6');
        await expect(form.getByTestId('pilih-default-template')).toHaveAttribute('data-ada-default', 'tidak');
        await form.getByTestId('isi-berpenanda').locator('summary').click();
        await expect(form.locator('[data-penanda="terdaftar"]').first()).toBeVisible();
        await page.screenshot({ path: `storage/playwright/shots/template-master-unggah-${testInfo.project.name}.png` });

        await page.keyboard.press('Escape');
        await expect(form).toBeHidden();
    });
});
