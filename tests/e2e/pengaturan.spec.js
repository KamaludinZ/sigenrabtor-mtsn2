import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';

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
        await expect(page.getByTestId('bagian-ai').getByRole('link', { name: 'Buka Pengaturan AI' })).toBeVisible();
        await expect(page.getByTestId('bagian-leading').getByRole('link', { name: 'Buka Leading & User' })).toBeVisible();
        await expect(page.getByTestId('bagian-madrasah').getByRole('link', { name: 'Buka Profil & Pejabat' })).toBeVisible();
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

        await page.screenshot({ path: `storage/playwright/shots/pengaturan-ikhtisar-${testInfo.project.name}.png`, fullPage: true });
        // Butir kesiapan menuju halaman pengaturannya.
        await kesiapan.getByRole('link', { name: 'Penyedia AI' }).click();
        await expect(page).toHaveURL(/\/pengaturan\/ai$/);
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

    test('editor cover: seret/isian posisi, ukuran huruf, sembunyikan kolom, simpan tata letak', async ({ page }, testInfo) => {
        const mobile = testInfo.project.name === 'mobile';
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/template');
        await page.getByTestId('input-gambar-cover').setInputFiles('tests/fixtures/cover-contoh.png');
        await expect(page.getByTestId('info-gambar')).toBeVisible({ timeout: 30_000 });
        await page.getByRole('button', { name: 'Simpan sebagai cover default' }).click();
        await expect(page.getByText('Cover tersimpan.')).toBeVisible({ timeout: 30_000 });

        try {
            await page.getByRole('button', { name: 'Atur kotak teks' }).click();
            const editor = page.getByTestId('editor-cover');
            await expect(editor).toBeVisible();
            const kanvas = editor.getByTestId('kanvas-cover');
            const judul = kanvas.locator('[data-field="judul"]');
            await expect(kanvas.locator('[data-field]')).toHaveCount(8);
            const atasAwal = await judul.evaluate((el) => parseFloat(el.style.top));
            expect(atasAwal).toBe(22);

            await editor.getByRole('button', { name: 'Judul dokumen' }).click();
            await expect(judul).toHaveAttribute('data-terpilih', 'ya');
            const atas = editor.getByTestId('properti-kotak').locator('[data-properti="y"]');
            if (!mobile) {
                // Seret kotak judul ke bawah ±10% tinggi halaman.
                await judul.evaluate((el) => el.scrollIntoView({ block: 'center', behavior: 'instant' }));
                const kotak = await judul.boundingBox();
                const halaman = await kanvas.boundingBox();
                await page.mouse.move(kotak.x + kotak.width / 2, kotak.y + kotak.height / 2);
                await page.mouse.down();
                await page.mouse.move(kotak.x + kotak.width / 2, kotak.y + kotak.height / 2 + halaman.height * 0.05, { steps: 4 });
                await page.mouse.move(kotak.x + kotak.width / 2, kotak.y + kotak.height / 2 + halaman.height * 0.1, { steps: 4 });
                await page.mouse.up();
                await expect.poll(() => judul.evaluate((el) => parseFloat(el.style.top))).toBeGreaterThan(30);
                await expect(atas).not.toHaveValue('22');
            }
            // Isian angka (juga cara di layar sentuh): posisi atas tepat 30%.
            await atas.fill('30');
            await atas.press('Tab');
            await expect.poll(() => judul.evaluate((el) => el.style.top)).toBe('30%');
            const ukuran = editor.getByTestId('properti-kotak').locator('[data-properti="font_size"]');
            await ukuran.fill('22');
            await ukuran.press('Tab');
            // Browser menormalkan calc(22 * 100cqw / 595) menjadi calc(3.69748cqw).
            await expect.poll(() => judul.evaluate((el) => el.style.fontSize)).toContain((22 * 100 / 595).toFixed(3));

            await editor.getByLabel('Tampilkan “Disusun oleh”').uncheck();
            await expect(kanvas.locator('[data-field]')).toHaveCount(7);
            await expect(editor.getByTestId('tata-letak-berubah')).toBeVisible();
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            expect(overflow).toBeLessThanOrEqual(0);
            await editor.screenshot({ path: `storage/playwright/shots/editor-cover-${testInfo.project.name}.png` });

            await editor.getByRole('button', { name: 'Simpan tata letak' }).click();
            await expect(page.getByText('Tata letak “Cover default 2026” tersimpan.')).toBeVisible({ timeout: 30_000 });
            const pratinjau = page.getByTestId('pratinjau-cover');
            await expect(pratinjau.locator('[data-field]')).toHaveCount(7);
            await expect.poll(() => pratinjau.locator('[data-field="judul"]').evaluate((el) => el.style.top)).toBe('30%');

            await page.reload();
            await expect.poll(() => page.getByTestId('pratinjau-cover').locator('[data-field="judul"]').evaluate((el) => el.style.top)).toBe('30%');
        } finally {
            await page.goto('/pengaturan/template');
            const hapus = page.getByTestId('cover-tersimpan').getByRole('button', { name: 'Hapus Cover default 2026' });
            if (await hapus.count()) {
                await hapus.click();
                await page.getByTestId('cover-tersimpan').getByRole('button', { name: 'Hapus', exact: true }).click();
                await expect(page.getByTestId('cover-kosong')).toBeVisible({ timeout: 30_000 });
            }
        }
    });

    test('master KAK/TOR: variabel salah tulis ditolak, master benar disimpan, diunduh, dihapus', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/template?tab=kak');
        await expect(page.getByTestId('master-kosong')).toBeVisible();

        await page.getByTestId('input-berkas-master').setInputFiles('tests/fixtures/master-kak-salah.docx');
        await expect(page.getByTestId('ringkasan-variabel')).toHaveText('3 variabel ditemukan · 1 terdaftar · 2 tidak terdaftar', { timeout: 30_000 });
        await expect(page.getByTestId('master-tidak-terdaftar')).toBeVisible();
        await expect(page.locator('[data-variabel="nama_kegiaatan"]')).toContainText('Mungkin maksud Anda ${nama_kegiatan}?');
        await expect(page.getByRole('button', { name: 'Simpan master' })).toBeDisabled();
        await page.getByTestId('unggah-master').screenshot({ path: `storage/playwright/shots/master-salah-${testInfo.project.name}.png` });

        // Unggah ulang berkas yang sudah diperbaiki.
        await page.getByTestId('unggah-master').locator('#ganti-berkas-master').setInputFiles('tests/fixtures/master-kak-contoh.docx');
        await expect(page.getByTestId('ringkasan-variabel')).toHaveText('14 variabel ditemukan · 14 terdaftar', { timeout: 30_000 });
        await expect(page.getByTestId('master-tidak-terdaftar')).toHaveCount(0);
        await expect(page.getByTestId('dianjurkan-belum-ada')).toContainText('${nama_kegiatan}');
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/master-kak-${testInfo.project.name}.png`, fullPage: true });

        // Pratinjau sebelum disimpan: penanda variabel, lalu uji isi dengan contoh kegiatan.
        await page.getByRole('button', { name: 'Pratinjau & uji isi' }).click();
        const panel = page.getByTestId('panel-pratinjau-master');
        await expect(panel).toBeVisible();
        const isi = panel.getByTestId('isi-master');
        await expect(isi).toHaveAttribute('data-mode', 'penanda');
        await expect(isi.locator('[data-penanda="terdaftar"]').first()).toBeVisible();
        await expect(isi.locator('[data-bagian-master="header"]')).toContainText('${kop}');
        await panel.getByRole('button', { name: 'Uji isi' }).click();
        await expect(isi).toHaveAttribute('data-mode', 'isi');
        await panel.getByLabel('Contoh kegiatan untuk uji isi').selectOption({ label: 'Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran' });
        await expect(isi.locator('[data-isi="total"]')).toHaveText('Rp10.000.000,-');
        await expect(isi.locator('[data-isi="nama_kegiatan_cover"]')).toContainText('WORKSHOP PENYUSUNAN KURIKULUM');
        await expect(panel.getByTestId('variabel-kosong')).toContainText('${ttd_kepala}');
        const lebarPanel = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(lebarPanel).toBeLessThanOrEqual(0);
        await panel.screenshot({ path: `storage/playwright/shots/master-uji-isi-${testInfo.project.name}.png` });
        await page.keyboard.press('Escape');
        await expect(panel).toHaveCount(0);

        await page.getByRole('button', { name: 'Simpan master' }).click();
        await expect(page.getByText('Master KAK/TOR tersimpan.')).toBeVisible({ timeout: 30_000 });
        const tersimpan = page.getByTestId('master-tersimpan');
        await expect(tersimpan.getByTestId('master-1')).toContainText('Master KAK/TOR 2026');
        await expect(tersimpan.getByTestId('master-1')).toContainText('14 variabel');
        await expect(page.getByText('Mengunggah…')).toBeHidden();

        const [unduhan] = await Promise.all([page.waitForEvent('download'), tersimpan.getByRole('link', { name: 'Unduh' }).click()]);
        expect(unduhan.suggestedFilename()).toBe('master-kak-contoh.docx');

        await tersimpan.getByRole('button', { name: 'Hapus Master KAK/TOR 2026' }).click();
        await tersimpan.getByRole('button', { name: 'Hapus', exact: true }).click();
        await expect(page.getByTestId('master-kosong')).toBeVisible({ timeout: 30_000 });
    });

    test('daftar isi: pengaturan bawaan & khusus per leading × sumber dana dengan pratinjau halaman', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/template?tab=daftar_isi');
        const pratinjau = page.getByTestId('pratinjau-daftar-isi');
        await expect(page.getByTestId('buku-pratinjau')).toContainText('Contoh buku Tim Penjamin Mutu · DIPA');
        await expect(page.getByTestId('di-judul-tercetak')).toHaveText('DAFTAR KEGIATAN');
        await expect(pratinjau.locator('tr[data-baris-daftar-isi]').first()).toContainText('Review RKJM dan Penyusunan RKTM');

        try {
            await page.locator('#di-judul').fill('DAFTAR ISI KEGIATAN');
            await expect(page.getByTestId('di-judul-tercetak')).toHaveText('DAFTAR ISI KEGIATAN');
            await page.locator('[data-kolom="bulan"]').check();
            await expect(pratinjau.locator('[data-kolom-tercetak="bulan"]')).toBeVisible();
            await expect(page.getByTestId('daftar-isi-berubah')).toBeVisible();
            await page.getByRole('button', { name: 'Simpan bawaan' }).click();
            await expect(page.getByText('Pengaturan bawaan daftar isi tersimpan.')).toBeVisible({ timeout: 30_000 });

            // Pengaturan khusus untuk buku TPM · Komite.
            await page.getByTestId('buku-1.6-komite').click();
            await expect(page.getByTestId('status-daftar-isi')).toContainText('Mengikuti pengaturan bawaan');
            await expect(page.getByTestId('di-judul-tercetak')).toHaveText('DAFTAR ISI KEGIATAN');
            await page.getByRole('radio', { name: 'Nama kegiatan' }).check();
            await page.locator('#di-catatan').fill('Diurutkan menurut nama kegiatan.');
            await expect(page.getByTestId('di-catatan-tercetak')).toHaveText('Diurutkan menurut nama kegiatan.');
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            expect(overflow).toBeLessThanOrEqual(0);
            await page.screenshot({ path: `storage/playwright/shots/daftar-isi-${testInfo.project.name}.png`, fullPage: true });
            await page.getByRole('button', { name: 'Simpan sebagai pengaturan khusus' }).click();
            await expect(page.getByTestId('buku-1.6-komite')).toHaveAttribute('data-khusus', 'ya', { timeout: 30_000 });
            await expect(page.getByTestId('status-daftar-isi')).toHaveText('Memakai pengaturan khusus.');

            await page.reload();
            await page.getByTestId('buku-1.6-komite').click();
            await expect(page.getByTestId('di-catatan-tercetak')).toHaveText('Diurutkan menurut nama kegiatan.');
        } finally {
            // Bersihkan: buku kembali ke bawaan, bawaan kembali ke susunan aplikasi.
            await page.goto('/pengaturan/template?tab=daftar_isi');
            if ((await page.getByTestId('buku-1.6-komite').getAttribute('data-khusus')) === 'ya') {
                await page.getByTestId('buku-1.6-komite').click();
                await page.getByRole('button', { name: 'Kembalikan ke pengaturan bawaan' }).click();
                await expect(page.getByTestId('buku-1.6-komite')).toHaveAttribute('data-khusus', 'tidak', { timeout: 30_000 });
            }
            await page.getByTestId('pilih-bawaan').click();
            await page.getByRole('button', { name: 'Pakai susunan aplikasi' }).click();
            await expect(page.getByTestId('di-judul-tercetak')).toHaveText('DAFTAR KEGIATAN', { timeout: 30_000 });
            const simpan = page.getByRole('button', { name: 'Simpan bawaan' });
            if (await page.getByTestId('daftar-isi-berubah').count()) {
                await simpan.click();
                // Tunggu simpan rampung (bukan hanya pratinjau) agar proyek berikutnya mulai dari bawaan aplikasi.
                await expect(page.getByRole('button', { name: 'Tersimpan' })).toBeDisabled({ timeout: 30_000 });
            }
        }
    });

    test('nomor halaman: format preset & sendiri, halaman tanpa nomor, simulasi buku', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/template?tab=nomor_halaman');
        const simulasi = page.getByTestId('simulasi-nomor');
        const daftarIsi = simulasi.locator('[data-jenis="daftar_isi"]');
        await expect(daftarIsi).toHaveAttribute('data-label', /^Halaman 2 dari \d+$/);
        await expect(simulasi.locator('[data-jenis="cover_buku"]')).toHaveAttribute('data-label', '');

        try {
            await page.getByRole('radio', { name: /- \{x\} -/ }).check();
            await expect(daftarIsi).toHaveAttribute('data-label', '- 2 -');
            await page.getByTestId('hitung-tanpa-nomor').uncheck();
            await expect(daftarIsi).toHaveAttribute('data-label', '- 1 -');
            await expect(page.getByTestId('halaman-daftar-isi').locator('[data-mulai="0"]')).toHaveText('2');

            await page.getByRole('radio', { name: 'Format lain…' }).check();
            await page.locator('#format-lain').fill('Halaman');
            await expect(page.getByText('Format harus memuat {x} (nomor halaman).')).toBeVisible();
            await page.locator('#format-lain').fill('Lembar {x}');
            await expect(daftarIsi).toHaveAttribute('data-label', 'Lembar 1');
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            expect(overflow).toBeLessThanOrEqual(0);
            await page.screenshot({ path: `storage/playwright/shots/nomor-halaman-${testInfo.project.name}.png`, fullPage: true });
            await page.getByRole('button', { name: 'Simpan aturan' }).click();
            await expect(page.getByText('Aturan nomor halaman tersimpan.')).toBeVisible({ timeout: 30_000 });

            await page.reload();
            await expect(page.locator('#format-lain')).toHaveValue('Lembar {x}');
            await expect(page.getByTestId('simulasi-nomor').locator('[data-jenis="daftar_isi"]')).toHaveAttribute('data-label', 'Lembar 1');
        } finally {
            await page.goto('/pengaturan/template?tab=nomor_halaman');
            await page.getByRole('button', { name: 'Pakai aturan aplikasi' }).click();
            await expect(page.getByTestId('simulasi-nomor').locator('[data-jenis="daftar_isi"]')).toHaveAttribute('data-label', /^Halaman 2 dari/, { timeout: 30_000 });
            if (await page.getByTestId('nomor-berubah').count()) {
                await page.getByRole('button', { name: 'Simpan aturan' }).click();
                await expect(page.getByRole('button', { name: 'Tersimpan' })).toBeDisabled({ timeout: 30_000 });
            }
        }
    });

    test('riwayat perubahan: perubahan tercatat, saringan bagian & cari, ringkasan di ikhtisar', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        // Satu perubahan baru agar riwayat pasti berisi aksi Template Dokumen.
        await page.goto('/pengaturan/template?tab=nomor_halaman');
        await page.getByRole('radio', { name: '{x} mis. 3', exact: true }).check();
        await page.getByRole('button', { name: 'Simpan aturan' }).click();
        await expect(page.getByText('Aturan nomor halaman tersimpan.')).toBeVisible({ timeout: 30_000 });
        try {
            await page.goto('/pengaturan/riwayat');
            const tabel = page.getByTestId('tabel-riwayat');
            const baris = testInfo.project.name === 'mobile' ? tabel.locator('ul li') : tabel.locator('tbody tr');
            await expect(baris.first()).toContainText('Format: Halaman {x} dari {y} → {x}');

            // Riwayat berhalaman 20 baris (putaran sebelumnya ikut mencatat); saringan bagian memuat entri contoh.
            await tabel.getByLabel('Bagian').selectOption({ label: 'Tahun Anggaran' });
            await expect(tabel).not.toContainText('Ubah nomor halaman');
            await expect(baris.first()).toContainText('Ganti tahun aktif');
            await expect(tabel).toContainText('Tahun Anggaran 2026 diaktifkan (sebelumnya 2025); 2025 dikunci.');
            await tabel.getByLabel('Bagian').selectOption('');
            await tabel.getByLabel('Cari').fill('nomor halaman');
            await expect(page.getByTestId('jumlah-riwayat')).toContainText('cocok');
            await expect(baris.first()).toContainText('Ubah nomor halaman');
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            expect(overflow).toBeLessThanOrEqual(0);
            await page.screenshot({ path: `storage/playwright/shots/riwayat-${testInfo.project.name}.png`, fullPage: true });

            await page.goto('/pengaturan');
            await expect(page.getByTestId('perubahan-terakhir')).toContainText('Ubah nomor halaman');
            await page.getByTestId('perubahan-terakhir').getByRole('link', { name: 'Lihat semua riwayat' }).click();
            await expect(page).toHaveURL(/\/pengaturan\/riwayat$/);
        } finally {
            await page.goto('/pengaturan/template?tab=nomor_halaman');
            await page.getByRole('button', { name: 'Pakai aturan aplikasi' }).click();
            await expect(page.getByTestId('simulasi-nomor').locator('[data-jenis="daftar_isi"]')).toHaveAttribute('data-label', /^Halaman 2 dari/, { timeout: 30_000 });
            if (await page.getByTestId('nomor-berubah').count()) {
                await page.getByRole('button', { name: 'Simpan aturan' }).click();
                await expect(page.getByRole('button', { name: 'Tersimpan' })).toBeDisabled({ timeout: 30_000 });
            }
        }
    });

    test('profil & pejabat: layout tab profil (kop) dan pejabat penanda tangan', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/madrasah');
        await expect(page.getByRole('heading', { name: 'Profil & Pejabat', level: 1 })).toBeVisible();
        await expect(page.getByTestId('profil-identitas').locator('[data-isian]')).toHaveCount(6);
        await expect(page.getByTestId('profil-identitas').locator('[data-isian="alamat"]')).toContainText('Belum diisi');
        await expect(page.getByTestId('pratinjau-kop')).toContainText('Madrasah Tsanawiyah Negeri 2 Kota Malang');
        await expect(page.getByTestId('kesiapan-madrasah').locator('li[data-kesiapan]')).toHaveCount(3);
        let overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/pengaturan-profil-${testInfo.project.name}.png`, fullPage: true });

        await page.getByRole('button', { name: /Pejabat penanda tangan/ }).click();
        await expect(page).toHaveURL(/tab=pejabat/);
        await expect(page.getByTestId('pejabat-pimpinan').locator('[data-pejabat]')).toHaveCount(2);
        const lebar = testInfo.project.name === 'desktop';
        await expect(page.getByTestId('pejabat-leading').locator(lebar ? 'tr[data-pejabat-leading]' : 'li[data-pejabat-leading-kartu]')).toHaveCount(8);
        await expect(page.getByTestId('pratinjau-ttd')).toContainText('Mengetahui,');
        overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/pengaturan-pejabat-${testInfo.project.name}.png`, fullPage: true });

        await page.reload();
        await expect(page.getByTestId('pejabat-pimpinan')).toBeVisible();
    });

    test('profil madrasah: formulir dengan pratinjau kop langsung, simpan, unggah & hapus logo', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/madrasah');
        const identitas = page.getByTestId('profil-identitas');
        const kop = page.getByTestId('pratinjau-kop');

        await identitas.getByRole('button', { name: 'Ubah' }).click();
        const formulir = page.getByTestId('formulir-profil');
        await expect(formulir.getByLabel('Nama madrasah')).toHaveValue('Madrasah Tsanawiyah Negeri 2 Kota Malang');
        await formulir.getByLabel('Alamat').fill('Jl. Uji E2E No. 1, Kota Malang');
        await formulir.getByLabel('Telepon').fill('(0341) 000 111');
        // Pratinjau kop mengikuti ketikan sebelum disimpan.
        await expect(kop.locator('[data-kop="alamat"]')).toHaveText('Jl. Uji E2E No. 1, Kota Malang');
        await expect(kop.locator('[data-kop="telepon"]')).toHaveText('Telepon (0341) 000 111');
        await expect(kop.locator('[data-kop="alamat"]')).not.toHaveClass(/italic/);
        let overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/pengaturan-profil-formulir-${testInfo.project.name}.png`, fullPage: true });

        await formulir.getByRole('button', { name: 'Simpan profil' }).click();
        await expect(identitas.locator('[data-isian="alamat"]')).toHaveAttribute('data-terisi', 'ya');
        await expect(identitas.locator('[data-isian="alamat"]')).toContainText('Jl. Uji E2E No. 1, Kota Malang');
        await expect(page.getByTestId('kesiapan-madrasah').locator('[data-kesiapan="profil"]')).toContainText('Logo kop belum diisi.');

        const logo = page.getByTestId('profil-logo');
        await logo.getByTestId('input-logo').setInputFiles('tests/fixtures/cover-contoh.png');
        await expect(logo).toContainText('Logo baru — belum disimpan.');
        await logo.getByRole('button', { name: 'Simpan logo' }).click();
        await expect(logo).toContainText('Logo tersimpan');
        await expect(kop.getByTestId('kop-logo')).toBeVisible();
        expect((await page.request.get('/madrasah/logo')).status()).toBe(200);

        // Pulihkan data (proyek mobile memakai data E2E yang sama).
        await logo.getByRole('button', { name: 'Hapus' }).click();
        await logo.getByRole('group', { name: 'Konfirmasi hapus logo' }).getByRole('button', { name: 'Hapus' }).click();
        await expect(logo).toContainText('Belum diunggah');
        await identitas.getByRole('button', { name: 'Ubah' }).click();
        await formulir.getByLabel('Alamat').fill('');
        await formulir.getByLabel('Telepon').fill('');
        await formulir.getByRole('button', { name: 'Simpan profil' }).click();
        await expect(identitas.locator('[data-isian="alamat"]')).toHaveAttribute('data-terisi', 'tidak');
    });

    test('pejabat penanda tangan: komponen input dengan pratinjau NIP, validasi, simpan pejabat leading', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/madrasah?tab=pejabat');
        const pimpinan = page.getByTestId('pejabat-pimpinan');

        // Kepala Madrasah: NIP wajib & diperiksa langsung (tidak disimpan — tes lain memerlukan pejabat kosong).
        await pimpinan.getByRole('button', { name: 'Ubah Kepala Madrasah' }).click();
        const formulir = pimpinan.getByTestId('formulir-pejabat');
        await expect(formulir.getByLabel('Jabatan tercetak')).toHaveValue('Kepala MTsN 2 Kota Malang');
        await formulir.getByLabel('Nama lengkap & gelar').fill('Dr. Uji Kepala, M.Pd.');
        await formulir.getByLabel('NIP').fill('1970010119950310');
        await expect(formulir.getByTestId('pejabat-kepala-nip-pratinjau')).toHaveText('NIP harus 18 angka (sekarang 16).');
        await formulir.getByLabel('NIP').fill('197001011995031001');
        await expect(formulir.getByTestId('pejabat-kepala-nip-pratinjau')).toHaveText('Tercetak: NIP. 19700101 199503 1 001');
        await expect(formulir.getByTestId('pejabat-kepala-nip-pratinjau')).not.toHaveClass(/text-gold-700/);
        await formulir.getByLabel('NIP').fill('');
        await formulir.getByRole('button', { name: 'Simpan' }).click();
        await expect(formulir).toContainText('NIP Kepala Madrasah wajib diisi');
        await page.screenshot({ path: `storage/playwright/shots/pengaturan-pejabat-formulir-${testInfo.project.name}.png`, fullPage: true });
        await formulir.getByRole('button', { name: 'Batal' }).click();
        await expect(pimpinan.locator('[data-pejabat="kepala"]')).toHaveAttribute('data-lengkap', 'tidak');

        // Pejabat leading 1.8 (tidak dipakai contoh kegiatan): simpan nama & NIP.
        const leading = page.getByTestId('pejabat-leading');
        await leading.getByRole('button', { name: 'Ubah pejabat 1.8 PMM' }).locator('visible=true').click();
        const formulirLeading = leading.getByTestId('formulir-pejabat').locator('visible=true');
        await formulirLeading.getByLabel('Nama lengkap & gelar').fill('Uji Koordinator PMM, S.Pd.');
        await formulirLeading.getByLabel('NIP (opsional)').fill('19800101 200501 1 002');
        await formulirLeading.getByRole('button', { name: 'Simpan' }).click();
        const baris = leading.locator(testInfo.project.name === 'desktop' ? 'tr[data-pejabat-leading="1.8"]' : 'li[data-pejabat-leading-kartu="1.8"]');
        await expect(baris).toContainText('Uji Koordinator PMM, S.Pd.');
        await expect(baris).toContainText('19800101 200501 1 002');
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
    });

    test('pejabat per tahun: pemilih tahun, tahun terkunci hanya dilihat, perbandingan antar-tahun', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/madrasah?tab=pejabat');
        const pemilih = page.getByTestId('pemilih-tahun-pejabat');
        await expect(pemilih.getByRole('button', { name: /TA 2026/ })).toHaveAttribute('aria-pressed', 'true');
        // Saran "Salin dari TA 2025" hanya muncul selama TA 2026 belum punya pejabat (tes lain di berkas ini mengisi 1.8) — diuji di PHPUnit.

        await pemilih.getByRole('button', { name: /TA 2025/ }).click();
        await expect(page).toHaveURL(/ta=2025/);
        await expect(page.getByTestId('pejabat-terkunci')).toBeVisible();
        await expect(page.getByTestId('pejabat-pimpinan')).toContainText('Drs. H. Mahmud Arifin, M.Pd.I.');
        await expect(page.getByTestId('pejabat-pimpinan').getByRole('button', { name: /^Ubah/ })).toHaveCount(0);
        await expect(page.getByTestId('pratinjau-ttd')).toContainText('TA 2025');

        const perbandingan = page.getByTestId('pejabat-per-tahun');
        await expect(perbandingan.locator('tr[data-perbandingan]')).toHaveCount(10);
        await expect(perbandingan.locator('tr[data-perbandingan="kepala"] td[data-tahun="2025"]')).toContainText('19680512 199403 1 002');
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/pengaturan-pejabat-tahun-${testInfo.project.name}.png`, fullPage: true });

        await page.reload();
        await expect(pemilih.getByRole('button', { name: /TA 2025/ })).toHaveAttribute('aria-pressed', 'true');
        await pemilih.getByRole('button', { name: /TA 2026/ }).click();
        await expect(page).not.toHaveURL(/ta=/);
    });

    test('blok Mengetahui: atur per sumber dana dengan pratinjau dokumen DIPA/Komite', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/madrasah?tab=pejabat');
        const ttd = page.getByTestId('pratinjau-ttd');
        const dipa = ttd.getByTestId('aturan-mengetahui').getByLabel('DIPA');
        await expect(dipa).toBeChecked();
        await expect(ttd.locator('[data-ttd="komite"]')).toBeVisible();

        await dipa.uncheck();
        await expect(page.getByText('Blok "Mengetahui" tampil di dokumen Komite.')).toBeVisible();
        await expect(ttd.locator('[data-ttd="komite"]')).toBeHidden();
        await ttd.getByRole('button', { name: 'Dokumen Komite' }).click();
        await expect(ttd.locator('[data-ttd="komite"]')).toBeVisible();
        await ttd.screenshot({ path: `storage/playwright/shots/pengaturan-mengetahui-${testInfo.project.name}.png` });
        await page.reload();
        await expect(ttd.getByTestId('aturan-mengetahui').getByLabel('DIPA')).not.toBeChecked();

        // Pulihkan bawaan (proyek mobile & tes dokumen memakai data yang sama).
        await ttd.getByTestId('aturan-mengetahui').getByLabel('DIPA').check();
        await expect(page.getByText('Blok "Mengetahui" tampil di dokumen DIPA & Komite.')).toBeVisible();
        await expect(ttd.locator('[data-ttd="komite"]')).toBeVisible();
    });

    test('dasar hukum & latar belakang: layout per tahun, kalimat sisip nama kegiatan, pratinjau KAK', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/madrasah');
        await page.getByRole('link', { name: 'Dasar hukum & latar belakang' }).click();
        await expect(page).toHaveURL(/\/pengaturan\/madrasah\/dasar-hukum$/);
        await expect(page.getByTestId('daftar-dasar-hukum').locator('li[data-butir]')).toHaveCount(4);
        await expect(page.getByTestId('kalimat-sisip').locator('mark')).toHaveText('nama kegiatan');
        await expect(page.getByTestId('pratinjau-dasar-hukum')).toContainText('2. Dasar Hukum');
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/pengaturan-dasar-hukum-${testInfo.project.name}.png`, fullPage: true });

        await page.getByTestId('pemilih-tahun-dasar-hukum').getByRole('button', { name: /TA 2025/ }).click();
        await expect(page).toHaveURL(/ta=2025/);
        await expect(page.getByTestId('dasar-hukum-terkunci')).toBeVisible();
        await expect(page.getByTestId('daftar-dasar-hukum')).toContainText('Tahun 2025.');
    });

    test('dasar hukum: tambah, ubah (Enter/Esc), dan hapus butir dengan tanda baca otomatis', async ({ page }, testInfo) => {
        const nama = `Peraturan Uji E2E ${testInfo.project.name} Tahun {tahun}`;
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/madrasah/dasar-hukum');
        const daftar = page.getByTestId('daftar-dasar-hukum');
        const pratinjau = page.getByTestId('pratinjau-dasar-hukum');
        await expect(daftar.locator('li[data-butir]')).toHaveCount(4);

        await daftar.getByRole('button', { name: 'Tambah butir' }).click();
        const isian = daftar.getByRole('textbox', { name: 'Butir baru' });
        await expect(isian).toBeFocused();
        await isian.press('Enter');
        await expect(daftar.getByRole('alert')).toHaveText('Butir dasar hukum wajib diisi.');
        await isian.fill(`${nama};`);
        await isian.press('Enter');
        await expect(page.getByText('Butir dasar hukum ditambahkan untuk TA 2026.')).toBeVisible();
        await expect(daftar.locator('li[data-butir]')).toHaveCount(5);
        await expect(daftar.locator('li[data-butir="5"]')).toContainText(`Peraturan Uji E2E ${testInfo.project.name} Tahun 2026.`);
        await expect(daftar.locator('li[data-butir="4"]')).toContainText('Tahun 2026;');
        await expect(pratinjau.locator('ol li').last()).toHaveText(`Peraturan Uji E2E ${testInfo.project.name} Tahun 2026.`);

        await daftar.getByRole('button', { name: 'Ubah butir 5' }).click();
        const ubah = daftar.getByRole('textbox', { name: 'Ubah butir 5' });
        await expect(ubah).toHaveValue(nama);
        await ubah.fill('Teks yang dibatalkan');
        await ubah.press('Escape');
        await expect(ubah).toBeHidden();
        await expect(daftar.locator('li[data-butir="5"]')).toContainText(`Peraturan Uji E2E ${testInfo.project.name} Tahun 2026.`);

        await daftar.getByRole('button', { name: 'Ubah butir 5' }).click();
        await daftar.getByRole('textbox', { name: 'Ubah butir 5' }).fill(`${nama} (diubah)`);
        await daftar.getByRole('button', { name: 'Simpan' }).click();
        await expect(page.getByText('Butir dasar hukum diperbarui untuk TA 2026.')).toBeVisible();
        await expect(daftar.locator('li[data-butir="5"]')).toContainText('Tahun 2026 (diubah).');
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await daftar.screenshot({ path: `storage/playwright/shots/dasar-hukum-ubah-${testInfo.project.name}.png` });

        // Hapus langsung lalu urungkan; hapus lagi untuk memulihkan (proyek mobile & tes dokumen memakai data yang sama).
        await daftar.getByRole('button', { name: 'Hapus butir 5' }).click();
        await expect(daftar.getByTestId('butir-terhapus')).toContainText('Butir 5 dihapus');
        await expect(daftar.locator('li[data-butir]')).toHaveCount(4);
        await daftar.getByTestId('butir-terhapus').getByRole('button', { name: 'Urungkan' }).click();
        await expect(page.getByText('Butir 5 dikembalikan.')).toBeVisible();
        await expect(daftar.locator('li[data-butir="5"]')).toContainText('(diubah).');
        await daftar.getByRole('button', { name: 'Hapus butir 5' }).click();
        await expect(daftar.locator('li[data-butir]')).toHaveCount(4);
        await expect(daftar.locator('li[data-butir="4"]')).toContainText('Tahun 2026.');
    });

    test('dasar hukum: urutkan butir dengan seret (desktop) atau naik/turun, dokumen mengikuti', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/madrasah/dasar-hukum');
        const daftar = page.getByTestId('daftar-dasar-hukum');
        const pratinjau = page.getByTestId('pratinjau-dasar-hukum').locator('ol li');
        const butir = (n) => daftar.locator(`li[data-butir="${n}"]`);
        await expect(butir(4)).toContainText('Rencana Kerja Tahunan Madrasah');

        if (testInfo.project.name === 'desktop') {
            await butir(4).locator('[wire\\:sort\\:handle]').dragTo(butir(1).locator('[wire\\:sort\\:handle]'));
        } else {
            for (const n of [4, 3, 2]) {
                await daftar.getByRole('button', { name: `Naikkan butir ${n}` }).click();
                await expect(daftar.getByRole('button', { name: n === 2 ? 'Turunkan butir 1' : `Naikkan butir ${n - 1}` })).toBeFocused();
            }
        }
        await expect(butir(1)).toContainText('Rencana Kerja Tahunan Madrasah (RKTM) MTsN 2 Kota Malang Tahun 2026;');
        await expect(butir(4)).toContainText('Penyelenggaraan Pendidikan Madrasah.');
        await expect(pratinjau.first()).toContainText('Rencana Kerja Tahunan Madrasah');
        await expect(daftar.getByRole('button', { name: 'Naikkan butir 1' })).toBeDisabled();
        await expect(page.getByRole('status').filter({ hasText: 'dipindah ke nomor 1.' })).toBeAttached();
        await daftar.screenshot({ path: `storage/playwright/shots/dasar-hukum-urut-${testInfo.project.name}.png` });

        // Pulihkan urutan semula dengan tombol turun (fokus tetap di tombol yang sama).
        for (const n of [1, 2, 3]) {
            await daftar.getByRole('button', { name: `Turunkan butir ${n}` }).click();
            // Di posisi terakhir tombol turun nonaktif: fokus pindah ke tombol naik butir yang sama.
            await expect(daftar.getByRole('button', { name: n === 3 ? 'Naikkan butir 4' : `Turunkan butir ${n + 1}` })).toBeFocused();
        }
        await expect(butir(4)).toContainText('Tahun 2026.');
        await expect(butir(1)).toContainText('Undang-Undang Republik Indonesia Nomor 20 Tahun 2003');
        await expect(daftar.getByRole('button', { name: 'Turunkan butir 4' })).toBeDisabled();
    });

    test('latar belakang baku: editor dengan sisip penanda, pratinjau saat mengetik, simpan, dan teks bawaan', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/madrasah/dasar-hukum');
        const bagian = page.getByTestId('latar-belakang-baku');
        await bagian.getByRole('button', { name: 'Ubah' }).click();
        const editor = bagian.getByTestId('editor-latar');
        const paragraf = editor.getByLabel('Paragraf baku');
        const penutup = editor.getByLabel('Kalimat penutup tiap kegiatan');
        const pratinjau = editor.getByTestId('pratinjau-latar');
        await expect(paragraf).toBeFocused();
        await expect(paragraf).toHaveValue(/Tahun Anggaran \{tahun\} memerlukan/);

        await paragraf.fill('Visi madrasah untuk Tahun Anggaran ');
        await editor.getByRole('button', { name: 'Sisipkan {tahun}' }).first().click();
        await paragraf.press('End');
        await paragraf.pressSequentially('. Salah {thn}');
        await expect(paragraf).toHaveValue('Visi madrasah untuk Tahun Anggaran {tahun}. Salah {thn}');
        await expect(pratinjau.locator('[data-pratinjau="paragraf"]')).toHaveText('Visi madrasah untuk Tahun Anggaran 2026. Salah {thn}');
        await expect(pratinjau.locator('[data-pratinjau="paragraf"] span.bg-brand-100')).toHaveText('2026');
        await expect(editor.getByText('Penanda tidak dikenal: {thn}')).toBeVisible();

        // Tempat sisip variabel kegiatan: kursor di antara kata, lalu pilih variabelnya.
        const variabel = editor.getByTestId('pilih-variabel-kegiatan');
        await penutup.fill('Kegiatan  oleh  mendukung Perkin.');
        await penutup.evaluate((el) => el.setSelectionRange(9, 9));
        await variabel.getByRole('button', { name: 'Nama kegiatan' }).click();
        await penutup.evaluate((el) => el.setSelectionRange(30, 30));
        await variabel.getByRole('button', { name: 'Unit leading' }).click();
        await expect(penutup).toHaveValue('Kegiatan {nama_kegiatan} oleh {leading} mendukung Perkin.');
        await expect(variabel.getByRole('button', { name: /Unit leading.*sudah dipakai/ })).toBeVisible();
        await expect(pratinjau.locator('[data-pratinjau="penutup"]')).toContainText(/^Kegiatan \S.+ oleh Tim Penjamin Mutu mendukung Perkin\.$/);
        await expect(pratinjau.locator('[data-pratinjau="penutup"] span.bg-brand-100').nth(1)).toHaveText('Tim Penjamin Mutu');

        await editor.getByRole('button', { name: 'Simpan' }).click();
        await expect(editor.getByRole('alert')).toContainText('Penanda {thn} tidak dikenal');
        await paragraf.fill('Visi madrasah untuk Tahun Anggaran {tahun}.');
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await bagian.screenshot({ path: `storage/playwright/shots/latar-belakang-editor-${testInfo.project.name}.png` });
        await editor.getByRole('button', { name: 'Simpan' }).click();
        await expect(page.getByText('Latar belakang baku TA 2026 tersimpan.')).toBeVisible();
        await expect(bagian.getByTestId('paragraf-baku')).toHaveText('Visi madrasah untuk Tahun Anggaran 2026.');
        await expect(bagian.getByTestId('kalimat-sisip')).toHaveText(/^\s*Kegiatan nama kegiatan oleh unit leading mendukung Perkin\.\s*$/);
        await expect(bagian.getByTestId('kalimat-sisip').locator('mark[data-variabel="{leading}"]')).toHaveText('unit leading');
        await expect(page.getByTestId('pratinjau-dasar-hukum')).toContainText('oleh Tim Penjamin Mutu mendukung Perkin.');
        await expect(page.getByTestId('pratinjau-dasar-hukum')).toContainText('Visi madrasah untuk Tahun Anggaran 2026.');

        // Pulihkan teks bawaan (proyek mobile & tes dokumen memakai data yang sama).
        await bagian.getByRole('button', { name: 'Ubah' }).click();
        await editor.getByRole('button', { name: 'Pakai teks bawaan' }).click();
        await expect(paragraf).toHaveValue(/^Madrasah Tsanawiyah Negeri 2 Kota Malang/);
        await editor.getByRole('button', { name: 'Simpan' }).click();
        await expect(page.getByText('Latar belakang baku TA 2026 tersimpan.').last()).toBeVisible();
        await expect(bagian.getByTestId('paragraf-baku')).toContainText('Madrasah Tsanawiyah Negeri 2 Kota Malang');
        await expect(bagian.getByTestId('kalimat-sisip')).toContainText('Perjanjian Kinerja (Perkin) Kepala Madrasah adalah kegiatan');
    });

    test('SK RKTM/RKAM: isi nomor & tanggal, tampil di Tahun Anggaran, penanda di butir, lalu dikosongkan', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/madrasah/dasar-hukum');
        const sk = page.getByTestId('sk-rktm');
        await expect(sk.getByTestId('sk-kosong')).toBeVisible();
        await sk.getByRole('button', { name: 'Isi SK' }).click();
        await expect(sk.getByLabel('Nomor SK')).toBeFocused();
        await sk.getByLabel('Nomor SK').fill(`12/SK/RKTM/${testInfo.project.name}`);
        await sk.getByRole('button', { name: 'Simpan' }).click();
        await expect(sk.getByRole('alert')).toHaveText('Tanggal SK wajib diisi bersama nomornya.');
        await sk.getByLabel('Tanggal SK').fill('2025-12-30');
        await sk.getByRole('button', { name: 'Simpan' }).click();
        await expect(page.getByText('SK RKTM/RKAM TA 2026 tersimpan.')).toBeVisible();
        await expect(sk.locator('[data-sk="nomor"]')).toHaveText(`12/SK/RKTM/${testInfo.project.name}`);
        await expect(sk.locator('[data-sk="tanggal"]')).toHaveText('30 Desember 2025');
        await expect(sk.getByTestId('sk-dipakai')).toContainText('Belum dipakai di dasar hukum');
        await sk.screenshot({ path: `storage/playwright/shots/sk-rktm-${testInfo.project.name}.png` });

        // Penanda SK lewat tombol sisip di isian butir (dibatalkan, tidak disimpan).
        const daftar = page.getByTestId('daftar-dasar-hukum');
        await daftar.getByRole('button', { name: 'Tambah butir' }).click();
        const isian = daftar.getByRole('textbox', { name: 'Butir baru' });
        await isian.fill('SK Nomor ');
        await daftar.getByRole('button', { name: 'Nomor SK' }).click();
        await expect(isian).toHaveValue('SK Nomor {no_sk_rktm}');
        await isian.press('Escape');
        await expect(daftar.locator('li[data-butir]')).toHaveCount(4);

        await page.goto('/pengaturan/tahun');
        await expect(page.getByText(`12/SK/RKTM/${testInfo.project.name}`).first()).toBeVisible();

        // Pulihkan: kosongkan SK TA 2026 (tes lain mengharapkan SK belum diisi).
        await page.goto('/pengaturan/madrasah/dasar-hukum');
        await sk.getByRole('button', { name: 'Ubah' }).click();
        await sk.getByLabel('Nomor SK').fill('');
        await sk.getByLabel('Tanggal SK').fill('');
        await sk.getByRole('button', { name: 'Simpan' }).click();
        await expect(page.getByText('SK RKTM/RKAM TA 2026 dikosongkan.')).toBeVisible();
        await expect(sk.getByTestId('sk-kosong')).toBeVisible();
    });

    test('dasar hukum: perubahan dari tab lain tidak tertimpa — tampilan dimuat ulang lebih dulu', async ({ page, context }, testInfo) => {
        const butirB = `Butir dari tab B ${testInfo.project.name}`;
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/madrasah/dasar-hukum');
        const tabB = await context.newPage();
        await tabB.goto('/pengaturan/madrasah/dasar-hukum');
        const daftarA = page.getByTestId('daftar-dasar-hukum');
        const daftarB = tabB.getByTestId('daftar-dasar-hukum');
        await expect(daftarA.locator('li[data-butir]')).toHaveCount(4);

        await daftarB.getByRole('button', { name: 'Tambah butir' }).click();
        await daftarB.getByRole('textbox', { name: 'Butir baru' }).fill(butirB);
        await daftarB.getByRole('textbox', { name: 'Butir baru' }).press('Enter');
        await expect(daftarB.locator('li[data-butir]')).toHaveCount(5);
        await tabB.close();

        // Tab A masih menampilkan 4 butir; aksinya ditolak dan tampilan dimuat ulang.
        await daftarA.getByRole('button', { name: 'Hapus butir 4' }).click();
        await expect(page.getByText('baru saja diubah di tempat lain')).toBeVisible();
        await expect(daftarA.locator('li[data-butir]')).toHaveCount(5);
        await expect(daftarA.locator('li[data-butir="5"]')).toContainText(`${butirB}.`);
        await expect(daftarA.getByTestId('butir-terhapus')).toHaveCount(0);

        // Pulihkan: hapus butir tab B.
        await daftarA.getByRole('button', { name: 'Hapus butir 5' }).click();
        await expect(daftarA.locator('li[data-butir]')).toHaveCount(4);
        await expect(daftarA.locator('li[data-butir="4"]')).toContainText('Tahun 2026.');
    });

    test('pratinjau dokumen: bagian 1–2 hasil otomatis per contoh kegiatan, susunan latar di Data Kegiatan', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/madrasah/dasar-hukum');
        const pratinjau = page.getByTestId('pratinjau-dasar-hukum');
        const dokumen = pratinjau.getByTestId('pratinjau-kak');
        await expect(dokumen.locator('[data-bagian]')).toHaveCount(2);
        await expect(dokumen.locator('[data-bagian="2"] ol li')).toHaveCount(4);

        const pilih = pratinjau.getByTestId('pilih-contoh');
        const opsi = pilih.locator('option').nth(1);
        const namaOpsi = (await opsi.textContent()).trim();
        await pilih.selectOption({ index: 1 });
        await expect(page).toHaveURL(/contoh=\d+/);
        await expect(pratinjau).toContainText(`dihasilkan otomatis untuk ${namaOpsi}`);
        await expect(dokumen.locator('[data-bagian="1"]')).toContainText(`adalah kegiatan ${namaOpsi}.`);
        await pratinjau.scrollIntoViewIfNeeded();
        await pratinjau.screenshot({ path: `storage/playwright/shots/dasar-hukum-pratinjau-${testInfo.project.name}.png` });

        // Data Kegiatan: susunan latar belakang memakai paragraf baku & kalimat penutup dari pengaturan.
        await page.goto('/kegiatan/1/edit');
        const susunan = page.getByTestId('susunan-latar');
        await susunan.locator('summary').click();
        await expect(susunan.locator('[data-latar="baku"]')).toContainText('Madrasah Tsanawiyah Negeri 2 Kota Malang');
        await expect(susunan.locator('[data-latar="penutup"]')).toContainText('Perjanjian Kinerja (Perkin) Kepala Madrasah adalah kegiatan');
    });

    test('master kode: menu aktif, tab per jenis dengan jumlah & pemakaian, tahun lain terkunci', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan');
        await page.getByTestId('kesiapan-pengaturan').getByRole('link', { name: 'Master kode 2026' }).click();
        await expect(page).toHaveURL(/\/master$/);
        const master = page.getByTestId('master-kode');
        await expect(master.getByRole('tab', { name: /^SK/ })).toHaveAttribute('aria-selected', 'true');
        await expect(master.locator('[data-kode="SK.10"]').filter({ visible: true })).toContainText(/\d+ kegiatan/);

        await master.getByRole('tab', { name: /RKTM/ }).click();
        await expect(page).toHaveURL(/jenis=rktm/);
        await expect(master.getByRole('heading', { name: 'Rencana Kerja Tahunan Madrasah (RKTM)' })).toBeVisible();
        await expect(master.locator('[data-kode="6.17.12"]').filter({ visible: true })).toContainText(/\d+ kegiatan/);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/master-kode-${testInfo.project.name}.png`, fullPage: true });

        await page.getByTestId('pemilih-tahun-master').getByRole('button', { name: /TA 2025/ }).click();
        await expect(page).toHaveURL(/ta=2025/);
        await expect(page.getByTestId('master-terkunci')).toBeVisible();
        await expect(master.getByTestId('ringkasan-jenis')).toContainText('0 dipakai kegiatan TA 2025');
    });

    test('master kode: formulir unggah — pilih/seret berkas, sheet dikenali per jenis, galat berkas asing', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/master');
        await page.getByRole('button', { name: 'Unggah Excel' }).click();
        const panel = page.getByTestId('unggah-master');
        await expect(panel).toBeVisible();
        await expect(panel.getByTestId('akan-diganti')).toContainText('Master TA 2026 yang ada akan diganti');

        await panel.getByTestId('input-berkas-master').setInputFiles('tests/fixtures/master-kode-asing.xlsx');
        await expect(panel.getByTestId('galat-unggah-master')).toContainText('Tidak ada sheet yang dikenali');
        await expect(panel.getByRole('button', { name: /Lanjut ke pratinjau/ })).toBeDisabled();
        await panel.getByRole('button', { name: 'Batal pilih' }).click();

        // Seret-lepas berkas ke zona unggah.
        const isi = [...readFileSync('tests/fixtures/master-kode-contoh.xlsx')];
        const transfer = await page.evaluateHandle((bita) => {
            const dt = new DataTransfer();
            dt.items.add(new File([new Uint8Array(bita)], 'master-2026.xlsx', { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }));
            return dt;
        }, isi);
        await panel.getByTestId('zona-berkas-master').dispatchEvent('drop', { dataTransfer: transfer });
        await expect(panel.getByTestId('nama-berkas-master')).toHaveText('master-2026.xlsx');
        const sheet = panel.getByTestId('sheet-dikenali');
        await expect(sheet.locator('[data-dikenali="ya"]')).toHaveCount(5);
        await expect(sheet.locator('[data-jenis="rktm"]')).toContainText('sheet SNP');
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await panel.screenshot({ path: `storage/playwright/shots/master-unggah-${testInfo.project.name}.png` });

        // Pratinjau SK: dibandingkan dengan master TA 2026 sebelum disimpan.
        await panel.getByRole('button', { name: 'Lanjut ke pratinjau' }).click();
        const pratinjau = panel.getByTestId('pratinjau-master');
        await expect(pratinjau.getByRole('tab', { name: /^SK/ })).toHaveAttribute('aria-selected', 'true');
        const ringkasan = pratinjau.getByTestId('ringkasan-pratinjau');
        await expect(ringkasan.locator('[data-status="tetap"]')).toContainText('Tetap 3');
        await expect(pratinjau.locator('li[data-kode="SK.11"]')).toContainText(/padahal dipakai \d+ kegiatan TA 2026/);
        await ringkasan.locator('[data-status="hilang"]').click();
        await expect(pratinjau.locator('li[data-status="tetap"]')).toHaveCount(0);
        await expect(pratinjau.locator('li[data-status="hilang"]').first()).toBeVisible();
        await pratinjau.screenshot({ path: `storage/playwright/shots/master-pratinjau-sk-${testInfo.project.name}.png` });
        await pratinjau.getByRole('tab', { name: /IKSK/ }).click();
        await expect(pratinjau.locator('li[data-kode="IKSK 10.2"]')).toContainText('SK induk: SK.10');
        await expect(pratinjau.locator('li[data-kode="IKSK 10.2"]')).toContainText(/dipakai \d+ kegiatan/);
        await pratinjau.getByRole('tab', { name: /RKTM/ }).click();
        await expect(pratinjau.locator('li[data-kode="1.1.2"]')).toContainText('Standar (SNP): PENGEMBANGAN STANDAR ISI');
        await expect(pratinjau.locator('li[data-kode="6.17.13"]')).toContainText(/padahal dipakai \d+ kegiatan TA 2026/);
        await pratinjau.getByRole('tab', { name: /EDM/ }).click();
        await expect(pratinjau.locator('li[data-kode="A1"]')).toContainText('Komponen: Kedisiplinan');
        await expect(pratinjau.locator('li[data-kode="A7"]')).toContainText(/padahal dipakai \d+ kegiatan TA 2026/);
        await pratinjau.getByRole('tab', { name: /Budaya/ }).click();
        await expect(pratinjau.locator('li[data-status="tetap"]')).toHaveCount(5);
        await expect(pratinjau.locator('li[data-kode="2"]')).toContainText(/dipakai \d+ kegiatan/);
        await panel.getByRole('button', { name: 'Kembali' }).click();
        await expect(panel.getByTestId('nama-berkas-master')).toHaveText('master-2026.xlsx');

        // Satu jenis: hanya jenis itu yang diperiksa.
        await panel.getByTestId('cakupan-unggah').getByText('EDM', { exact: true }).click();
        await expect(sheet.locator('[data-jenis]')).toHaveCount(1);
        await expect(sheet.locator('[data-jenis="edm"]')).toContainText('sheet EDM');

        // Pindah halaman selama ada unggahan meminta konfirmasi; ditolak = tetap di sini.
        let pesanDialog = '';
        page.once('dialog', (dialog) => {
            pesanDialog = dialog.message();
            dialog.dismiss();
        });
        await page.getByTestId('kepala-pengaturan').getByRole('link', { name: 'Pengaturan' }).click();
        await expect.poll(() => pesanDialog).toContain('Unggahan master kode belum disimpan');
        await expect(page).toHaveURL(/\/master/);

        // Batalkan dari pratinjau: konfirmasi, lalu master tidak berubah.
        await panel.getByRole('button', { name: 'Lanjut ke pratinjau' }).click();
        await panel.getByRole('button', { name: 'Batalkan', exact: true }).click();
        const konfirmasi = panel.getByTestId('konfirmasi-batal-unggah');
        await expect(konfirmasi).toContainText('kode hilang) tidak disimpan; master TA 2026 tetap seperti sekarang.');
        await panel.screenshot({ path: `storage/playwright/shots/master-batal-${testInfo.project.name}.png` });
        await konfirmasi.getByRole('button', { name: 'Lanjutkan pratinjau' }).click();
        await expect(konfirmasi).toHaveCount(0);
        await panel.getByRole('button', { name: 'Tutup unggah master' }).click();
        await panel.getByTestId('konfirmasi-batal-unggah').getByRole('button', { name: 'Ya, batalkan' }).click();
        await expect(page.getByText('Unggahan dibatalkan — master kode TA 2026 tidak berubah.')).toBeVisible();
        await expect(panel).toHaveCount(0);
        await expect(page.getByTestId('master-kode').getByRole('tab', { name: /^SK/ })).toContainText('11');
    });

    test('leading & user: tab leading & akun, akun tertaut, status, tanpa gulir mendatar', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan');
        await page.getByTestId('bagian-leading').getByRole('link', { name: 'Buka Leading & User' }).click();
        await expect(page).toHaveURL(/\/pengaturan\/leading$/);
        const leading = page.getByTestId('daftar-leading');
        await expect(leading.locator('[data-leading]').filter({ visible: true })).toHaveCount(9);
        await expect(leading.locator('[data-leading="1.1"]').filter({ visible: true })).toContainText('Siti Rohmah, M.Pd.');
        await expect(leading.locator('[data-leading="1.9"]').filter({ visible: true })).toContainText('Nonaktif');
        await expect(page.getByTestId('leading-tanpa-akun')).toContainText('1.3 Waka Sarpras');

        // Cari, saring status, dan urutkan (tersimpan di URL).
        await leading.getByPlaceholder('Cari kode, unit, atau jabatan…').fill('mahad');
        await expect(leading.locator('[data-leading]').filter({ visible: true })).toHaveCount(1);
        await expect(page).toHaveURL(/cari_leading=mahad/);
        await leading.getByPlaceholder('Cari kode, unit, atau jabatan…').fill('');
        await leading.getByLabel('Status leading').selectOption('nonaktif');
        await expect(leading.locator('[data-leading]').filter({ visible: true })).toHaveCount(1);
        await expect(leading.locator('[data-leading="1.9"]').filter({ visible: true })).toBeVisible();
        await leading.getByLabel('Status leading').selectOption('');
        if (testInfo.project.name === 'desktop') {
            await leading.getByRole('button', { name: 'Kegiatan' }).click();
            await expect(leading.locator('tbody tr').first()).toHaveAttribute('data-leading', '1.6');
            await expect(page).toHaveURL(/urut_leading=kegiatan/);
        }
        let overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/leading-user-${testInfo.project.name}.png`, fullPage: true });

        await page.getByRole('button', { name: /^Akun/ }).click();
        await expect(page).toHaveURL(/tab=akun/);
        const akun = page.getByTestId('daftar-akun');
        const barisAkun = (id) => akun.locator(`[data-akun="${id}"]`).filter({ visible: true });
        await expect(akun.locator('[data-akun]').filter({ visible: true })).toHaveCount(6);
        await expect(barisAkun(2)).toContainText('Anda');
        await expect(barisAkun(4)).toContainText('1.4 Waka Humas');
        await expect(barisAkun(6)).toContainText('Nonaktif');
        await akun.getByLabel('Peran akun').selectOption('admin');
        await expect(akun.locator('[data-akun]').filter({ visible: true })).toHaveCount(2);
        await expect(page).toHaveURL(/peran_akun=admin/);
        await akun.getByLabel('Peran akun').selectOption('');
        await akun.getByPlaceholder('Cari nama, surel, atau jabatan…').fill('kurikulum');
        await expect(akun.locator('[data-akun]').filter({ visible: true })).toHaveCount(1);
        await expect(barisAkun(3)).toContainText('Siti Rohmah, M.Pd.');
        await akun.getByPlaceholder('Cari nama, surel, atau jabatan…').fill('');
        await expect(akun.locator('[data-akun]').filter({ visible: true })).toHaveCount(6);
        overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/akun-user-${testInfo.project.name}.png`, fullPage: true });
    });

    test('leading: formulir tambah (galat kode ganda), ubah jabatan lalu pulihkan, kode terpakai terkunci', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/leading');
        await page.getByRole('button', { name: 'Tambah leading' }).click();
        const form = page.getByTestId('formulir-leading');
        await expect(form.getByLabel('Kode')).toBeFocused();
        await form.getByLabel('Kode').fill('1.6');
        await form.getByRole('button', { name: 'Simpan' }).click();
        await expect(form).toContainText('Kode 1.6 sudah dipakai leading lain.');
        await expect(form).toContainText('Nama unit wajib diisi.');
        await form.getByRole('button', { name: 'Batal' }).click();
        await expect(form).toHaveCount(0);

        // Kode yang dipakai kegiatan/akun terkunci.
        await page.getByRole('button', { name: 'Ubah leading 1.6' }).filter({ visible: true }).click();
        await expect(form.getByLabel('Kode')).toHaveAttribute('readonly', '');
        await expect(form).toContainText('Terkunci: dipakai');
        await form.getByRole('button', { name: 'Batal' }).click();

        // Ubah jabatan Mahad, lalu pulihkan (proyek mobile memakai data yang sama).
        const jabatan = `Pengasuh Mahad Madrasah ${testInfo.project.name}`;
        await page.getByRole('button', { name: 'Ubah leading 1.7' }).filter({ visible: true }).click();
        await form.getByLabel('Jabatan penanda tangan').fill(jabatan);
        await form.screenshot({ path: `storage/playwright/shots/leading-form-${testInfo.project.name}.png` });
        await form.getByRole('button', { name: 'Simpan' }).click();
        await expect(page.getByText('Leading 1.7 Mahad diperbarui.')).toBeVisible();
        await expect(page.getByTestId('daftar-leading').locator('[data-leading="1.7"]').filter({ visible: true })).toContainText(jabatan);
        await page.getByRole('button', { name: 'Ubah leading 1.7' }).filter({ visible: true }).click();
        await form.getByLabel('Jabatan penanda tangan').fill('Pengasuh Mahad');
        await form.getByRole('button', { name: 'Simpan' }).click();
        await expect(page.getByTestId('daftar-leading').locator('[data-leading="1.7"]').filter({ visible: true })).not.toContainText(jabatan);
        await expect(form).toHaveCount(0);
    });

    test('leading: nonaktifkan dengan konfirmasi dampak, penanda di saringan lain, lalu aktifkan kembali', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/leading');
        const daftar = page.getByTestId('daftar-leading');
        const baris = daftar.locator('[data-leading="1.7"]').filter({ visible: true });
        await page.getByRole('button', { name: 'Nonaktifkan leading 1.7', exact: true }).filter({ visible: true }).click();
        const konfirmasi = daftar.getByTestId('konfirmasi-nonaktif-leading').filter({ visible: true });
        await expect(konfirmasi).toContainText('Nonaktifkan 1.7 Mahad?');
        await konfirmasi.screenshot({ path: `storage/playwright/shots/leading-nonaktif-${testInfo.project.name}.png` });
        await konfirmasi.getByRole('button', { name: 'Nonaktifkan' }).click();
        await expect(page.getByText('Leading 1.7 Mahad dinonaktifkan')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Aktifkan leading 1.7', exact: true }).filter({ visible: true })).toBeVisible();

        // Saringan lain menandai leading nonaktif.
        await page.goto('/monitoring');
        await expect(page.locator('select[wire\\:model\\.live="leading"] option[value="1.7"]')).toHaveText('1.7 · Mahad (nonaktif)');

        // Pulihkan (proyek mobile memakai data yang sama).
        await page.goto('/pengaturan/leading');
        await page.getByRole('button', { name: 'Aktifkan leading 1.7', exact: true }).filter({ visible: true }).click();
        await expect(page.getByText('Leading 1.7 Mahad aktif kembali')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Nonaktifkan leading 1.7', exact: true }).filter({ visible: true })).toBeVisible();
        await expect(baris).toBeVisible();
    });

    test('akun: formulir tambah (galat), pratinjau NIP & tautan leading, ubah lalu pulihkan', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/leading?tab=akun');
        await page.getByRole('button', { name: 'Tambah akun' }).click();
        const form = page.getByTestId('formulir-akun');
        await expect(form.getByLabel('Nama & gelar')).toBeFocused();
        await form.getByRole('button', { name: 'Simpan' }).click();
        await expect(form).toContainText('Nama wajib diisi.');
        await expect(form).toContainText('Akun leading wajib ditautkan ke minimal satu leading.');
        await form.getByLabel('Surel').fill('muslimin@example.com');
        await form.getByLabel('NIP').fill('197001011995031001');
        await expect(form.locator('#akun-nip-pratinjau')).toHaveText('Tercetak: 19700101 199503 1 001');
        await form.getByLabel('Admin').check();
        await expect(form.getByTestId('tautan-leading')).toHaveCount(0);
        await form.getByRole('button', { name: 'Simpan' }).click();
        await expect(form).toContainText('Surel sudah dipakai akun Muslimin, M.Pd.');
        await form.screenshot({ path: `storage/playwright/shots/akun-form-${testInfo.project.name}.png` });
        await form.getByRole('button', { name: 'Batal' }).click();
        await expect(form).toHaveCount(0);

        // Ubah jabatan Siti Rohmah lalu pulihkan (proyek mobile memakai data yang sama).
        const jabatan = `Waka Kurikulum ${testInfo.project.name}`;
        await page.getByRole('button', { name: 'Ubah akun Siti Rohmah, M.Pd.' }).filter({ visible: true }).click();
        await expect(form.getByTestId('tautan-leading').getByLabel(/1\.1/)).toBeChecked();
        await form.getByLabel('Jabatan').fill(jabatan);
        await form.getByRole('button', { name: 'Simpan' }).click();
        await expect(page.getByText('Akun Siti Rohmah, M.Pd. diperbarui.')).toBeVisible();
        await expect(page.getByTestId('daftar-akun').locator('[data-akun="3"]').filter({ visible: true })).toContainText(jabatan);
        await page.getByRole('button', { name: 'Ubah akun Siti Rohmah, M.Pd.' }).filter({ visible: true }).click();
        await form.getByLabel('Jabatan').fill('Wakil Kepala Bidang Kurikulum');
        await form.getByRole('button', { name: 'Simpan' }).click();
        await expect(form).toHaveCount(0);
        await expect(page.getByTestId('daftar-akun').locator('[data-akun="3"]').filter({ visible: true })).not.toContainText(jabatan);
    });

    test('leading: panel tautan akun — tautkan akun leading lalu lepas lagi', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/leading');
        await page.getByRole('button', { name: 'Tautkan akun ke leading 1.3' }).filter({ visible: true }).click();
        const panel = page.getByTestId('panel-tautan-leading').filter({ visible: true });
        await expect(panel).toContainText('Belum ada akun');
        await panel.getByLabel('Pilih akun untuk 1.3').selectOption({ label: 'Ahmad Fauzan, S.Pd. — 1.2, 1.4' });
        await panel.getByRole('button', { name: 'Tautkan' }).click();
        await expect(page.getByText('Akun Ahmad Fauzan, S.Pd. ditautkan ke 1.3 Waka Sarpras.')).toBeVisible();
        await expect(panel.locator('[data-tautan-akun="4"]')).toContainText('juga 1.2, 1.4');
        await expect(page.getByTestId('leading-tanpa-akun')).not.toContainText('1.3 Waka Sarpras');
        await panel.screenshot({ path: `storage/playwright/shots/leading-tautan-${testInfo.project.name}.png` });

        // Pulihkan (proyek mobile memakai data yang sama).
        await panel.getByRole('button', { name: 'Lepas Ahmad Fauzan, S.Pd. dari 1.3' }).click();
        await expect(page.getByText('Akun Ahmad Fauzan, S.Pd. dilepas dari 1.3 Waka Sarpras.')).toBeVisible();
        await expect(panel).toContainText('Belum ada akun');
        await panel.getByRole('button', { name: 'Tutup panel tautan 1.3' }).click();
        await expect(page.getByTestId('panel-tautan-leading').filter({ visible: true })).toHaveCount(0);
    });

    test('leading & user: perubahan dari tab lain tidak tertimpa — aksi ditolak dan tampilan dimuat ulang', async ({ page, context }, testInfo) => {
        const jabatan = `Pengasuh Mahad tab B ${testInfo.project.name}`;
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/leading');
        // Tab A menyiapkan tautan (memilih akun belum mengirim request).
        await page.getByRole('button', { name: 'Tautkan akun ke leading 1.3' }).filter({ visible: true }).click();
        const panel = page.getByTestId('panel-tautan-leading').filter({ visible: true });
        await panel.getByLabel('Pilih akun untuk 1.3').selectOption({ label: 'Ahmad Fauzan, S.Pd. — 1.2, 1.4' });

        const tabB = await context.newPage();
        await tabB.goto('/pengaturan/leading');
        await tabB.getByRole('button', { name: 'Ubah leading 1.7' }).filter({ visible: true }).click();
        await tabB.getByTestId('formulir-leading').getByLabel('Jabatan penanda tangan').fill(jabatan);
        await tabB.getByTestId('formulir-leading').getByRole('button', { name: 'Simpan' }).click();
        await expect(tabB.getByText('Leading 1.7 Mahad diperbarui.')).toBeVisible();
        await tabB.close();

        // Tab A belum memuat ulang: menautkan ditolak, tampilan memuat data terbaru.
        await panel.getByRole('button', { name: 'Tautkan' }).click();
        await expect(page.getByText('baru saja diubah di tempat lain')).toBeVisible();
        await expect(page.getByTestId('daftar-leading').locator('[data-leading="1.7"]').filter({ visible: true })).toContainText(jabatan);
        await expect(panel).toContainText('Belum ada akun');

        // Pulihkan jabatan 1.7 (proyek mobile memakai data yang sama).
        await page.getByRole('button', { name: 'Ubah leading 1.7' }).filter({ visible: true }).click();
        await page.getByTestId('formulir-leading').getByLabel('Jabatan penanda tangan').fill('Pengasuh Mahad');
        await page.getByTestId('formulir-leading').getByRole('button', { name: 'Simpan' }).click();
        await expect(page.getByText('Leading 1.7 Mahad diperbarui.')).toBeVisible();
    });

    test('akun: nonaktifkan dengan konfirmasi, akun sendiri terlindungi, lalu aktifkan kembali', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/leading?tab=akun');
        const daftar = page.getByTestId('daftar-akun');
        await expect(daftar.getByRole('button', { name: 'Nonaktifkan akun Koordinator PUSKOM', exact: true })).toHaveCount(0);

        await page.getByRole('button', { name: 'Nonaktifkan akun Siti Rohmah, M.Pd.', exact: true }).filter({ visible: true }).click();
        const konfirmasi = daftar.getByTestId('konfirmasi-nonaktif-akun').filter({ visible: true });
        await expect(konfirmasi).toContainText('Akun tidak bisa masuk lagi.');
        await konfirmasi.screenshot({ path: `storage/playwright/shots/akun-nonaktif-${testInfo.project.name}.png` });
        await konfirmasi.getByRole('button', { name: 'Nonaktifkan' }).click();
        await expect(page.getByText('Akun Siti Rohmah, M.Pd. dinonaktifkan — tidak bisa masuk lagi.')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Aktifkan akun Siti Rohmah, M.Pd.', exact: true }).filter({ visible: true })).toBeVisible();

        // Pulihkan (proyek mobile memakai data yang sama).
        await page.getByRole('button', { name: 'Aktifkan akun Siti Rohmah, M.Pd.', exact: true }).filter({ visible: true }).click();
        await expect(page.getByText('Akun Siti Rohmah, M.Pd. aktif kembali — bisa masuk lagi.')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Nonaktifkan akun Siti Rohmah, M.Pd.', exact: true }).filter({ visible: true })).toBeVisible();
    });
});
