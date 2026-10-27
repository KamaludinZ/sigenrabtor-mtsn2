import { expect, test } from '@playwright/test';

test.describe('Pemeriksaan', () => {
    test('layout: khusus admin, tab status dengan jumlah, cari & saringan di URL', async ({ page }, testInfo) => {
        const ditolak = await page.goto('/pemeriksaan');
        expect(ditolak.status()).toBe(403);

        await page.goto('/demo/peran/admin');
        await page.goto('/pemeriksaan');
        await expect(page.getByRole('heading', { name: 'Pemeriksaan', level: 1 })).toBeVisible();
        const tab = page.getByTestId('tab-pemeriksaan').getByRole('button');
        await expect(tab).toHaveCount(5);
        await expect(tab.first()).toHaveAttribute('aria-pressed', 'true');
        await expect(page.getByRole('heading', { name: 'Perlu diperiksa', level: 2 })).toBeVisible();

        // Data E2E dipakai bersama proyek lain: cukup pastikan contoh kegiatan ada di tab Belum diajukan.
        await page.getByTestId('tab-pemeriksaan').locator('[data-tab="draf"]').click();
        await expect(page).toHaveURL(/status=draf/);
        await expect(page.getByRole('heading', { name: 'Belum diajukan', level: 2 })).toBeVisible();
        const daftar = page.getByTestId('daftar-pemeriksaan');
        await expect(daftar.locator('li[data-kegiatan="1"]')).toBeVisible();
        await expect(daftar.locator('li[data-kegiatan="3"]').locator('[data-status-pagu="melebihi"]')).toBeVisible();

        await daftar.getByRole('searchbox', { name: 'Cari kegiatan' }).fill('Supervisi Akademik');
        await expect(page).toHaveURL(/cari=Supervisi/);
        await expect(daftar.locator('li[data-kegiatan="2"]')).toBeVisible();
        await expect(daftar.locator('li[data-kegiatan="1"]')).toHaveCount(0);

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/pemeriksaan-${testInfo.project.name}.png`, fullPage: true });

        await daftar.getByRole('searchbox', { name: 'Cari kegiatan' }).fill('tidak ada kegiatan seperti ini');
        await expect(page.getByTestId('pemeriksaan-kosong')).toContainText('Tidak ada kegiatan yang cocok.');
        await page.getByRole('button', { name: 'Hapus saringan' }).click();
        await expect(page).not.toHaveURL(/cari=/);
        await expect(daftar.locator('li[data-kegiatan="1"]')).toBeVisible();

        await page.goto('/demo/peran/leading');
    });

    test('detail dokumen: KAK/TOR & RAB per sumber dana, kelengkapan, anggaran, riwayat', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pemeriksaan?status=draf');
        await page.getByTestId('daftar-pemeriksaan').locator('li[data-kegiatan="2"]').getByRole('link', { name: 'Lihat dokumen' }).click();
        await expect(page).toHaveURL(/\/pemeriksaan\/2$/);
        await expect(page.getByRole('heading', { name: 'Supervisi Akademik dan Refleksi Pembelajaran', level: 1 })).toBeVisible();
        await expect(page.getByTestId('pratinjau-kak')).toContainText('Sumber dana: DIPA');
        await expect(page.getByTestId('panel-keputusan')).toContainText('Belum diajukan');
        await expect(page.getByTestId('kelengkapan-dokumen').locator('li[data-kelengkapan]')).toHaveCount(2);
        await expect(page.getByTestId('ringkasan-anggaran').locator('[data-sumber="Komite"]')).toContainText('Sesuai pagu');

        const pratinjau = page.getByTestId('pratinjau-pemeriksaan');
        await pratinjau.getByRole('group', { name: 'Jenis dokumen' }).getByRole('button', { name: 'RAB' }).click();
        await expect(page).toHaveURL(/dokumen=rab/);
        await expect(page.getByTestId('pratinjau-rab')).toContainText('Rencana Anggaran Biaya Kegiatan Supervisi Akademik dan Refleksi Pembelajaran');
        await expect(page.getByTestId('tabel-rab-dipa')).toBeVisible();

        await page.getByTestId('kelengkapan-dokumen').locator('[data-dokumen="rab-komite"]').click();
        await expect(page).toHaveURL(/sumber=Komite/);
        await expect(page.getByTestId('tabel-rab-komite')).toBeVisible();
        await expect(page.getByTestId('kelengkapan-dokumen').locator('[data-dokumen="rab-komite"]')).toHaveAttribute('aria-pressed', 'true');

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/pemeriksaan-detail-${testInfo.project.name}.png`, fullPage: true });

        await page.reload();
        await expect(page.getByTestId('tabel-rab-komite')).toBeVisible();
        await page.getByRole('navigation', { name: 'Breadcrumb' }).getByRole('link', { name: 'Pemeriksaan' }).click();
        await expect(page).toHaveURL(/\/pemeriksaan\?status=draf$/);

        await page.goto('/demo/peran/leading');
    });
});
