import { expect, test } from '@playwright/test';

test.describe('Daftar akun', () => {
    test('formulir pendaftaran: syarat sandi, NIP, galat saat blur, kirim, masuk masih menunggu', async ({ page }, testInfo) => {
        await page.goto('/masuk');
        await page.getByTestId('tautan-daftar').click();
        await expect(page).toHaveURL(/\/daftar$/);
        await expect(page.getByRole('heading', { name: 'Daftar akun', level: 1 })).toBeVisible();

        const syarat = (kunci) => page.getByTestId('syarat-sandi-daftar').locator(`[data-syarat="${kunci}"]`);
        await page.getByTestId('isian-daftar-nama').fill('Hasan Basri, S.Pd.');
        await page.getByTestId('isian-daftar-sandi').fill('basri1');
        await expect(syarat('panjang')).toHaveAttribute('data-ok', 'tidak');
        await expect(syarat('campur')).toHaveAttribute('data-ok', 'ya');
        await expect(syarat('pribadi')).toHaveAttribute('data-ok', 'tidak');
        await expect(page.getByTestId('kekuatan-sandi')).toHaveAttribute('data-tingkat', 'lemah');
        await page.getByTestId('isian-daftar-sandi').fill('Madrasah#2026');
        for (const kunci of ['panjang', 'campur', 'pribadi']) {
            await expect(syarat(kunci)).toHaveAttribute('data-ok', 'ya');
        }
        await expect(page.getByTestId('kekuatan-sandi')).toHaveAttribute('data-tingkat', 'kuat');
        await expect(page.getByTestId('kekuatan-sandi').locator('[data-nyala="ya"]')).toHaveCount(3);
        await expect(page.getByTestId('kekuatan-sandi').locator('span').first()).toHaveClass(/bg-brand-600/);
        await page.getByTestId('lihat-sandi-daftar').click();
        await expect(page.getByTestId('isian-daftar-sandi')).toHaveAttribute('type', 'text');

        await page.getByTestId('isian-daftar-sandi-ulang').fill('Madrasah#20');
        await expect(page.getByTestId('sandi-cocok')).toHaveAttribute('data-cocok', 'tidak');
        await page.getByTestId('isian-daftar-sandi-ulang').fill('Madrasah#2026');
        await expect(page.getByTestId('sandi-cocok')).toHaveAttribute('data-cocok', 'ya');

        await page.getByTestId('isian-daftar-nip').fill('19850312.201001.1.004');
        await expect(page.getByTestId('pratinjau-nip-daftar')).toHaveText('Tercetak: NIP. 19850312 201001 1 004');

        // Galat surel muncul saat blur (surel sudah dipakai akun).
        await page.getByTestId('isian-daftar-surel').fill('puskom@example.com');
        await page.getByTestId('isian-daftar-surel').blur();
        await expect(page.locator('#daftar-surel-error')).toContainText('sudah terdaftar');

        // Kirim tanpa leading & pernyataan → galat + fokus ke leading.
        const surel = `e2e-${testInfo.project.name}-${Date.now()}@example.com`;
        await page.getByTestId('isian-daftar-surel').fill(surel);
        await page.getByTestId('isian-daftar-surel').blur();
        await expect(page.locator('#daftar-surel-error')).toHaveCount(0);
        await page.getByTestId('kirim-pendaftaran').click();
        await expect(page.locator('#daftar-leadings-error')).toContainText('minimal satu leading');
        await expect(page.locator('#daftar-setuju-error')).toBeVisible();
        await expect(page.locator('#daftar-leadings')).toBeFocused();

        await page.getByTestId('pilih-leading').filter({ has: page.locator('input[value="1.4"]') }).click();
        await page.getByTestId('isian-daftar-catatan').fill('Untuk kegiatan Adiwiyata.');
        await expect(page.getByTestId('hitung-catatan')).toHaveText('25/300');
        await page.getByTestId('isian-daftar-setuju').check();
        await page.screenshot({ path: `storage/playwright/shots/daftar-${testInfo.project.name}.png`, fullPage: true });
        await page.getByTestId('kirim-pendaftaran').click();

        await expect(page.getByTestId('pendaftaran-terkirim')).toBeVisible();
        await expect(page.getByTestId('status-permohonan')).toHaveAttribute('data-status', 'menunggu');
        await expect(page.getByTestId('ringkasan-permohonan')).toContainText('1.4');
        await expect(page.getByTestId('ringkasan-permohonan')).toContainText('19850312 201001 1 004');
        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(lebar).toBeLessThanOrEqual(0);

        await page.getByTestId('ke-masuk').click();
        await page.getByTestId('isian-surel').fill(surel);
        await page.getByTestId('isian-sandi').fill('Madrasah#2026');
        await page.getByTestId('tombol-masuk').click();
        await expect(page.getByTestId('galat-surel')).toContainText('masih menunggu persetujuan admin');
    });
    test('admin menolak permohonan dari panel Leading & User', async ({ page }, testInfo) => {
        // Permohonan milik tes ini sendiri (surel unik); menyetujui tidak diuji di E2E karena membuat akun di data bersama.
        const surel = `tolak-${testInfo.project.name}-${Date.now()}@example.com`;
        await page.goto('/daftar');
        await page.getByTestId('isian-daftar-nama').fill('Pemohon Uji Tolak');
        await page.getByTestId('pilih-leading').filter({ has: page.locator('input[value="1.2"]') }).click();
        await page.getByTestId('isian-daftar-surel').fill(surel);
        await page.getByTestId('isian-daftar-sandi').fill('Madrasah#2026');
        await page.getByTestId('isian-daftar-sandi-ulang').fill('Madrasah#2026');
        await page.getByTestId('isian-daftar-setuju').check();
        await page.getByTestId('kirim-pendaftaran').click();
        await expect(page.getByTestId('pendaftaran-terkirim')).toBeVisible();

        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/leading?tab=akun');
        const panel = page.getByTestId('permohonan-akun');
        await expect(panel).toBeVisible();
        const baris = panel.getByTestId('permohonan').filter({ hasText: surel });
        await expect(baris).toBeVisible();
        await baris.getByTestId('tolak-permohonan').click();
        await baris.getByTestId('kirim-tolak').click();
        await expect(baris.locator('#alasan-tolak-error')).toBeVisible();
        await baris.getByTestId('alasan-tolak').fill('Uji E2E — permohonan ditolak.');
        await baris.getByTestId('kirim-tolak').click();
        await expect(page.locator('[data-testid="toast"]').filter({ hasText: 'Permohonan Pemohon Uji Tolak ditolak.' })).toBeVisible();
        await expect(panel.getByTestId('permohonan').filter({ hasText: surel })).toHaveCount(0);
        await page.goto('/demo/peran/leading');
    });
});
