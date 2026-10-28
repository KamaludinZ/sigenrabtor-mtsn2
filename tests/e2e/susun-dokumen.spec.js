import { expect, test } from '@playwright/test';

test.describe('Susun KAK/TOR otomatis', () => {
    test('layout: tab ruang kerja, sembilan bagian berstatus & berasal-isi, RAB otomatis, panel samping, tanpa gulir mendatar', async ({ page }, testInfo) => {
        const mobile = testInfo.project.name.includes('mobile');
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/1/edit');
        await page.getByRole('navigation', { name: 'Bagian kegiatan' }).getByRole('link', { name: 'Susun KAK/TOR' }).click();
        await expect(page).toHaveURL(/\/kegiatan\/1\/dokumen$/);
        await expect(page.getByRole('heading', { name: 'Susun KAK/TOR', level: 1 })).toBeVisible();

        const bagian = page.getByTestId('bagian-susun');
        await expect(bagian).toHaveCount(9);
        await expect(bagian.first()).toContainText('Latar Belakang');
        await expect(bagian.nth(8)).toContainText('Lembar Pengesahan');
        await expect(bagian.locator('[data-testid="pratinjau-kak"]')).toHaveCount(9);
        await expect(page.getByTestId('ringkasan-susun')).toContainText(/\d dari 9 bagian lengkap/);

        // Asal isi bertaut ke tempat mengisi; yang diatur admin terkunci bagi akun leading.
        await expect(page.locator('#susun-bagian-1 [data-asal="identitas"]')).toHaveAttribute('href', /\/kegiatan\/1\/edit#identitas$/);
        await expect(page.locator('#susun-bagian-2 a[data-asal="dasar_hukum"]')).toHaveCount(0);

        // Ciutkan & buka semua bagian.
        await page.getByTestId('ciutkan-semua-bagian').click();
        await expect(page.locator('#isi-susun-3')).toBeHidden();
        await page.getByTestId('buka-semua-bagian').click();
        await expect(page.locator('#isi-susun-3')).toBeVisible();

        // Daftar bagian (layar sempit: bilah atas; layar lebar: panel samping) menuju kartunya.
        const navigasi = mobile ? page.getByRole('navigation', { name: 'Bagian KAK/TOR' }) : page.getByTestId('kemajuan-susun');
        await navigasi.getByRole('link', { name: /7\. Jadwal Kegiatan/ }).click();
        await expect(page).toHaveURL(/#susun-bagian-7$/);

        await expect(page.getByTestId('susun-rab')).toContainText('RAB otomatis');
        await expect(page.getByTestId('kepala-rab').first()).toContainText('Kementerian');
        await expect(page.getByTestId('draf-dokumen')).toHaveAttribute('data-ada', 'ya');
        await expect(page.getByTestId('template-susun')).toContainText('Master template');
        await expect(page.getByTestId('buka-pratinjau')).toHaveAttribute('href', /\/kegiatan\/1\/pratinjau/);

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
        await page.screenshot({ path: `storage/playwright/shots/susun-kak-tor-${testInfo.project.name}.png`, fullPage: true });
    });

    test('dua sumber dana: pilihan sumber mengganti RAB otomatis', async ({ page }) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/2/dokumen');
        const sumber = page.getByRole('group', { name: 'Sumber dana' });
        await sumber.getByRole('button', { name: 'Komite' }).click();
        await expect(page).toHaveURL(/sumber=Komite/);
        await expect(sumber.getByRole('button', { name: 'Komite' })).toHaveAttribute('aria-pressed', 'true');
        await expect(page.getByTestId('susun-rab')).toContainText('Komite');
        await expect(page.getByTestId('draf-dokumen')).toHaveAttribute('data-ada', 'tidak');
    });
});
