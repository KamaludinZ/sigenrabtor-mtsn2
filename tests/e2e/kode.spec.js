import { expect, test } from '@playwright/test';

test.describe('Kode & Klasifikasi', () => {
    test.afterEach(async ({ page }) => {
        await page.goto('/demo/peran/leading');
    });

    test('pemilih leading: akun leading satu kartu, admin semua & bisa memilih', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/1/kode');
        await expect(page.locator('[data-leading]')).toHaveCount(1);
        await expect(page.getByText('Akun Anda terikat ke leading ini.')).toBeVisible();

        await page.goto('/demo/peran/admin');
        await page.goto('/kegiatan/1/kode');
        await expect(page.locator('[data-leading]')).toHaveCount(8);
        await page.locator('[data-leading="1.4"]').click();
        await expect(page.getByRole('radio', { name: /Waka Humas/ })).toBeChecked();
        await expect(page.getByTestId('pratinjau-header')).toContainText('Waka Humas');
        await page.screenshot({ path: `storage/playwright/shots/pilih-leading-${testInfo.project.name}.png`, fullPage: true });

        // Kembalikan pilihan agar data contoh tetap seperti semula untuk tes lain.
        await page.locator('[data-leading="1.6"]').click();
        await expect(page.getByTestId('pratinjau-header')).toContainText('Tim Penjamin Mutu');
    });

    test('combobox RKTM & EDM: cari, keyboard, kosongkan', async ({ page }, testInfo) => {
        await page.goto('/kegiatan/1/kode');
        const rktm = page.locator('#kode_rktm');
        await expect(rktm).toContainText('6.17.12');

        await rktm.click();
        await expect(page.locator('#kode_rktm-cari')).toBeFocused();
        await page.locator('#kode_rktm-cari').fill('tahfiz');
        await expect(page.locator('#kode_rktm-daftar [role=option]')).toHaveCount(1);
        await expect(page.locator('#kode_rktm-daftar')).toContainText('Program Tahfiz');
        await page.screenshot({ path: `storage/playwright/shots/combobox-rktm-${testInfo.project.name}.png` });
        await page.locator('#kode_rktm-cari').press('Enter');
        await expect(rktm).toContainText('2.2.13');
        await expect(page.getByTestId('pratinjau-header')).toContainText('(2.2.13)');

        // Keyboard: buka dengan panah bawah, Esc menutup tanpa mengubah.
        await rktm.focus();
        await page.keyboard.press('ArrowDown');
        await expect(page.locator('#kode_rktm-daftar')).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.locator('#kode_rktm-daftar')).toBeHidden();
        await expect(rktm).toContainText('2.2.13');

        // EDM: cari per komponen lalu kosongkan.
        await page.locator('#kode_edm').click();
        await page.locator('#kode_edm-cari').fill('pembiayaan');
        await expect(page.locator('#kode_edm-daftar [role=option]')).toHaveCount(3);
        await page.locator('#kode_edm-daftar [data-nilai="E1"]').click();
        await expect(page.locator('#kode_edm')).toContainText('E1');
        await page.getByRole('button', { name: 'Kosongkan pilihan' }).nth(1).click();
        await expect(page.locator('#kode_edm')).toContainText('Pilih kode EDM');

        // Pulihkan data contoh untuk tes lain.
        await page.reload();
    });

    test('halaman kode tidak menimbulkan gulir horizontal (teks kode panjang terpotong rapi)', async ({ page }) => {
        await page.goto('/kegiatan/1/kode');
        const luber = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(luber).toBeLessThanOrEqual(0);
    });

    test('IKSK terfilter: mengikuti SK dan terpilih otomatis bila hanya satu', async ({ page }) => {
        await page.goto('/kegiatan/1/kode');
        await expect(page.locator('#kode_iksk')).toContainText('IKSK 10.6');

        await page.locator('#kode_sk').click();
        await page.locator('#kode_sk-cari').fill('SK.12');
        await page.locator('#kode_sk-cari').press('Enter');
        await expect(page.getByText('IKSK IKSK 12.1 dipilih otomatis')).toBeVisible();
        await expect(page.locator('#kode_iksk')).toContainText('IKSK 12.1');

        await page.locator('#kode_sk').click();
        await page.locator('#kode_sk-cari').fill('SK.9');
        await page.locator('#kode_sk-cari').press('Enter');
        await expect(page.locator('#kode_iksk')).toContainText('Pilih kode IKSK');
        await page.locator('#kode_iksk').click();
        await expect(page.locator('#kode_iksk-daftar [role=option]')).toHaveCount(4);

        await page.reload();
    });

    test('ringkasan kode: jumlah terpilih, uraian lengkap bisa dibuka, ikut berubah', async ({ page }, testInfo) => {
        await page.goto('/kegiatan/1/kode');
        const ringkasan = page.getByTestId('ringkasan-kode-terpilih');
        await expect(ringkasan.getByTestId('ringkasan-kode-jumlah')).toHaveText('6/6 dipilih');

        // Detail EDM tersembunyi sampai uraiannya diklik.
        const edm = ringkasan.locator('[data-kode="edm"]');
        await expect(edm.getByText('Komponen C · Proses Pembelajaran')).toBeHidden();
        await edm.getByRole('button').click();
        await expect(edm.getByText('Komponen C · Proses Pembelajaran')).toBeVisible();
        await page.screenshot({ path: `storage/playwright/shots/ringkasan-kode-${testInfo.project.name}.png`, fullPage: true });

        // Mengosongkan IKSK langsung tercermin di ringkasan.
        await page.locator('#kode_sk').click();
        await page.locator('#kode_sk-cari').fill('SK.9');
        await page.locator('#kode_sk-cari').press('Enter');
        await expect(ringkasan.getByTestId('ringkasan-kode-jumlah')).toHaveText('5/6 dipilih');
        await expect(ringkasan.locator('[data-kode="iksk"]')).toContainText('belum dipilih');

        // Teks salinan mengikuti pilihan terbaru, bukan pilihan saat halaman dimuat.
        await page.context().grantPermissions(['clipboard-read', 'clipboard-write']);
        await ringkasan.getByTestId('salin-ringkasan-kode').click();
        await expect(ringkasan.getByTestId('salin-ringkasan-kode')).toContainText('Tersalin');
        const salinan = await page.evaluate(() => navigator.clipboard.readText());
        expect(salinan).toContain('Kode SK: SK.9 — ');
        expect(salinan).toContain('IKSK: (belum dipilih)');

        await page.reload();
    });

    test('notifikasi kelengkapan kode: banner, tautan ke kartu kosong, lencana tab', async ({ page }, testInfo) => {
        await page.goto('/kegiatan/1/kode');
        const banner = page.getByTestId('status-kelengkapan-kode');
        await expect(banner).toHaveAttribute('data-status', 'lengkap');
        await expect(banner).toContainText('Semua kode lengkap');
        await expect(page.getByTestId('lencana-kode')).toHaveAttribute('data-status', 'lengkap');

        // Kosongkan EDM: banner jadi sebagian dan menautkan kartu EDM.
        await page.locator('#kode-edm').getByRole('button', { name: 'Kosongkan pilihan' }).click();
        await expect(banner).toHaveAttribute('data-status', 'sebagian');
        await expect(banner).toContainText('5 dari 6 kode terpilih.');
        await expect(page.getByTestId('lencana-kode')).toHaveText('5/6');
        await banner.getByRole('link', { name: 'Kode EDM' }).click();
        await expect(page).toHaveURL(/#kode-edm$/);
        await expect(page.locator('#kode-edm')).toBeInViewport();
        await page.screenshot({ path: `storage/playwright/shots/status-kelengkapan-${testInfo.project.name}.png` });

        await page.goto('/kegiatan/1/kode');
        await expect(banner).toHaveAttribute('data-status', 'lengkap');
    });

    test('submit kode: Ctrl+S, riwayat, bentrok antartab, batalkan', async ({ page, context }, testInfo) => {
        await page.goto('/kegiatan/buat');
        await page.locator('#nama_kegiatan').fill(`Simpan Kode ${testInfo.project.name}`);
        await page.getByTestId('sumber-dipa').getByRole('checkbox').check();
        await expect(page.locator('#pagu_dipa')).toBeEnabled();
        await page.locator('#pagu_dipa').pressSequentially('1000000');
        await page.getByRole('button', { name: 'Simpan & lanjut ke RAB' }).click();
        await expect(page).toHaveURL(/\/rab/);
        const urlKode = page.url().replace(/\/rab.*$/, '/kode');

        // Tab A: pilih SK lalu simpan dengan Ctrl+S.
        await page.goto(urlKode);
        await page.locator('#kode_sk').click();
        await page.locator('#kode_sk-cari').fill('SK.12');
        await page.locator('#kode_sk-cari').press('Enter');
        await expect(page.getByTestId('belum-disimpan')).toBeVisible();
        await page.keyboard.press('Control+s');
        await expect(page.getByText(/Kode & klasifikasi tersimpan\./)).toBeVisible();
        await expect(page.getByTestId('belum-disimpan')).toBeHidden();
        await expect(page.getByTestId('info-simpan')).toContainText('Terakhir disimpan');
        await page.getByTestId('riwayat-kode').locator('summary').click();
        await expect(page.getByTestId('riwayat-kode')).toContainText('Kode SK: kosong → SK.12');

        // Tab B menyimpan EDM lebih dulu.
        const tabB = await context.newPage();
        await tabB.goto(urlKode);
        await tabB.locator('#kode_edm').click();
        await tabB.locator('#kode_edm-cari').fill('C1');
        await tabB.locator('#kode_edm-cari').press('Enter');
        await tabB.getByRole('button', { name: 'Simpan', exact: true }).click();
        await expect(tabB.getByText(/Kode & klasifikasi tersimpan\./)).toBeVisible();
        await tabB.close();

        // Tab A: simpan budaya mutu → bentrok, lalu muat versi terbaru.
        await page.getByText('3. Inovasi').click();
        await page.getByRole('button', { name: 'Simpan', exact: true }).click();
        const bentrok = page.getByTestId('banner-bentrok');
        await expect(bentrok).toContainText('Kode EDM: kosong → C1');
        await page.screenshot({ path: `storage/playwright/shots/bentrok-kode-${testInfo.project.name}.png` });
        await bentrok.getByRole('button', { name: 'Muat versi terbaru' }).click();
        await expect(bentrok).toBeHidden();
        await expect(page.locator('#kode_edm')).toContainText('C1');
        await expect(page.getByRole('radio', { name: '3. Inovasi' })).not.toBeChecked();

        // Batalkan perubahan yang belum disimpan.
        await page.getByText('4. Tanggung Jawab').click();
        await expect(page.getByTestId('belum-disimpan')).toBeVisible();
        await page.getByRole('button', { name: 'Batalkan' }).click();
        await expect(page.getByTestId('belum-disimpan')).toBeHidden();
        await expect(page.getByRole('radio', { name: '4. Tanggung Jawab' })).not.toBeChecked();
    });

    test('ringkasan kode di Data Kegiatan memuat uraian kode', async ({ page }) => {
        await page.goto('/kegiatan/1/edit');
        const ringkasan = page.getByTestId('ringkasan-kode');
        await expect(ringkasan.getByTestId('ringkasan-kode-jumlah')).toHaveText('6/6 dipilih');
        await expect(ringkasan.locator('[data-kode="sk"]')).toContainText('Meningkatnya layanan pendidikan');
    });
});
