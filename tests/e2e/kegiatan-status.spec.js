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

        // Pengajuan tanpa kode SK ditolak; SK dipilih di tab Kode & Klasifikasi.
        await jalankanAksi(page, 'Ajukan pemeriksaan');
        await expect(page.getByTestId('error-kode-sk')).toBeVisible();
        await page.getByRole('link', { name: 'Kode & Klasifikasi', exact: true }).click();
        await page.locator('#kode_sk').click();
        await page.locator('#kode_sk-cari').fill('SK.12');
        await page.locator('#kode_sk-cari').press('Enter');
        await expect(page.getByTestId('uraian-sk')).toContainText('perencanaan dan anggaran');
        await page.getByRole('button', { name: 'Simpan', exact: true }).click();
        await expect(page.getByText(/Kode & klasifikasi tersimpan\. \d dari 6 kode terpilih — belum: /)).toBeVisible();
        await page.goto(urlKegiatan);
        await expect(page.getByTestId('lencana-kode')).toHaveAttribute('data-status', 'sebagian');

        // Isian pelaksanaan masih kosong: pengajuan ditolak dan isian wajib ditandai.
        await jalankanAksi(page, 'Ajukan pemeriksaan');
        await expect(page.getByText(/Lengkapi \d isian yang ditandai merah sebelum mengajukan\./)).toBeVisible();
        await expect(page.locator('#tempat-error')).toContainText('Tempat pelaksanaan wajib diisi sebelum kegiatan diajukan');

        await page.locator('#tanggal_mulai').fill('2026-07-14');
        await page.locator('#lama_hari').fill('2');
        await page.locator('#tempat').fill('Aula Madrasah');
        await page.locator('#peserta_jumlah').fill('40');
        await page.locator('#peserta_jenis').fill('Guru');
        await page.locator('#peserta_jenis').blur();
        await expect(page.locator('#tempat-error')).toHaveCount(0);

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
