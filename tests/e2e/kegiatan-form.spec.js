import { expect, test } from '@playwright/test';

test.describe('Form Data Kegiatan', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/kegiatan/buat');
        await expect(page.getByRole('heading', { name: 'Kegiatan Baru' })).toBeVisible();
    });

    test('tidak ada scroll horizontal dan semua bagian tampil', async ({ page }) => {
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);

        for (const judul of ['Identitas Kegiatan', 'Kode & Klasifikasi', 'Pelaksanaan', 'Konteks Kegiatan', 'Pelaksana & Panitia', 'Sumber Dana']) {
            await expect(page.locator('section h2', { hasText: judul })).toBeVisible();
        }
    });

    test('identitas: nama cover kapital otomatis dan kalimat terpotong ditolak', async ({ page }) => {
        await page.locator('#nama_kegiatan').fill('Workshop Penyusunan Kurikulum Madrasah');
        await expect(page.locator('#nama_kegiatan_cover')).toHaveValue('WORKSHOP PENYUSUNAN KURIKULUM MADRASAH');

        await page.locator('#sasaran').fill('dokumen perencanaan yang dibutuhkan oleh');
        await page.locator('#tujuan').click();
        await expect(page.getByText('tampak terpotong (diakhiri kata “oleh”)')).toBeVisible();
    });

    test('uraian: tombol rapikan spasi menormalkan teks', async ({ page }) => {
        const tujuan = page.locator('#tujuan');
        await tujuan.fill('menghasilkan   dokumen    kurikulum');
        await page.locator('#tujuan').locator('xpath=ancestor::div[@x-data][1]').getByRole('button', { name: 'Rapikan spasi' }).click();
        await expect(tujuan).toHaveValue('menghasilkan dokumen kurikulum');
    });

    test('panitia: susunan baku, isi nama, geser, hapus', async ({ page }) => {
        const bagian = page.locator('#panitia');
        await bagian.getByRole('button', { name: 'Pakai susunan baku' }).click();
        await expect(bagian.locator('[data-panitia-row]')).toHaveCount(4);
        await expect(page.locator('#panitia-0-nama')).toBeFocused();

        await page.locator('#panitia-0-nama').fill('Ahmad Fauzi, S.Pd.');
        await page.locator('#panitia-0-jabatan').fill('Waka Kurikulum');
        await bagian.getByRole('button', { name: 'Turunkan anggota 1' }).click();
        await expect(page.locator('#panitia-1-nama')).toHaveValue('Ahmad Fauzi, S.Pd.');
        await expect(page.locator('#panitia-1-peran')).toHaveValue('Ketua');

        await bagian.getByRole('button', { name: 'Hapus anggota 4' }).click();
        await expect(bagian.locator('[data-panitia-row]')).toHaveCount(3);

        await page.screenshot({ path: `storage/playwright/shots/panitia-${test.info().project.name}.png`, fullPage: false });
    });

    test('panitia: seret-lepas, urungkan hapus, dan urutkan sesuai peran', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name === 'mobile', 'Seret-lepas diuji di desktop; mobile memakai tombol naik/turun.');
        const bagian = page.locator('#panitia');
        await bagian.getByRole('button', { name: 'Pakai susunan baku' }).click();
        await expect(bagian.locator('[data-panitia-row]')).toHaveCount(4);

        // Seret baris "Anggota" (4) ke paling atas.
        const handle = bagian.locator('[data-panitia-row]').nth(3).locator('[wire\\:sort\\:handle]');
        await handle.dragTo(bagian.locator('[data-panitia-row]').nth(0).locator('[wire\\:sort\\:handle]'));
        await expect(page.locator('#panitia-0-peran')).toHaveValue('Anggota');

        await bagian.getByRole('button', { name: 'Urutkan sesuai peran' }).click();
        await expect(page.locator('#panitia-0-peran')).toHaveValue('Ketua');
        await expect(page.locator('#panitia-3-peran')).toHaveValue('Anggota');

        await bagian.getByRole('button', { name: 'Hapus anggota 2' }).click();
        await expect(bagian.locator('[data-panitia-row]')).toHaveCount(3);
        await bagian.getByRole('button', { name: 'Urungkan' }).click();
        await expect(bagian.locator('[data-panitia-row]')).toHaveCount(4);
        await expect(page.locator('#panitia-1-peran')).toHaveValue('Sekretaris');
    });

    test('jadwal: tanggal selesai, bulan, dan batas laporan dihitung otomatis', async ({ page }) => {
        await page.locator('#tanggal_mulai').fill('2026-07-30');
        await page.locator('#lama_hari').fill('4');
        await expect(page.getByTestId('tanggal-selesai')).toHaveText('Minggu, 2 Agustus 2026');
        await expect(page.locator('#pelaksanaan').getByRole('button', { name: 'Jul' })).toHaveAttribute('aria-pressed', 'true');
        await expect(page.locator('#pelaksanaan').getByRole('button', { name: 'Agu' })).toHaveAttribute('aria-pressed', 'true');
        await expect(page.locator('#tanggal_laporan')).toHaveValue('2026-08-12');

        await page.getByLabel('Hitung hari kerja saja').check();
        await expect(page.getByTestId('tanggal-selesai')).toHaveText('Selasa, 4 Agustus 2026');
        await expect(page.locator('#tanggal_laporan')).toHaveValue('2026-08-14');

        await page.locator('#pelaksanaan').scrollIntoViewIfNeeded();
        await page.screenshot({ path: `storage/playwright/shots/jadwal-${test.info().project.name}.png` });
    });

    test('bulan: klik & Shift+klik rentang tampil seketika, bertahan setelah render ulang, bisa dikosongkan', async ({ page }) => {
        const pemilih = page.getByTestId('pemilih-bulan');
        const bulan = (n) => pemilih.locator(`[data-bulan="${n}"]`);

        await bulan(3).click();
        await bulan(6).click({ modifiers: ['Shift'] });
        for (const n of [3, 4, 5, 6]) {
            await expect(bulan(n)).toHaveAttribute('aria-pressed', 'true');
        }
        await expect(bulan(7)).toHaveAttribute('aria-pressed', 'false');
        await expect(pemilih).toContainText('Tercetak: Maret – Juni 2026');

        // Klik ulang membatalkan satu bulan; render ulang karena isian lain tidak menimpa pilihan.
        await bulan(4).click();
        await page.locator('#tempat').fill('Aula Madrasah');
        await page.locator('#tempat').blur();
        await expect(pemilih).toContainText('Tercetak: Maret, Mei, Juni 2026');
        await expect(bulan(4)).toHaveAttribute('aria-pressed', 'false');
        await expect(bulan(5)).toHaveAttribute('aria-pressed', 'true');

        // Keyboard: panah memindah fokus, spasi memilih.
        await bulan(6).focus();
        await page.keyboard.press('ArrowRight');
        await expect(bulan(7)).toBeFocused();
        await page.keyboard.press('Space');
        await expect(bulan(7)).toHaveAttribute('aria-pressed', 'true');
        await expect(pemilih).toContainText('Tercetak: Maret, Mei, Juni, Juli 2026');

        // Bulan kegiatan berlangsung ditandai setelah tanggal diisi.
        await page.locator('#tanggal_mulai').fill('2026-09-29');
        await page.locator('#lama_hari').fill('3');
        await expect(bulan(10)).toHaveAttribute('data-berlangsung', '');
        await expect(page.getByTestId('keterangan-berlangsung')).toBeVisible();
        await pemilih.screenshot({ path: `storage/playwright/shots/pemilih-bulan-${test.info().project.name}.png` });

        await pemilih.getByRole('button', { name: 'Kosongkan' }).click();
        await expect(pemilih.locator('[data-bulan][aria-pressed="true"]')).toHaveCount(0);
        await expect(pemilih.getByRole('button', { name: 'Kosongkan' })).toHaveCount(0);
    });

    test('validasi tanggal: dokumen setelah mulai & bulan tidak mencakup kegiatan', async ({ page }) => {
        await page.locator('#pelaksanaan').getByRole('button', { name: 'Mei' }).click();
        await page.locator('#tanggal_mulai').fill('2026-07-14');
        await page.locator('#lama_hari').fill('2');
        await expect(page.getByText('Bulan pelaksanaan belum mencakup bulan kegiatan berlangsung: Juli.')).toBeVisible();

        await page.locator('#tanggal_dokumen').fill('2026-07-20');
        await expect(page.getByText('harus sebelum atau sama dengan tanggal mulai kegiatan (14 Juli 2026)')).toBeVisible();
        await expect(page.locator('#tanggal_dokumen')).toHaveAttribute('max', '2026-07-14');

        await page.locator('#tanggal_dokumen').fill('2026-07-01');
        await expect(page.getByText('harus sebelum atau sama dengan tanggal mulai kegiatan')).toHaveCount(0);
    });

    test('simpan draf kosong menampilkan error nama kegiatan', async ({ page }) => {
        await page.getByRole('button', { name: 'Simpan Draf' }).first().click();
        await expect(page.getByText('Kolom nama kegiatan wajib diisi.')).toBeVisible();
        await expect(page.getByText('Periksa kembali isian yang ditandai merah.')).toBeVisible();
    });
    test('pelaksanaan: tiga kelompok, pratinjau KAK ikut isian, penghitung uraian terisi saat dimuat', async ({ page }, testInfo) => {
        const bagian = page.locator('#pelaksanaan');
        await expect(bagian.locator('[data-kelompok]')).toHaveCount(3);
        const pratinjau = page.getByTestId('pratinjau-pelaksanaan');
        await expect(pratinjau.locator('[data-pratinjau="tempat"]')).toContainText('Lengkapi tempat pelaksanaan');

        await page.locator('#nama_kegiatan').fill('Workshop Kurikulum');
        await page.locator('#tanggal_mulai').fill('2026-07-14');
        await page.locator('#lama_hari').fill('2');
        await page.locator('#tempat').fill('Aula Madrasah');
        await page.locator('#tempat').blur();
        await expect(pratinjau.locator('[data-pratinjau="pelaksanaan"]')).toContainText('akan dilaksanakan selama 2 hari, 14 – 15 Juli 2026.');
        await expect(pratinjau.locator('[data-pratinjau="tempat"]')).toContainText('Kegiatan Workshop Kurikulum bertempat di Aula Madrasah.');
        await expect(bagian.locator('[data-kelompok="waktu"] [data-kelengkapan]')).toHaveAttribute('data-kelengkapan', 'lengkap');
        await bagian.screenshot({ path: `storage/playwright/shots/pelaksanaan-${testInfo.project.name}.png` });

        // Kegiatan tersimpan: penghitung karakter menunjukkan panjang uraian yang dimuat, bukan 0.
        await page.goto('/kegiatan/1/edit');
        const panjang = await page.locator('#uraian_pelaksanaan').evaluate((el) => el.value.length);
        expect(panjang).toBeGreaterThan(0);
        await expect(page.locator('#pelaksanaan').getByText(`${panjang} / 3.000`)).toBeVisible();
    });
    test('pelaksanaan: saran tempat & peserta mendahulukan isian kegiatan lain', async ({ page }) => {
        await expect(page.locator('#saran-tempat option').first()).toHaveAttribute('label', 'Pernah dipakai');
        await expect(page.locator('#saran-tempat option[value="Ruang Kelas dan Ruang Rapat Madrasah"]')).toHaveAttribute('label', 'Pernah dipakai');
        await expect(page.locator('#saran-tempat option[value="Laboratorium IPA"]')).not.toHaveAttribute('label');
        await expect(page.locator('#saran-peserta option[value="Siswa kelas IX"]')).toHaveCount(1);

        await page.locator('#tempat').fill('Ruang Kelas dan Ruang Rapat Madrasah');
        await page.locator('#tempat').blur();
        await expect(page.getByTestId('pratinjau-pelaksanaan').locator('[data-pratinjau="tempat"]')).toContainText('bertempat di Ruang Kelas dan Ruang Rapat Madrasah.');
    });
    test('konteks: sisipkan aspek, tanda aspek terisi, bahan AI dari isian lain, peringatan NIP', async ({ page }, testInfo) => {
        const bagian = page.getByTestId('bagian-konteks');
        await bagian.scrollIntoViewIfNeeded();
        await expect(page.getByTestId('bahan-ai')).toContainText('Tim Penjamin Mutu');

        await bagian.locator('[data-sisip="peserta"]').click();
        await expect(page.locator('#catatan_konteks')).toHaveValue('Peserta: ');
        await expect(page.locator('#catatan_konteks')).toBeFocused();
        await page.keyboard.type('54 guru');
        await bagian.locator('[data-sisip="narasumber"]').click();
        await expect(page.locator('#catatan_konteks')).toHaveValue('Peserta: 54 guru\nNarasumber: ');
        await page.keyboard.type('pengawas madrasah');
        await page.locator('#catatan_konteks').blur();
        await expect(bagian.locator('[data-aspek="peserta"]')).toHaveAttribute('data-terisi', 'ya');
        await expect(bagian.locator('[data-aspek="narasumber"]')).toHaveAttribute('data-terisi', 'ya');
        await expect(bagian.locator('[data-aspek="output"]')).toHaveAttribute('data-terisi', 'tidak');

        // Isian lain langsung tercermin di panel bahan AI.
        await page.locator('#tempat').fill('Aula Madrasah');
        await page.locator('#tempat').blur();
        await expect(page.getByTestId('bahan-ai')).toContainText('Aula Madrasah');

        await page.locator('#catatan_konteks').fill('Narasumber NIP 197210082007101002');
        await page.locator('#catatan_konteks').blur();
        await expect(bagian).toContainText('Catatan memuat NIP');
        await bagian.screenshot({ path: `storage/playwright/shots/konteks-${testInfo.project.name}.png` });
    });
    test('pelaksanaan: angka bulat saja (ketik & tempel), peringatan angka tak wajar', async ({ page, context }) => {
        const lama = page.locator('#lama_hari');
        await lama.click();
        await page.keyboard.type('1e-2.5');
        await expect(lama).toHaveValue('125');

        await context.grantPermissions(['clipboard-read', 'clipboard-write']);
        const peserta = page.locator('#peserta_jumlah');
        await page.evaluate(() => navigator.clipboard.writeText('2.500'));
        await peserta.click();
        await page.keyboard.press('Control+V');
        await expect(peserta).toHaveValue('2500');
        await peserta.blur();

        await expect(page.getByText('Lama kegiatan 125 hari (lebih dari sebulan) — pastikan bukan salah ketik.')).toBeVisible();
        await expect(page.getByText('Jumlah peserta 2.500 orang — pastikan bukan salah ketik.')).toBeVisible();
        await expect(page.locator('#lama_hari-error')).toHaveCount(0);
    });
    test('pratinjau KAK/TOR: panel dari isian saat ini, dokumen per sumber dana, tutup dengan Esc', async ({ page }, testInfo) => {
        await page.goto('/kegiatan/2/edit');
        await page.locator('#tempat').fill('Laboratorium IPA');
        await page.locator('#tempat').blur();
        await page.getByRole('button', { name: 'Pratinjau', exact: true }).click();

        const panel = page.getByTestId('panel-pratinjau');
        await expect(panel).toBeVisible();
        const kak = panel.getByTestId('pratinjau-kak');
        await expect(kak.locator('[data-bagian]')).toHaveCount(9);
        await expect(kak).toContainText('bertempat di Laboratorium IPA.');
        await expect(kak).toContainText('dibebankan pada dana DIPA');
        await page.screenshot({ path: `storage/playwright/shots/pratinjau-kak-${testInfo.project.name}.png` });

        await panel.getByRole('tab', { name: 'KAK Komite' }).click();
        await expect(kak).toContainText('dibebankan pada dana KOMITE');
        await expect(kak).toContainText('Ketua Komite MTsN 2 Kota Malang');

        await page.keyboard.press('Escape');
        await expect(panel).toHaveCount(0);
        await expect(page.locator('#tempat')).toHaveValue('Laboratorium IPA');
    });
});
