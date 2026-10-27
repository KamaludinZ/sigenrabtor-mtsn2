import { expect, test } from '@playwright/test';

test.describe('Pengaturan AI', () => {
    test('dari Ikhtisar: tab penyedia, prompt per bagian, dan batas pemakaian tanpa gulir mendatar', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan');
        await page.getByTestId('bagian-ai').getByRole('link').first().click();
        await expect(page).toHaveURL(/\/pengaturan\/ai$/);
        await expect(page.getByRole('heading', { name: 'Pengaturan AI', level: 1 })).toBeVisible();

        await expect(page.getByTestId('penyedia-aktif')).toBeVisible();
        await expect(page.getByTestId('pilihan-penyedia-ai').locator('[data-penyedia]')).toHaveCount(4);

        const tab = page.getByTestId('tab-pengaturan-ai');
        await tab.locator('[data-tab="prompt"]').click();
        await expect(page).toHaveURL(/tab=prompt/);
        await expect(page.getByTestId('panel-prompt-ai').locator('[data-prompt]')).toHaveCount(6);

        await tab.locator('[data-tab="pemakaian"]').click();
        await expect(page.getByTestId('batas-harian-ai')).toContainText('20');
        await expect(page.getByTestId('pemakaian-per-akun').locator('[data-akun]').first()).toBeVisible();

        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(lebar).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/pengaturan-ai-${testInfo.project.name}.png`, fullPage: true });

        await tab.locator('[data-tab="penyedia"]').click();
        await page.screenshot({ path: `storage/playwright/shots/pengaturan-ai-penyedia-${testInfo.project.name}.png`, fullPage: true });
    });

    test('server sekolah: atur alamat & model, pakai sebagai penyedia aktif, lalu pulihkan', async ({ page }) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/ai');
        const lokal = page.getByTestId('penyedia-lokal');
        await expect(lokal.getByTestId('peran-lokal')).toHaveText('Tidak dipakai');

        await lokal.getByTestId('atur-lokal').click();
        const formulir = lokal.getByTestId('formulir-lokal');
        await formulir.getByLabel('Alamat server').fill('ftp://server-sekolah');
        await formulir.getByLabel('Model').fill('qwen2.5:7b');
        await formulir.getByText('Penyedia aktif', { exact: true }).click();
        await formulir.getByTestId('simpan-lokal').click();
        await expect(formulir).toContainText('Alamat server harus berbentuk http(s)://alamat[:port]');

        await formulir.getByLabel('Alamat server').fill('http://192.168.1.10:11434/');
        await formulir.getByTestId('simpan-lokal').click();
        await expect(page.getByText('Konfigurasi server sekolah tersimpan.')).toBeVisible();
        await expect(lokal.getByTestId('peran-lokal')).toHaveText('Penyedia aktif');
        await expect(page.getByTestId('penyedia-aktif')).toContainText('Ollama (server sekolah)');
        await expect(page.getByTestId('penyedia-aktif')).toContainText('qwen2.5:7b');
        await expect(page.getByTestId('ai-belum-dipilih')).toHaveCount(0);

        // Pulihkan: data dipakai bersama proyek lain.
        await lokal.getByTestId('atur-lokal').click();
        await formulir.getByLabel('Alamat server').fill('http://localhost:11434');
        await formulir.getByLabel('Model').fill('');
        await formulir.getByText('Tidak dipakai', { exact: true }).click();
        await formulir.getByTestId('simpan-lokal').click();
        await expect(lokal.getByTestId('peran-lokal')).toHaveText('Tidak dipakai');
        await expect(page.getByTestId('ai-belum-dipilih')).toBeVisible();
    });

    test('pemilihan penyedia: hanya yang siap bisa dipilih, alur cadangan, lalu pulihkan', async ({ page }) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/ai');
        const lokal = page.getByTestId('penyedia-lokal');

        // Siapkan server sekolah (model diisi) tanpa dipakai.
        await lokal.getByTestId('atur-lokal').click();
        await lokal.getByLabel('Model').fill('qwen2.5:7b');
        await lokal.getByTestId('simpan-lokal').click();
        await expect(page.getByText('Konfigurasi server sekolah tersimpan.')).toBeVisible();

        await page.getByTestId('pilih-aktif').click();
        const pilih = page.getByTestId('pilih-penyedia');
        const aktif = pilih.getByTestId('pilihan-aktif');
        await expect(aktif.locator('[data-pilih-penyedia="claude"]')).toHaveAttribute('data-siap', 'tidak');
        await expect(aktif.locator('[data-pilih-penyedia="claude"] input')).toBeDisabled();
        await aktif.locator('[data-pilih-penyedia="ollama"]').click();
        await expect(pilih.getByTestId('alur-penyedia')).toContainText('Ollama (server sekolah)');
        await pilih.screenshot({ path: `storage/playwright/shots/pilih-penyedia-ai-${test.info().project.name}.png` });
        await pilih.getByTestId('simpan-pilihan').click();
        await expect(page.getByText('Penyedia AI tersimpan: Ollama (server sekolah), tanpa cadangan.')).toBeVisible();
        await expect(page.getByTestId('penyedia-aktif')).toContainText('Ollama (server sekolah)');

        // Pulihkan: tanpa penyedia, model server sekolah dikosongkan lagi.
        await page.getByTestId('pilih-aktif').click();
        await page.getByTestId('pilihan-aktif').getByText('Tanpa penyedia').click();
        await page.getByTestId('simpan-pilihan').click();
        await expect(page.getByTestId('ai-belum-dipilih')).toBeVisible();
        await lokal.getByTestId('atur-lokal').click();
        await lokal.getByLabel('Model').fill('');
        await lokal.getByTestId('simpan-lokal').click();
        await expect(lokal.getByTestId('ringkasan-lokal')).toContainText('Belum diisi');
    });
});
