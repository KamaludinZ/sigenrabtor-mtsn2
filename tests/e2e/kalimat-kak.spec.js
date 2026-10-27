import { expect, test } from '@playwright/test';

test.describe('Kalimat KAK (bantuan AI)', () => {
    test('layout enam bagian: tab ruang kerja, bahan AI, pratinjau tercetak, suntingan bisa dibatalkan, tombol & modal saran AI', async ({ page }, testInfo) => {
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

        // Buat dengan AI → modal saran: pilih versi, sunting (pratinjau tercetak ikut), Esc menutup tanpa mengubah isian.
        const isianTujuan = await page.locator('#tujuan').inputValue();
        await page.getByTestId('buat-ai-tujuan').click();
        const modal = page.getByTestId('modal-saran-tujuan');
        await expect(modal.locator('[data-versi]')).toHaveCount(3);
        await expect(page.getByTestId('buat-ai-tujuan')).toHaveText(/Lihat saran \(3\)/);
        await expect(page.getByTestId('sisa-ai')).toContainText(/Sisa \d+ dari 20 permintaan AI hari ini/);
        await modal.locator('[data-versi="2"]').click();
        await expect(modal.locator('[data-versi="2"]')).toHaveAttribute('aria-checked', 'true');
        const sunting = modal.getByTestId('sunting-saran');
        await sunting.fill('meningkatkan mutu pembelajaran yang berpihak pada murid.');
        await expect(modal.getByTestId('saran-diedit')).toBeVisible();
        await expect(modal.getByTestId('cetak-saran')).toContainText('bertujuan meningkatkan mutu pembelajaran yang berpihak pada murid.');
        await modal.screenshot({ path: `storage/playwright/shots/modal-saran-ai-${testInfo.project.name}.png` });
        // Minta perbaikan dari teks yang disunting: chip instruksi → versi baru.
        await modal.getByRole('button', { name: 'lebih singkat' }).click();
        await expect(modal.locator('#instruksi-saran')).toHaveValue('lebih singkat');
        await modal.getByTestId('minta-perbaikan').click();
        await expect(modal.locator('[data-versi="1"]')).toContainText('Lebih singkat');
        await expect(modal.locator('#instruksi-saran')).toHaveValue('');
        await page.keyboard.press('Escape');
        await expect(modal).toHaveCount(0);
        await expect(page.locator('#tujuan')).toHaveValue(isianTujuan);

        // Pakai versi ini → isian berubah (belum disimpan) dengan Urungkan.
        await page.getByTestId('buat-ai-tujuan').click();
        await expect(modal).toBeVisible();
        const versi1 = await modal.getByTestId('sunting-saran').inputValue();
        await modal.getByTestId('pakai-saran').click();
        await expect(modal).toHaveCount(0);
        await expect(page.locator('#tujuan')).toHaveValue(versi1);
        await expect(page.getByTestId('belum-disimpan')).toBeVisible();
        await page.getByTestId('saran-dipakai-tujuan').getByRole('button', { name: 'Urungkan' }).click();
        await expect(page.locator('#tujuan')).toHaveValue(isianTujuan);
        await expect(page.getByTestId('belum-disimpan')).toHaveCount(0);

        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(lebar).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/kalimat-kak-${testInfo.project.name}.png`, fullPage: true });
    });
});
