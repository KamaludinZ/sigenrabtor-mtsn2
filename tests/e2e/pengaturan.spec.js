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
        await expect(page.getByTestId('bagian-leading')).toContainText('Segera disiapkan');
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
});
