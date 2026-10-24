import { expect, test } from '@playwright/test';

// Panel status muncul dua kali (mobile di kolom utama, desktop di samping); ambil yang terlihat.
const panelStatus = (page) => page.getByTestId('panel-status').locator('visible=true');

async function jalankanAksi(page, label) {
    await panelStatus(page).getByRole('button', { name: label, exact: true }).click();
    await panelStatus(page).getByRole('button', { name: `Ya, ${label.toLowerCase()}` }).click();
}

test.describe.serial('Status kegiatan (Draf → Final)', () => {
    test('leading mengajukan, admin menyetujui dan memfinalkan, form terkunci', async ({ page }, testInfo) => {
        // Buat kegiatan baru agar tidak bergantung pada data tes lain.
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/buat');
        await page.locator('#nama_kegiatan').fill(`Kegiatan Status ${testInfo.project.name}`);
        await page.getByRole('button', { name: 'Simpan Draf' }).first().click();
        await expect(page).toHaveURL(/\/kegiatan\/\d+\/edit$/);
        const urlKegiatan = page.url();

        await jalankanAksi(page, 'Ajukan pemeriksaan');
        await expect(page.getByText('Status kegiatan: Diperiksa.')).toBeVisible();
        await expect(page.getByTestId('banner-terkunci')).toContainText('Isian terkunci — status Diperiksa');
        await expect(page.locator('#nama_kegiatan')).toBeDisabled();
        await expect(page.getByRole('button', { name: 'Simpan Draf' })).toHaveCount(0);

        await page.goto('/demo/peran/admin');
        await page.goto(urlKegiatan);
        await jalankanAksi(page, 'Setujui');
        await expect(panelStatus(page)).toContainText('Disetujui');
        await jalankanAksi(page, 'Tandai final');
        await expect(page.getByTestId('banner-terkunci')).toContainText('status Final');
        await expect(panelStatus(page)).toContainText('Ditandai final');
        await page.screenshot({ path: `storage/playwright/shots/status-final-${testInfo.project.name}.png` });

        await jalankanAksi(page, 'Buka kunci');
        await expect(page.getByTestId('banner-terkunci')).toHaveCount(0);
        await expect(page.locator('#nama_kegiatan')).toBeEnabled();
        await expect(panelStatus(page)).toContainText('Kunci dibuka');

        await page.goto('/demo/peran/leading');
    });
});
