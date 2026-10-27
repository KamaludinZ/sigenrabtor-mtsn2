import { expect, test } from '@playwright/test';

test.describe('Kalimat KAK (bantuan AI)', () => {
    test('layout enam bagian: tab ruang kerja, bahan AI, pratinjau tercetak, suntingan bisa dibatalkan, tombol Buat dengan AI', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/1/edit');
        await page.getByRole('navigation', { name: 'Bagian kegiatan' }).getByRole('link', { name: 'Kalimat KAK' }).click();
        await expect(page).toHaveURL(/\/kegiatan\/1\/kalimat$/);
        await expect(page.getByRole('heading', { name: 'Kalimat KAK', level: 1 })).toBeVisible();

        const bagian = page.getByTestId('bagian-kalimat').locator('[data-bagian-ai]');
        await expect(bagian).toHaveCount(6);
        await expect(page.getByTestId('daftar-bagian-kalimat').locator('a')).toHaveCount(6);
        await expect(page.getByTestId('buat-ai-latar_belakang')).toBeEnabled();
        await expect(page.getByTestId('buat-ai-jadwal')).toHaveAttribute('href', /\/kegiatan\/1\/matrik#jadwal$/);
        await expect(page.getByTestId('bahan-tujuan').locator('li')).toHaveCount(4);

        // Pratinjau bagian KAK mengikuti teks di layar.
        const tujuan = page.locator('[data-bagian-ai="tujuan"]');
        await tujuan.getByText('Lihat tercetak di KAK/TOR').click();
        await expect(page.getByTestId('pratinjau-tujuan')).toContainText('Tujuan dan Sasaran');

        // Suntingan belum disimpan → penanda, lalu dibatalkan (data E2E bersama tidak diubah).
        const sasaran = page.locator('#sasaran');
        const awal = await sasaran.inputValue();
        await sasaran.fill(`${awal} Tambahan uji.`);
        await sasaran.blur();
        await expect(page.getByTestId('belum-disimpan')).toBeVisible();
        await page.getByRole('button', { name: 'Batalkan' }).click();
        await expect(page.getByTestId('belum-disimpan')).toHaveCount(0);
        await expect(sasaran).toHaveValue(awal);

        // Buat dengan AI: 2–3 versi saran tampil di kartu bagiannya; teks isian belum berubah.
        const isianTujuan = await page.locator('#tujuan').inputValue();
        await page.getByTestId('buat-ai-tujuan').click();
        const saran = page.getByTestId('saran-tujuan');
        await expect(saran.locator('[data-versi]')).toHaveCount(3);
        await expect(page.getByTestId('buat-ai-tujuan')).toHaveText(/Lihat saran \(3\)/);
        await expect(page.getByTestId('sisa-ai')).toContainText(/Sisa \d+ dari 20 permintaan AI hari ini/);
        await expect(page.locator('#tujuan')).toHaveValue(isianTujuan);
        await saran.screenshot({ path: `storage/playwright/shots/saran-ai-${testInfo.project.name}.png` });
        await saran.getByRole('button', { name: 'Tutup' }).click();
        await expect(saran).toHaveCount(0);
        await page.getByTestId('buat-ai-tujuan').click();
        await expect(page.getByTestId('saran-tujuan')).toBeVisible();

        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(lebar).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/kalimat-kak-${testInfo.project.name}.png`, fullPage: true });
    });
});
