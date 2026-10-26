import { expect, test } from '@playwright/test';

test.describe('Pengaturan admin', () => {
    test('ikhtisar: khusus admin, peta bagian & kesiapan tahun anggaran', async ({ page }, testInfo) => {
        // Peran bawaan data tiruan: leading → ditolak.
        const ditolak = await page.goto('/pengaturan');
        expect(ditolak.status()).toBe(403);

        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan');
        await expect(page.getByRole('heading', { name: 'Pengaturan', level: 1 })).toBeVisible();
        await expect(page.getByTestId('kepala-pengaturan')).toContainText('Berlaku TA 2026');

        const kesiapan = page.getByTestId('kesiapan-pengaturan');
        await expect(kesiapan).toContainText('Kesiapan Tahun Anggaran 2026');
        await expect(page.getByTestId('jumlah-wajib-siap')).toHaveText('4/7 wajib siap');
        await expect(kesiapan.locator('li[data-siap="tidak"]')).toHaveCount(6);
        await expect(page.locator('article[data-testid^="bagian-"]')).toHaveCount(6);
        await expect(page.getByTestId('bagian-madrasah')).toContainText('Segera disiapkan');
        await expect(page.getByTestId('bagian-tahun').getByRole('link', { name: 'Buka Tahun Anggaran' })).toBeVisible();

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);

        if (testInfo.project.name === 'mobile') {
            // Layar sempit: kesiapan (yang perlu dikerjakan) tampil sebelum peta bagian.
            const atasKesiapan = (await kesiapan.boundingBox()).y;
            const atasBagian = (await page.getByTestId('bagian-tahun').boundingBox()).y;
            expect(atasKesiapan).toBeLessThan(atasBagian);
        } else {
            const menu = page.getByRole('navigation', { name: 'Menu utama' }).first();
            await expect(menu.getByRole('link', { name: 'Ikhtisar Pengaturan' })).toHaveAttribute('aria-current', 'page');
        }

        // Butir kesiapan tanpa halaman menuju kartu bagiannya.
        await kesiapan.getByRole('link', { name: 'Master kode 2026' }).click();
        await expect(page).toHaveURL(/#bagian-master$/);
        await page.screenshot({ path: `storage/playwright/shots/pengaturan-ikhtisar-${testInfo.project.name}.png`, fullPage: true });
    });

    test('tahun anggaran: status, pilih tahun aktif lewat konfirmasi, berlaku di seluruh aplikasi', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/tahun');
        await expect(page.getByTestId('tahun-aktif')).toContainText('2026');
        await expect(page.getByTestId('sk-belum-diisi')).toBeVisible();
        await expect(page.getByTestId('tahun-2025')).toHaveAttribute('data-status', 'terkunci');
        const chip = page.getByTestId('chip-tahun-aktif');
        await expect(chip).toContainText('2026');

        // Batal lewat Esc tidak mengubah apa pun.
        await page.getByRole('button', { name: 'Jadikan 2025 tahun aktif' }).click();
        const dialog = page.getByTestId('konfirmasi-tahun');
        await expect(dialog).toContainText('Aktifkan Tahun Anggaran 2025?');
        await expect(dialog.getByTestId('peringatan-terkunci')).toBeVisible();
        await page.screenshot({ path: `storage/playwright/shots/tahun-anggaran-konfirmasi-${testInfo.project.name}.png` });
        await page.keyboard.press('Escape');
        await expect(dialog).toHaveCount(0);
        await expect(page.getByTestId('tahun-2026')).toHaveAttribute('data-status', 'aktif');

        try {
            await page.getByRole('button', { name: 'Jadikan 2025 tahun aktif' }).click();
            await dialog.getByRole('button', { name: 'Ya, aktifkan 2025' }).click();
            await expect(page.getByText('Tahun Anggaran 2025 aktif. Seluruh dokumen kini memakai tahun 2025.')).toBeVisible({ timeout: 30_000 });
            await expect(page.getByTestId('tahun-2025')).toHaveAttribute('data-status', 'aktif');
            await expect(chip).toContainText('2025');
            await expect(page.getByTestId('kepala-pengaturan')).toContainText('Berlaku TA 2025');
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            expect(overflow).toBeLessThanOrEqual(0);
            await page.screenshot({ path: `storage/playwright/shots/tahun-anggaran-${testInfo.project.name}.png`, fullPage: true });
        } finally {
            // Kembalikan 2026 agar tes lain tetap memakai tahun contoh.
            await page.goto('/pengaturan/tahun');
            if ((await page.getByTestId('tahun-2026').getAttribute('data-status')) !== 'aktif') {
                await page.getByRole('button', { name: 'Jadikan 2026 tahun aktif' }).click();
                await page.getByTestId('konfirmasi-tahun').getByRole('button', { name: 'Ya, aktifkan 2026' }).click();
                await expect(page.getByTestId('tahun-2026')).toHaveAttribute('data-status', 'aktif', { timeout: 30_000 });
            }
        }
        await expect(chip).toContainText('2026');
    });

    test('template cover: unggah, pratinjau dengan kotak teks, simpan sebagai default, hapus', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/template');
        await expect(page.getByTestId('cover-kosong')).toBeVisible();
        await expect(page.getByTestId('sumber-pratinjau')).toHaveAttribute('data-jenis', 'baku');

        await page.getByTestId('input-gambar-cover').setInputFiles('tests/fixtures/cover-contoh.png');
        await expect(page.getByTestId('info-gambar')).toContainText('1.240 × 1.754 piksel', { timeout: 30_000 });
        await expect(page.getByTestId('rasio-gambar')).toHaveAttribute('data-a4', 'ya');
        await expect(page.getByTestId('sumber-pratinjau')).toHaveAttribute('data-jenis', 'baru');
        const cover = page.getByTestId('pratinjau-cover');
        await expect.poll(() => cover.getByTestId('gambar-cover').evaluate((img) => img.complete && img.naturalWidth)).toBe(1240);
        await expect(cover.locator('[data-field="nama_kegiatan"]')).toContainText('KEGIATAN');
        await expect(cover.locator('[data-field]')).toHaveCount(8);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.getByTestId('panel-pratinjau-cover').screenshot({ path: `storage/playwright/shots/cover-pratinjau-${testInfo.project.name}.png` });

        await page.getByRole('button', { name: 'Simpan sebagai cover default' }).click();
        await expect(page.getByText('Cover tersimpan.')).toBeVisible({ timeout: 30_000 });
        const tersimpan = page.getByTestId('cover-tersimpan');
        await expect(tersimpan.getByTestId('cover-1')).toContainText('Cover default 2026');
        await expect(tersimpan.getByTestId('cover-1')).toContainText('Default');
        await expect(page.getByText('Mengunggah…')).toBeHidden();
        await expect(page.getByTestId('sumber-pratinjau')).toHaveAttribute('data-jenis', 'tersimpan');
        await expect.poll(() => cover.getByTestId('gambar-cover').evaluate((img) => img.complete && img.naturalWidth)).toBe(1240);

        // Kotak teks bisa disembunyikan agar desain terlihat utuh.
        await page.getByLabel('Tampilkan kotak teks').uncheck();
        await expect(cover.locator('[data-field="judul"]')).not.toHaveClass(/outline-dashed/);
        await page.screenshot({ path: `storage/playwright/shots/cover-tersimpan-${testInfo.project.name}.png`, fullPage: true });

        // Hapus dengan konfirmasi agar data E2E kembali kosong.
        await tersimpan.getByRole('button', { name: 'Hapus Cover default 2026' }).click();
        await tersimpan.getByRole('button', { name: 'Hapus', exact: true }).click();
        await expect(page.getByTestId('cover-kosong')).toBeVisible({ timeout: 30_000 });
        await expect(page.getByTestId('sumber-pratinjau')).toHaveAttribute('data-jenis', 'baku');
    });
});
