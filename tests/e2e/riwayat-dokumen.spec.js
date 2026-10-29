import { expect, test } from '@playwright/test';

test.describe('Riwayat Dokumen', () => {
    test('layout: ringkasan, kelompok per tahun, tabel/kartu (kronologis)', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/dokumen?tampilan=kronologis');
        await expect(page.getByRole('heading', { name: 'Riwayat Dokumen', level: 1 })).toBeVisible();
        await expect(page.getByTestId('ringkasan-riwayat').locator('[data-ringkasan="arsip"]')).toContainText('1');

        const tahun = page.getByTestId('riwayat-tahun');
        await expect(tahun).toHaveCount(2);
        await expect(tahun.first()).toHaveAttribute('data-tahun', '2026');
        const mobile = testInfo.project.name === 'mobile';
        const butir = page.getByTestId(mobile ? 'kartu-riwayat' : 'dokumen-riwayat');
        await expect(butir.first()).toBeVisible();
        await expect(page.getByTestId(mobile ? 'dokumen-riwayat' : 'kartu-riwayat').first()).toBeHidden();
        await expect(page.locator(`[data-testid="${mobile ? 'kartu-riwayat' : 'dokumen-riwayat'}"][data-id="rd-2025-5"]`)).toContainText(mobile ? 'Lomba Kreativitas Siswa' : 'KAK/031/2025');

        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(lebar).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/riwayat-dokumen-${testInfo.project.name}.png`, fullPage: true });

    });

    test('tampilan per kegiatan & kronologis', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/dokumen');
        await expect(page.getByTestId('tampilan-riwayat')).toHaveAttribute('data-tampilan', 'kegiatan');
        const lomba = page.getByTestId('kegiatan-riwayat').filter({ hasText: 'Lomba Kreativitas Siswa Akhir Tahun' });
        await expect(lomba.getByTestId('kegiatan-tidak-tersimpan')).toBeVisible();
        await expect(lomba.getByTestId('dokumen-kegiatan')).toHaveAttribute('data-versi', '2');
        await expect(lomba.getByTestId('versi-sebelumnya')).toBeHidden();
        await lomba.getByTestId('versi-lama').locator('summary').click();
        await expect(lomba.getByTestId('versi-sebelumnya')).toHaveAttribute('data-versi', '1');
        await expect(page.getByTestId('kegiatan-riwayat').filter({ hasText: 'Supervisi Akademik' }).getByTestId('buka-kegiatan-riwayat')).toHaveAttribute('href', /\/kegiatan\/2\/edit$/);
        await page.screenshot({ path: `storage/playwright/shots/riwayat-per-kegiatan-${testInfo.project.name}.png`, fullPage: true });

        await page.getByTestId('tampilan-riwayat').locator('[data-pilih-tampilan="kronologis"]').click();
        await expect(page).toHaveURL(/tampilan=kronologis/);
        await page.getByTestId('urut-riwayat').selectOption('terlama');
        await expect(page).toHaveURL(/urut=terlama/);
        await expect(page.getByTestId('riwayat-tahun').first()).toHaveAttribute('data-tahun', '2025');
        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(lebar).toBeLessThanOrEqual(0);
    });

    test('pencarian kata kunci: sorotan, hitungan, pintasan, hapus', async ({ page }) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/dokumen');
        const cari = page.getByTestId('cari-riwayat');

        await page.locator('body').click({ position: { x: 5, y: 5 } });
        await page.keyboard.press('/');
        await expect(cari).toBeFocused();
        await cari.pressSequentially('lomba', { delay: 20 });
        await expect(page).toHaveURL(/cari=lomba/);
        await expect(page.getByTestId('cari-riwayat-hasil')).toContainText('2 dokumen cocok dengan “lomba” dari 9');
        await expect(page.getByTestId('kegiatan-riwayat')).toHaveCount(1);
        await expect(page.getByTestId('kegiatan-riwayat').locator('mark').first()).toHaveText(/lomba/i);

        await cari.press('Escape');
        await expect(page).not.toHaveURL(/cari=/);
        await expect(page.getByTestId('kegiatan-riwayat')).toHaveCount(4);

        await cari.fill('tidak ada sama sekali');
        await expect(page.getByTestId('riwayat-tanpa-hasil')).toBeVisible();
        await page.getByTestId('cari-riwayat-hapus').click();
        await expect(page.getByTestId('riwayat-tanpa-hasil')).toBeHidden();
        await expect(cari).toHaveValue('');

        await page.goto('/dokumen?tampilan=kronologis&cari=KAK%2F031');
        await expect(page.getByTestId('riwayat-tahun')).toHaveCount(1);
        await expect(page.getByTestId('cari-riwayat-hasil')).toContainText('1 dokumen cocok');
    });

    test('saringan jenis, format, rentang tanggal: chip, peringatan, pintasan, hapus', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/dokumen?tampilan=kronologis');
        const mobile = testInfo.project.name === 'mobile';
        const butir = (id) => page.locator(`[data-testid="${mobile ? 'kartu-riwayat' : 'dokumen-riwayat'}"][data-id="${id}"]`);
        const chip = (kunci) => page.locator(`[data-testid="chip-saringan"][data-kunci="${kunci}"]`);
        await expect(butir('rd-2026-6')).toBeVisible();

        if (mobile) {
            await expect(page.getByTestId('saring-jenis')).toBeHidden();
            await page.getByTestId('buka-saringan-riwayat').click();
            await expect(page.getByTestId('buka-saringan-riwayat')).toHaveAttribute('aria-expanded', 'true');
        }
        await page.getByTestId('saring-format').selectOption('docx');
        await expect(page).toHaveURL(/format=docx/);
        await expect(chip('format')).toContainText('Word');
        await expect(butir('rd-2026-5')).toBeVisible();
        await expect(butir('rd-2026-6')).toHaveCount(0);
        await chip('format').click();
        await expect(page).not.toHaveURL(/format=/);

        await page.getByTestId('saring-jenis').selectOption('RAB');
        await page.getByTestId('saring-dari').fill('2026-02-01');
        await page.getByTestId('saring-dari').blur();
        await expect(page).toHaveURL(/dari=2026-02-01/);
        await page.getByTestId('saring-sampai').fill('2026-02-28');
        await page.getByTestId('saring-sampai').blur();
        await expect(chip('tanggal')).toContainText('1 Februari 2026 – 28 Februari 2026');
        await expect(chip('jenis')).toContainText('RAB');
        await expect(butir('rd-2026-1')).toBeVisible();
        await expect(butir('rd-2026-6')).toHaveCount(0);
        await page.screenshot({ path: `storage/playwright/shots/riwayat-saringan-${testInfo.project.name}.png`, fullPage: true });

        await page.goto('/dokumen?tampilan=kronologis&dari=2026-03-01&sampai=2026-02-01');
        await expect(page.getByTestId('rentang-terbalik')).toBeVisible();
        await expect(chip('tanggal')).toHaveCount(0);
        await expect(butir('rd-2026-7')).toBeVisible();
        if (mobile) {
            await expect(page.getByTestId('saring-dari')).toBeVisible();
        }
        await page.getByTestId('pintasan-ta').click();
        await expect(page.getByTestId('rentang-terbalik')).toBeHidden();
        await expect(chip('tanggal')).toContainText('1 Januari 2026 – 31 Desember 2026');
        await expect(butir('rd-2025-5')).toHaveCount(0);

        await expect(page.getByTestId('saring-leading')).toHaveCount(0);
        await page.getByTestId('saring-sumber').selectOption('DIPA');
        await expect(chip('sumber')).toContainText('Sumber dana DIPA');
        await expect(page.getByTestId('saring-status').locator('option[value="draf"]')).toHaveAttribute('data-jumlah', '2');
        await page.getByTestId('saring-status').selectOption('draf');
        await expect(page).toHaveURL(/sumber=DIPA/);
        await expect(page).toHaveURL(/status=draf/);
        await expect(butir('rd-2026-1')).toBeVisible();
        await expect(butir('rd-2026-6')).toHaveCount(0);
        await chip('status').click();
        await chip('sumber').click();
        await expect(page).not.toHaveURL(/sumber=|status=/);
        await page.getByTestId('saring-jenis').selectOption('KAK');
        await expect(page.getByTestId('riwayat-tanpa-hasil')).toBeVisible();
        await expect(page.getByTestId('saran-longgar')).toHaveCount(1);
        await expect(page.locator('[data-testid="saran-longgar"][data-kunci="jenis"]')).toHaveAttribute('data-jumlah', '7');
        await page.getByTestId('tanpa-hasil-hapus').click();
        await expect(page.getByTestId('chip-saringan-riwayat')).toBeHidden();
        await expect(page).not.toHaveURL(/jenis=|dari=|sampai=/);
        await expect(butir('rd-2025-5')).toBeVisible();
        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(lebar).toBeLessThanOrEqual(0);
    });

    test('pencarian & saringan terpadu: hitungan pilihan, ringkasan, keterangan, versi lebih baru', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/dokumen');
        const mobile = testInfo.project.name === 'mobile';
        if (mobile) {
            await page.getByTestId('buka-saringan-riwayat').click();
        }
        const ringkasan = page.getByTestId('ringkasan-riwayat');
        await expect(ringkasan).toHaveAttribute('data-tersaring', '0');
        await expect(page.getByTestId('saring-format').locator('option[value="pdf"]')).toHaveAttribute('data-jumlah', '7');

        await page.getByTestId('cari-riwayat').fill('workshop');
        await expect(page).toHaveURL(/cari=workshop/);
        await expect(page.getByTestId('saring-jenis').locator('option[value="RAB"]')).toHaveAttribute('data-jumlah', '1');
        await expect(page.getByTestId('saring-format').locator('option[value="pdf"]')).toHaveAttribute('data-jumlah', '1');
        await expect(ringkasan).toHaveAttribute('data-tersaring', '1');
        await expect(ringkasan.locator('[data-ringkasan="jumlah"]')).toContainText('dari 9 dokumen');
        await expect(page.getByTestId('hasil-riwayat')).toHaveAttribute('data-jumlah', '3');

        await page.getByTestId('saring-format').selectOption('xlsx');
        await expect(page.getByTestId('hasil-riwayat')).toHaveAttribute('data-jumlah', '1');
        await expect(page.getByTestId('cari-riwayat-hasil')).toHaveText('');
        await expect(page.getByTestId('kegiatan-riwayat')).toHaveCount(1);
        await expect(page.getByTestId('jumlah-dokumen-kegiatan')).toHaveText('1 dari 3 dokumen');

        await page.getByTestId('hapus-saringan-riwayat').click();
        await expect(ringkasan).toHaveAttribute('data-tersaring', '0');
        await page.goto('/dokumen?sampai=2025-09-30');
        const lomba = page.getByTestId('kegiatan-riwayat').filter({ hasText: 'Lomba Kreativitas Siswa Akhir Tahun' });
        await expect(lomba.getByTestId('dokumen-kegiatan')).toHaveAttribute('data-versi', '1');
        await expect(lomba.getByTestId('versi-lebih-baru')).toHaveAttribute('data-versi', '2');
        await expect(lomba.getByTestId('versi-lebih-baru')).toContainText('Ada versi lebih baru: v2');
        await page.screenshot({ path: `storage/playwright/shots/riwayat-terpadu-${testInfo.project.name}.png`, fullPage: true });
        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(lebar).toBeLessThanOrEqual(0);
    });

    test('aksi lihat & duplikat: modal rincian, versi, buka ulang, rencana salinan', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/dokumen?tampilan=kronologis');
        const mobile = testInfo.project.name === 'mobile';
        const baris = page.locator(`[data-testid="${mobile ? 'kartu-riwayat' : 'dokumen-riwayat'}"][data-id="rd-2026-1"]`);
        await baris.getByTestId('lihat-dokumen').click();
        const lihat = page.getByTestId('modal-lihat-dokumen');
        await expect(lihat).toHaveAttribute('data-id', 'rd-2026-1');
        await expect(lihat.getByTestId('lihat-keadaan-versi')).toHaveText('Versi terbaru');
        await expect(lihat.getByTestId('keadaan-sekarang')).toContainText('Draf');
        await expect(lihat.getByTestId('cetak-ulang')).toHaveAttribute('href', /\/kegiatan\/2\/cetak\?sumber=DIPA&dokumen=rab/);
        await expect(lihat.getByTestId('unduh-ulang-excel')).toBeVisible();
        await expect(lihat.getByTestId('unduh-ulang-word')).toHaveCount(0);
        await page.screenshot({ path: `storage/playwright/shots/riwayat-modal-lihat-${testInfo.project.name}.png` });
        await page.keyboard.press('Escape');
        await expect(lihat).toBeHidden();

        await page.goto('/dokumen');
        const lomba = page.getByTestId('kegiatan-riwayat').filter({ hasText: 'Lomba Kreativitas Siswa Akhir Tahun' });
        await lomba.getByTestId('versi-lama').locator('summary').click();
        await lomba.getByTestId('versi-sebelumnya').getByTestId('lihat-dokumen').click();
        await expect(lihat).toHaveAttribute('data-id', 'rd-2025-4');
        await expect(lihat.getByTestId('lihat-keadaan-versi')).toHaveText('Versi lama');
        await expect(lihat.getByTestId('alasan-buka')).toContainText('tidak lagi tersimpan');
        await lihat.getByTestId('versi-dokumen').locator('[data-id="rd-2025-5"]').getByTestId('lihat-versi').click();
        await expect(lihat).toHaveAttribute('data-id', 'rd-2025-5');
        await lihat.getByTestId('lihat-ke-duplikat').click();
        const duplikat = page.getByTestId('modal-duplikat-dokumen');
        await expect(lihat).toBeHidden();
        await expect(duplikat).toHaveAttribute('data-mode', 'tidak');
        await expect(duplikat.getByTestId('duplikat-tidak-bisa')).toContainText('tidak lagi tersimpan');
        await duplikat.getByTestId('tutup-duplikat-dokumen').click();
        await expect(duplikat).toBeHidden();

        await page.getByTestId('kegiatan-riwayat').filter({ hasText: 'Supervisi Akademik' }).locator('[data-testid="duplikat-dokumen"][data-id="rd-2026-1"]').click();
        await expect(duplikat).toHaveAttribute('data-mode', 'tahun_ini');
        const nama = duplikat.getByTestId('nama-duplikat');
        await expect(nama).toHaveValue('Supervisi Akademik dan Refleksi Pembelajaran (salinan)');
        await expect(nama).toBeFocused();
        await expect(duplikat.getByTestId('perubahan-duplikat')).toContainText('Status');
        await nama.fill('Review RKJM dan Penyusunan RKTM');
        await nama.blur();
        await expect(duplikat.getByTestId('galat-nama-duplikat')).toContainText('Sudah ada kegiatan bernama');
        await page.screenshot({ path: `storage/playwright/shots/riwayat-modal-duplikat-${testInfo.project.name}.png` });
        await page.keyboard.press('Escape');
        await expect(duplikat).toBeHidden();
        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(lebar).toBeLessThanOrEqual(0);
    });

    test('konfirmasi buka ulang, notifikasi cetak ulang & duplikasi', async ({ page, context }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/dokumen?tampilan=kronologis');
        const mobile = testInfo.project.name === 'mobile';
        await page.locator(`[data-testid="${mobile ? 'kartu-riwayat' : 'dokumen-riwayat'}"][data-id="rd-2026-6"]`).getByTestId('lihat-dokumen').click();
        const lihat = page.getByTestId('modal-lihat-dokumen');
        const konfirmasi = lihat.getByTestId('konfirmasi-buka-ulang');
        await expect(konfirmasi).toContainText('Total RAB Komite kini Rp10.000.000 (saat dibuat Rp9.250.000).');
        await expect(lihat.getByTestId('tombol-buka-ulang')).toBeHidden();
        await page.screenshot({ path: `storage/playwright/shots/riwayat-konfirmasi-buka-${testInfo.project.name}.png` });
        await konfirmasi.getByTestId('setujui-buka-ulang').click();
        await expect(konfirmasi).toBeHidden();
        await expect(lihat.getByTestId('buka-ulang-disetujui')).toContainText('1 perbedaan dari catatan v1');
        await expect(lihat.getByTestId('buka-pratinjau')).toBeFocused();
        const tabBaru = context.waitForEvent('page');
        await lihat.getByTestId('cetak-ulang').click();
        const cetak = await tabBaru;
        await expect(cetak).toHaveURL(/\/kegiatan\/1\/cetak\?sumber=Komite&dokumen=rab/);
        await cetak.close();
        await expect(page.locator('[data-testid="toast"][data-jenis="info"]').filter({ hasText: 'Cetak ulang RAB dibuka di tab baru' })).toBeVisible();
        await page.keyboard.press('Escape');

        await page.locator(`[data-testid="${mobile ? 'kartu-riwayat' : 'dokumen-riwayat'}"][data-id="rd-2026-1"]`).getByTestId('duplikat-dokumen').click();
        const duplikat = page.getByTestId('modal-duplikat-dokumen');
        await expect(duplikat.getByTestId('ringkasan-salin')).toContainText('RAB DIPA');
        await duplikat.getByTestId('nama-duplikat').fill('Supervisi Akademik Tahap Dua');
        await expect(duplikat.getByTestId('akan-dibuat')).toContainText('Akan dibuat: draf “Supervisi Akademik Tahap Dua” TA 2026');
        await page.keyboard.press('Escape');
        await expect(duplikat).toBeHidden();

        await page.evaluate(() => window.dispatchEvent(new CustomEvent('notify', { detail: { message: 'Kegiatan “Contoh” dibuat sebagai draf TA 2026.', type: 'success', tautan: { label: 'Buka kegiatan baru', url: '/kegiatan/1/edit' } } })));
        const tautan = page.getByTestId('toast-tautan');
        await expect(tautan).toHaveText('Buka kegiatan baru');
        await expect(tautan).toHaveAttribute('href', '/kegiatan/1/edit');
    });
});
