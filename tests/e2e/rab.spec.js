import { expect, test } from '@playwright/test';

test.describe('Halaman RAB (data tiruan)', () => {
    test('header, rincian, total, dan ringkasan pagu tampil', async ({ page }, testInfo) => {
        await page.goto('/kegiatan/1/rab');
        await expect(page.getByRole('heading', { name: 'Rencana Anggaran Biaya' })).toBeVisible();
        await expect(page.getByRole('tab', { name: /RAB Komite/ })).toHaveAttribute('aria-selected', 'true');
        await expect(page.getByTestId('header-rab')).toContainText('Tim Penjamin Mutu');
        await expect(page.getByTestId('header-rab')).toContainText('6.17.12');
        await expect(page.getByTestId('ringkasan-rab')).toContainText('Rp10.000.000');
        await expect(page.getByTestId('ringkasan-rab')).toContainText('Sisa pagu');
        if (testInfo.project.name === 'desktop') {
            await expect(page.getByTestId('total-rab')).toHaveText('10.000.000');
        }

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/rab-${testInfo.project.name}.png`, fullPage: true });
    });

    test('tab ruang kerja berpindah antara Data Kegiatan dan RAB', async ({ page }) => {
        await page.goto('/kegiatan/1/edit');
        await page.getByRole('link', { name: 'RAB', exact: true }).click();
        await expect(page).toHaveURL(/\/kegiatan\/1\/rab/);
        await page.getByRole('link', { name: 'Data Kegiatan' }).last().click();
        await expect(page).toHaveURL(/\/kegiatan\/1\/edit$/);
    });
});

test.describe('Form item belanja baru', () => {
    test('jumlah terhitung seketika, item tersimpan dan total bertambah', async ({ page }, testInfo) => {
        await page.goto('/kegiatan/buat');
        await page.locator('#nama_kegiatan').fill(`Kegiatan RAB ${testInfo.project.name}`);
        await page.getByTestId('sumber-dipa').getByRole('checkbox').check();
        await expect(page.locator('#pagu_dipa')).toBeEnabled();
        await page.locator('#pagu_dipa').pressSequentially('2000000');
        await page.getByRole('button', { name: 'Simpan & lanjut ke RAB' }).click();
        await expect(page).toHaveURL(/\/rab/);

        await page.getByRole('button', { name: 'Tambah item belanja' }).click();
        await expect(page.locator('#item_uraian')).toBeFocused();
        await page.locator('#item_uraian').fill('Honorarium Narasumber');
        await page.locator('#item_volume').fill('3');
        await page.locator('#item_satuan').fill('OJ');
        await page.locator('#item_harga_satuan').pressSequentially('600000');
        await expect(page.locator('#item_harga_satuan')).toHaveValue('600.000');
        await expect(page.getByTestId('jumlah-item')).toHaveText('Rp1.800.000');
        await expect(page.getByRole('radio', { name: 'Jasa' })).toBeChecked();
        await page.screenshot({ path: `storage/playwright/shots/form-item-${testInfo.project.name}.png` });

        await page.getByRole('button', { name: 'Simpan & tambah lagi' }).click();
        await expect(page.getByText('Item ditambahkan: Honorarium Narasumber (Rp1.800.000).')).toBeVisible();
        await expect(page.locator('#item_uraian')).toHaveValue('');
        await expect(page.locator('#item_uraian')).toBeFocused();

        await page.locator('#item_uraian').fill('Nasi Kotak');
        await page.locator('#item_volume').fill('10');
        await page.locator('#item_satuan').fill('kotak');
        await page.locator('#item_harga_satuan').pressSequentially('30000');
        await page.getByRole('button', { name: 'Simpan item' }).click();
        await expect(page.getByTestId('form-item')).toHaveCount(0);
        await expect(page.getByTestId('ringkasan-rab')).toContainText('Rp2.100.000');
        await expect(page.getByTestId('ringkasan-rab')).toContainText('Melebihi pagu');
    });
});
