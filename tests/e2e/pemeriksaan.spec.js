import { expect, test } from '@playwright/test';

// Panel status Data Kegiatan muncul dua kali (mobile di kolom utama, desktop di samping); ambil yang terlihat.
const panelStatus = (page) => page.getByTestId('panel-status').locator('visible=true');

async function ajukan(page, label = 'Ajukan pemeriksaan') {
    await panelStatus(page).getByRole('button', { name: label, exact: true }).click();
    await panelStatus(page).getByRole('button', { name: `Ya, ${label.toLowerCase()}` }).click();
    await expect(page.getByText('Status kegiatan: Diperiksa.')).toBeVisible();
}

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

    test('keputusan & perbaikan: kembalikan wajib catatan, leading memperbaiki data lalu ajukan ulang, admin menyetujui, leading mengunggah arsip final lalu menandai final', async ({ page }, testInfo) => {
        // Kegiatan baru per proyek agar tidak mengubah contoh yang dipakai tes lain.
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/buat');
        await page.locator('#nama_kegiatan').fill(`Kegiatan Pemeriksaan ${testInfo.project.name}`);
        await page.getByTestId('sumber-dipa').getByRole('checkbox').check();
        await expect(page.locator('#pagu_dipa')).toBeEnabled();
        await page.locator('#pagu_dipa').pressSequentially('1000000');
        await page.getByRole('button', { name: 'Simpan Draf' }).first().click();
        await expect(page).toHaveURL(/\/kegiatan\/\d+\/edit$/);
        const urlKegiatan = page.url();
        const id = urlKegiatan.match(/kegiatan\/(\d+)/)[1];

        await page.getByRole('link', { name: 'Kode & Klasifikasi', exact: true }).click();
        await page.locator('#kode_sk').click();
        await page.locator('#kode_sk-cari').fill('SK.12');
        await page.locator('#kode_sk-cari').press('Enter');
        await expect(page.getByTestId('uraian-sk')).toContainText('perencanaan dan anggaran');
        await page.getByRole('button', { name: 'Simpan', exact: true }).click();
        await expect(page.getByText(/Kode & klasifikasi tersimpan\./)).toBeVisible();
        await page.goto(urlKegiatan);
        await page.locator('#tanggal_mulai').fill('2026-07-14');
        await page.locator('#lama_hari').fill('2');
        await page.locator('#tempat').fill('Aula Madrasah');
        await page.locator('#peserta_jumlah').fill('40');
        await page.locator('#peserta_jenis').fill('Guru');
        await page.locator('#peserta_jenis').blur();
        await ajukan(page);

        // Admin mendapat notifikasi pengajuan dan membukanya dari lonceng.
        await page.goto('/demo/peran/admin');
        await page.goto('/pemeriksaan');
        await expect(page.getByTestId('daftar-pemeriksaan').locator(`li[data-kegiatan="${id}"]`).getByRole('link', { name: 'Periksa' })).toBeVisible();
        const lonceng = page.getByTestId('notifikasi');
        await expect(lonceng.getByTestId('jumlah-notifikasi')).toBeVisible();
        await lonceng.getByRole('button', { name: /^Notifikasi/ }).click();
        const itemAjukan = page.getByTestId('panel-notifikasi').locator(`li[data-kegiatan="${id}"][data-aksi="ajukan"]`);
        await expect(itemAjukan).toContainText('Pengajuan baru untuk diperiksa');
        await expect(lonceng.getByTestId('jumlah-notifikasi')).toHaveCount(0);
        const kotak = await page.getByTestId('panel-notifikasi').boundingBox();
        expect(kotak.x).toBeGreaterThanOrEqual(0);
        expect(kotak.x + kotak.width).toBeLessThanOrEqual(page.viewportSize().width);
        await page.screenshot({ path: `storage/playwright/shots/notifikasi-${testInfo.project.name}.png` });
        await itemAjukan.getByRole('link').click();
        await expect(page).toHaveURL(new RegExp(`/pemeriksaan/${id}$`));
        const panel = page.getByTestId('panel-keputusan');
        await expect(panel).toHaveAttribute('data-status', 'diperiksa');
        await expect(page.getByTestId('peringatan-keputusan')).toContainText('dokumen belum lengkap');

        await panel.getByRole('button', { name: 'Kembalikan', exact: true }).click();
        await expect(panel).toContainText('Tulis catatan perbaikan agar leading tahu apa yang harus diubah.');
        await expect(page.getByTestId('konfirmasi-keputusan')).toHaveCount(0);
        await panel.getByLabel(/Catatan untuk leading/).fill('Sasaran belum terukur.\nSesuaikan honor narasumber dengan SBM.');
        await panel.getByRole('button', { name: 'Kembalikan', exact: true }).click();
        await expect(page.getByTestId('konfirmasi-keputusan')).toContainText('Kembalikan ke leading?');
        await page.screenshot({ path: `storage/playwright/shots/pemeriksaan-keputusan-${testInfo.project.name}.png`, fullPage: true });
        await panel.getByRole('button', { name: 'Ya, kembalikan' }).click();
        await expect(panel).toHaveAttribute('data-status', 'dikembalikan');
        await expect(page.getByTestId('riwayat-pemeriksaan')).toContainText('Sesuaikan honor narasumber dengan SBM.');

        // Leading memperbaiki data: catatan & bagian yang sudah diubah tampil di Data Kegiatan, lalu ajukan ulang.
        await page.goto('/demo/peran/leading');
        await page.goto(urlKegiatan);
        await page.getByTestId('notifikasi').getByRole('button', { name: /^Notifikasi/ }).click();
        await expect(page.getByTestId('panel-notifikasi').locator(`li[data-kegiatan="${id}"][data-aksi="kembalikan"]`)).toContainText('Sasaran belum terukur.');
        await page.keyboard.press('Escape');
        await expect(page.getByTestId('panel-notifikasi')).toBeHidden();
        await expect(panelStatus(page).locator('[data-catatan-pemeriksa]').first()).toContainText('Sasaran belum terukur.');
        const banner = page.getByTestId('banner-perbaikan');
        await expect(banner.getByTestId('catatan-perbaikan')).toContainText('Sesuaikan honor narasumber dengan SBM.');
        await expect(banner).toContainText('Belum ada perubahan tersimpan sejak dikembalikan.');
        await page.locator('#tempat').fill('Laboratorium Komputer');
        await page.getByRole('button', { name: 'Simpan Draf' }).first().click();
        await expect(banner.getByTestId('perubahan-perbaikan').locator('[data-bagian-diubah]')).toHaveText(['Pelaksanaan']);
        await page.screenshot({ path: `storage/playwright/shots/perbaikan-data-${testInfo.project.name}.png` });
        await ajukan(page, 'Ajukan ulang');
        await expect(banner).toHaveCount(0);

        await page.goto('/demo/peran/admin');
        await page.goto(`/pemeriksaan/${id}`);
        await expect(page.getByTestId('pengajuan-ulang').locator('[data-bagian-diubah]')).toHaveText(['Pelaksanaan']);
        await panel.getByRole('button', { name: 'Setujui', exact: true }).click();
        await panel.getByRole('button', { name: 'Ya, setujui' }).click();
        await expect(panel).toHaveAttribute('data-status', 'disetujui');
        await expect(panel).toContainText('Perlu perbaikan lagi?');
        await expect(page.getByTestId('arsip-pemeriksaan').locator('[data-arsip="DIPA"]')).toContainText('Belum diunggah');

        // Leading mengunggah arsip final PDF per sumber dana, lalu menandai final (data terkunci).
        await page.goto('/demo/peran/leading');
        await page.goto(urlKegiatan);
        await expect(panelStatus(page).getByTestId('final-terhalang')).toContainText('Unggah arsip final bertanda tangan untuk RAB DIPA sebelum menandai final.');
        const arsip = page.getByTestId('panel-arsip-final');
        await expect(arsip.getByTestId('jumlah-arsip')).toHaveText('0 dari 1 diunggah');
        // Berkas yang salah ditolak di peramban sebelum diunggah (tanpa request unggah).
        const unggahan = [];
        page.on('request', (r) => { if (r.url().includes('/upload-file')) unggahan.push(r.url()); });
        const inputDipa = arsip.getByTestId('input-arsip-dipa');
        await inputDipa.setInputFiles({ name: 'foto-ttd.jpg', mimeType: 'image/jpeg', buffer: Buffer.from([0xff, 0xd8, 0xff, 0xe0, 0x00]) });
        await expect(arsip.getByTestId('galat-arsip-klien-dipa')).toHaveText('Arsip final harus berupa berkas PDF.');
        await inputDipa.setInputFiles({ name: 'foto-diganti-nama.pdf', mimeType: 'application/pdf', buffer: Buffer.from('bukan pdf') });
        await expect(arsip.getByTestId('galat-arsip-klien-dipa')).toHaveText('Berkas ini bukan PDF yang sah — simpan ulang hasil pindaian sebagai PDF.');
        expect(unggahan).toHaveLength(0);
        await expect(arsip.getByTestId('jumlah-arsip')).toHaveText('0 dari 1 diunggah');

        await inputDipa.setInputFiles('tests/fixtures/arsip-contoh.pdf');
        await expect(arsip.getByTestId('galat-arsip-klien-dipa')).toBeHidden();
        await expect.poll(() => unggahan.length).toBe(1);
        await expect(arsip.locator('[data-arsip="DIPA"] [data-nama-arsip]')).toHaveText('arsip-contoh.pdf');
        await expect(arsip.getByTestId('arsip-lengkap')).toBeVisible();
        await expect(page.getByTestId('belum-disimpan')).toHaveCount(0);
        await expect(panelStatus(page).getByTestId('final-terhalang')).toHaveCount(0);
        const tautanPdf = arsip.getByRole('link', { name: 'Lihat' });
        const pdf = await page.request.get(await tautanPdf.getAttribute('href'));
        expect(pdf.headers()['content-type']).toBe('application/pdf');
        await page.screenshot({ path: `storage/playwright/shots/arsip-final-${testInfo.project.name}.png` });

        await panelStatus(page).getByRole('button', { name: 'Tandai final', exact: true }).click();
        await panelStatus(page).getByRole('button', { name: 'Ya, tandai final' }).click();
        const bannerKunci = page.getByTestId('banner-terkunci');
        await expect(bannerKunci).toContainText('Isian terkunci — status Final');
        await expect(bannerKunci.getByTestId('jejak-kunci')).toContainText(/^Dikunci \d{1,2} \S+ 2026, \d{2}\.\d{2} oleh Muslimin, M\.Pd\./);
        await expect(bannerKunci.getByRole('link', { name: 'RAB DIPA' })).toBeVisible();
        await page.screenshot({ path: `storage/playwright/shots/tanda-kunci-${testInfo.project.name}.png` });
        await expect(arsip).toContainText('Data terkunci; hanya admin yang dapat membuka kunci.');
        await expect(arsip.getByTestId('input-arsip-dipa')).toHaveCount(0);

        await page.goto('/demo/peran/admin');
        await page.goto(`/pemeriksaan/${id}`);
        await expect(panel).toHaveAttribute('data-status', 'final');
        await expect(panel.getByTestId('jejak-kunci')).toContainText('oleh Muslimin, M.Pd.');
        await expect(page.getByTestId('pratinjau-kak').getByTestId('cap-final')).toBeVisible();
        await expect(page.getByTestId('arsip-pemeriksaan').locator('[data-arsip="DIPA"]')).toContainText('Lihat PDF');

        // Admin membuka kunci dari halaman Pemeriksaan (alasan wajib) — sekaligus memulihkan data E2E bersama agar tidak
        // menyisakan kegiatan Final (tes Monitoring menyaring status final).
        await expect(panel.getByTestId('buka-kunci')).toBeVisible();
        await panel.getByRole('button', { name: 'Buka kunci', exact: true }).click();
        await expect(panel).toContainText('Tulis alasan membuka kunci — tercatat di riwayat dan dibaca leading.');
        await panel.getByLabel(/Alasan membuka kunci/).fill('Nama kegiatan di cover salah ketik.');
        await panel.getByRole('button', { name: 'Buka kunci', exact: true }).click();
        await expect(page.getByTestId('konfirmasi-keputusan')).toContainText('Buka kunci kegiatan ini?');
        await panel.getByRole('button', { name: 'Ya, buka kunci' }).click();
        await expect(panel).toHaveAttribute('data-status', 'dikembalikan');
        await expect(page.getByTestId('riwayat-pemeriksaan')).toContainText('Nama kegiatan di cover salah ketik.');

        await page.goto('/demo/peran/leading');
        await page.goto(urlKegiatan);
        await expect(page.getByTestId('banner-perbaikan')).toContainText('Kunci dibuka untuk perbaikan');
        await expect(page.getByTestId('banner-perbaikan').getByTestId('catatan-perbaikan')).toContainText('Nama kegiatan di cover salah ketik.');
        await expect(page.getByTestId('panel-arsip-final')).toHaveCount(0);

        // Riwayat pemeriksaan: per putaran di detail, lalu log semua kegiatan di halaman Pemeriksaan.
        await page.goto('/demo/peran/admin');
        await page.goto(`/pemeriksaan/${id}`);
        const riwayat = page.getByTestId('riwayat-pemeriksaan');
        await expect(riwayat.getByTestId('jumlah-putaran')).toHaveText('2× diajukan');
        await expect(riwayat.locator('[data-putaran="2"]')).toHaveAttribute('data-hasil', 'dibuka');
        await expect(riwayat.locator('[data-putaran="1"]')).toHaveAttribute('data-hasil', 'dikembalikan');
        await expect(riwayat.locator('[data-putaran="2"] [data-aksi="unggah_arsip"]')).toContainText('Arsip final RAB DIPA diunggah');

        await page.goto('/pemeriksaan');
        await page.getByTestId('tampilan-pemeriksaan').getByRole('button', { name: 'Riwayat' }).click();
        await expect(page).toHaveURL(/tampilan=riwayat/);
        const log = page.getByTestId('log-riwayat');
        await log.getByLabel('Aksi').selectOption('buka_kunci');
        await log.getByRole('searchbox', { name: 'Cari riwayat' }).fill(`Kegiatan Pemeriksaan ${testInfo.project.name}`);
        await expect(page).toHaveURL(/aksi=buka_kunci/);
        await expect(log.locator(`li[data-kegiatan="${id}"][data-aksi="buka_kunci"]`)).toContainText('Nama kegiatan di cover salah ketik.');
        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(lebar).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/riwayat-pemeriksaan-${testInfo.project.name}.png`, fullPage: true });

        await page.goto('/demo/peran/leading');
    });
});
