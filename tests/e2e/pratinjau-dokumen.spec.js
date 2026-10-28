import { expect, test } from '@playwright/test';

test.describe('Pratinjau dokumen', () => {
    test('layout: cover, KAK/TOR, RAB per sumber dana, panel kaidah dokumen', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/2/edit');
        await page.getByRole('link', { name: 'Pratinjau Dokumen', exact: true }).click();
        await expect(page).toHaveURL(/\/kegiatan\/2\/pratinjau$/);
        await expect(page.getByRole('heading', { name: 'Pratinjau Dokumen', level: 1 })).toBeVisible();

        const dokumen = page.getByTestId('dokumen-pratinjau');
        await expect(dokumen.getByTestId('pratinjau-cover')).toContainText('SUMBER DANA DIPA');
        await expect(dokumen.getByTestId('pratinjau-kak')).toBeVisible();
        await expect(dokumen.getByTestId('tabel-rab-dipa')).toBeVisible();
        await expect(page.getByTestId('keadaan-cetak')).toBeVisible();

        const kaidah = page.getByTestId('panel-kaidah');
        await expect(kaidah.locator('[data-kaidah]')).toHaveCount(9);
        await expect(kaidah.locator('[data-kaidah="total"]')).toHaveAttribute('data-status', 'lulus');
        await expect(kaidah.locator('[data-kaidah="total"]')).toContainText('Rp5.400.000,-');

        await page.getByTestId('bilah-pratinjau').getByRole('group', { name: 'Sumber dana' }).getByRole('button', { name: 'Komite' }).click();
        await expect(page).toHaveURL(/sumber=Komite/);
        await expect(dokumen.getByTestId('pratinjau-cover')).toContainText('SUMBER DANA KOMITE');
        await expect(dokumen.getByTestId('tabel-rab-komite')).toBeVisible();
        await expect(kaidah.locator('[data-kaidah="total"]')).toContainText('Rp2.500.000,-');

        await page.getByRole('navigation', { name: 'Dokumen' }).getByRole('link', { name: 'RAB' }).click();
        await expect(page.locator('#dok-rab')).toBeInViewport();

        // Rupiah & terbilang: tiap tempat cetak menautkan letaknya di dokumen.
        const tempat = kaidah.getByTestId('sinkron-dokumen');
        await expect(tempat.getByTestId('sinkron-dokumen-status')).toHaveText('Sinkron');
        await tempat.locator('[data-tempat="rab-diajukan"]').getByRole('link').click();
        await expect(page).toHaveURL(/sumber=Komite#cetak-rab-diajukan$/);
        await expect(page.locator('#cetak-rab-diajukan')).toBeInViewport();
        await expect(page.locator('#cetak-rab-diajukan')).toContainText('Rp2.500.000,- (Dua Juta Lima Ratus Ribu Rupiah)');
        await tempat.locator('[data-tempat="kak-pembiayaan"]').getByRole('link').click();
        await expect(page.locator('#cetak-kak-pembiayaan')).toBeInViewport();
        await expect(page.locator('#cetak-kak-pembiayaan')).toContainText('Rp2.500.000,- (Dua Juta Lima Ratus Ribu Rupiah)');

        // Tanggal Indonesia: teks persis tercetak, tautan ke lembar pengesahan.
        const tanggal = kaidah.getByTestId('tanggal-cetak');
        await expect(tanggal.locator('[data-tanggal="kak-pelaksanaan"] [data-teks-cetak]')).toHaveText(/^“\d{1,2}( – \d{1,2})? \S+( – \d{1,2} \S+)? 2026”$/);
        await expect(tanggal.locator('[data-tanggal="rab-pengesahan"] [data-teks-cetak]')).toHaveText(/^“Malang, \d{1,2} \S+ 2026”$/);
        await tanggal.locator('[data-tanggal="rab-pengesahan"]').getByRole('link').click();
        await expect(page.locator('#cetak-rab-pengesahan')).toBeInViewport();
        await expect(page.locator('#cetak-rab-pengesahan')).toContainText(/Malang, \d{1,2} \S+ 2026/);

        // Satu tahun untuk semua: penanda di kepala tiap dokumen & daftar tahun di dokumen.
        await expect(dokumen.getByTestId('kepala-tahun')).toHaveCount(3);
        await expect(dokumen.getByTestId('kepala-tahun').first()).toContainText('TA 2026 · tahun sinkron');
        const tahun = kaidah.getByTestId('tahun-cetak');
        await expect(tahun.getByTestId('tahun-cetak-status')).toHaveText('Sinkron TA 2026');
        await tahun.locator('[data-tahun-tempat="rab-judul"]').getByRole('link').click();
        await expect(page.locator('#cetak-rab-judul')).toBeInViewport();
        await expect(page.locator('#cetak-rab-judul')).toContainText('Tahun 2026');

        // Checklist isian wajib per tempat mengisinya; grup yang kurang terbuka dan menautkan tempat melengkapinya.
        const checklist = page.getByTestId('checklist-isian');
        await expect(checklist.locator('[data-grup]')).toHaveCount(6);
        await expect(checklist.getByTestId('ringkasan-checklist')).toHaveText(/^\d+\/\d+ lengkap$/);
        await expect(checklist.locator('[data-grup="kode"]')).toHaveAttribute('data-kurang', '0');

        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(lebar).toBeLessThanOrEqual(0);
        const matrik = checklist.locator('[data-grup="matrik"]');
        if ((await matrik.getAttribute('data-kurang')) !== '0') {
            await matrik.getByRole('link', { name: 'Lengkapi' }).click();
            await expect(page).toHaveURL(/\/kegiatan\/2\/matrik$/);
        }
        await page.goto('/kegiatan/2/pratinjau?sumber=Komite');
        await page.screenshot({ path: `storage/playwright/shots/pratinjau-dokumen-${testInfo.project.name}.png`, fullPage: true });
    });

    test('penampil seperti cetak: lembar A4 berperbesaran, perkiraan batas halaman, penunjuk halaman, daftar isi', async ({ page }, testInfo) => {
        const mobile = testInfo.project.name.includes('mobile');
        await page.goto('/demo/peran/leading');
        await page.evaluate(() => window.localStorage.removeItem('sirabtor.pratinjau.perbesaran'));
        await page.goto('/kegiatan/2/pratinjau');
        const perbesaran = page.getByTestId('perbesaran');
        const pilihan = page.getByTestId('pilihan-perbesaran');
        await expect(perbesaran).toHaveAttribute('data-perbesaran', mobile ? 'layar' : 'pas');

        // 100%: lembar selebar A4 (210 mm), cover tepat satu halaman, KAK/TOR bergaris batas halaman.
        await perbesaran.click();
        await pilihan.locator('[data-pilihan-perbesaran="100"]').click();
        await expect(perbesaran).toHaveAttribute('data-perbesaran', '100');
        await expect(perbesaran).toContainText('100%');
        const cover = page.locator('[data-lembar="cover"]');
        const kotak = await cover.boundingBox();
        expect(Math.round(kotak.width)).toBe(794);
        expect(kotak.height / kotak.width).toBeCloseTo(297 / 210, 2);
        await expect(page.getByTestId('halaman-cover')).toHaveText('1 hlm');
        await expect(page.getByTestId('halaman-kak')).toHaveText(/^\d+ hlm$/);
        const nKak = Number((await page.getByTestId('halaman-kak').textContent()).replace(/\D/g, ''));
        expect(nKak).toBeGreaterThan(1);
        const kak = page.locator('[data-lembar="kak"]');
        await expect(kak.locator('[data-batas-halaman]')).toHaveCount(nKak - 1);
        await expect(kak.locator('[data-batas-halaman]').first()).toContainText('Halaman 2');
        const rab = page.locator('[data-lembar="rab"]');
        await expect(rab.locator('col')).toHaveCount(7);
        await expect(rab.getByTestId('total-tabel-rab')).toHaveText('5.400.000');
        await rab.scrollIntoViewIfNeeded();
        await rab.screenshot({ path: `storage/playwright/shots/pratinjau-tabel-rab-${testInfo.project.name}.png` });
        await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
        const total = Number(await page.getByTestId('halaman-total').textContent());
        expect(total).toBeGreaterThanOrEqual(nKak + 2);
        await expect(page.getByTestId('halaman-aktif')).toHaveText('1');
        await kak.locator('[data-batas-halaman]').first().scrollIntoViewIfNeeded();
        await expect(page.getByTestId('halaman-aktif')).not.toHaveText('1');
        await page.screenshot({ path: `storage/playwright/shots/pratinjau-batas-halaman-${testInfo.project.name}.png` });

        // 75%: lembar mengecil, jumlah halaman tetap.
        await perbesaran.click();
        await pilihan.locator('[data-pilihan-perbesaran="75"]').click();
        await expect(perbesaran).toContainText('75%');
        await expect.poll(async () => Math.round((await cover.boundingBox()).width)).toBe(595);
        await expect(page.getByTestId('halaman-kak')).toHaveText(`${nKak} hlm`);

        // Daftar isi: bagian yang kurang ditandai; lompat ke RAB, penunjuk halaman & bagian aktif mengikuti.
        await page.getByTestId('buka-daftar-isi').click();
        const daftar = page.getByTestId('daftar-isi-pratinjau');
        await expect(daftar.locator('[data-isi]')).toHaveCount(11);
        await expect(daftar.locator('[data-isi="kak-7"]')).toContainText('7. Jadwal Kegiatan');
        await daftar.locator('[data-isi="rab"]').click();
        await expect(page).toHaveURL(/#dok-rab$/);
        await expect(daftar).toBeHidden();
        await expect(page.getByTestId('halaman-aktif')).toHaveText(String(nKak + 2));
        await page.getByTestId('buka-daftar-isi').click();
        await expect(daftar.locator('[data-isi="rab"]')).toHaveAttribute('aria-current', 'location');
        await page.keyboard.press('Escape');
        await expect(daftar).toBeHidden();
        await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
        await page.screenshot({ path: `storage/playwright/shots/pratinjau-penampil-${testInfo.project.name}.png` });

        // Pilihan diingat; lebar layar = tanpa lembar tetap & tanpa batas halaman.
        await page.reload();
        await expect(perbesaran).toHaveAttribute('data-perbesaran', '75');
        await perbesaran.click();
        await pilihan.locator('[data-pilihan-perbesaran="layar"]').click();
        await expect(page.getByTestId('penunjuk-halaman')).toBeHidden();
        await expect(kak.locator('[data-batas-halaman]').first()).toBeHidden();
        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(lebar).toBeLessThanOrEqual(0);
    });

    test('sunting teks langsung: tujuan disunting di lembar KAK lalu dipulihkan, Esc membatalkan, mode baca tanpa bingkai', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/1/pratinjau');
        const mode = page.getByTestId('mode-pratinjau');
        await mode.getByRole('button', { name: 'Sunting teks' }).click();
        await expect(page).toHaveURL(/mode=sunting/);
        await expect(mode.getByRole('button', { name: 'Sunting teks' })).toHaveAttribute('aria-pressed', 'true');
        await expect(page.getByTestId('petunjuk-sunting')).toBeVisible();
        const kak = page.locator('[data-lembar="kak"]');
        // Delapan bidang: blok bersunting, atau tombol "Tambah" bila belum tampil (mis. latar belakang khusus kosong).
        await expect(kak.locator('[data-sunting], [data-testid^="tambah-"]')).toHaveCount(8);

        const blok = kak.locator('[data-sunting="tujuan"]');
        const kalimatAwal = (await blok.textContent()).replace(/Sunting\s+Tujuan/, '').trim();
        await blok.getByTestId('sunting-tujuan').click();
        const editor = page.getByTestId('penyunting-tujuan');
        const teks = editor.getByRole('textbox', { name: 'Tujuan' });
        await expect(teks).toBeFocused();
        const awal = await teks.inputValue();
        await teks.fill('Teks uji yang dibatalkan.');
        // Ketikan belum disimpan: Esc meminta konfirmasi membuangnya.
        page.once('dialog', (dialog) => dialog.accept());
        await teks.press('Escape');
        await expect(editor).toHaveCount(0);
        await expect(kak).not.toContainText('Teks uji yang dibatalkan');

        await kak.getByTestId('sunting-tujuan').click();
        await teks.fill('meningkatkan mutu kurikulum madrasah secara partisipatif.');
        await editor.getByTestId('simpan-sunting').click();
        await expect(editor).toHaveCount(0);
        await expect(kak.locator('[data-sunting="tujuan"]')).toContainText('bertujuan meningkatkan mutu kurikulum madrasah secara partisipatif.');
        await kak.locator('[data-sunting="tujuan"]').scrollIntoViewIfNeeded();
        await page.screenshot({ path: `storage/playwright/shots/pratinjau-sunting-${testInfo.project.name}.png` });

        // Pulihkan data bersama.
        await kak.getByTestId('sunting-tujuan').click();
        await teks.fill(awal);
        await editor.getByTestId('simpan-sunting').click();
        await expect(editor).toHaveCount(0);
        await expect(kak.locator('[data-sunting="tujuan"]')).toContainText(kalimatAwal);

        await mode.getByRole('button', { name: 'Baca' }).click();
        await expect(page).not.toHaveURL(/mode=/);
        await expect(kak.locator('[data-sunting]')).toHaveCount(0);
        await expect(page.getByTestId('petunjuk-sunting')).toHaveCount(0);
    });

    test('perubahan belum disimpan: penanda, konfirmasi berpindah, draf lokal dipulihkan lalu dibuang', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        const drafLokal = () => page.evaluate(() => Object.keys(window.localStorage).filter((k) => k.startsWith('sirabtor.sunting.')).length);
        await page.evaluate(() => Object.keys(window.localStorage).filter((k) => k.startsWith('sirabtor.sunting.')).forEach((k) => window.localStorage.removeItem(k)));
        await page.goto('/kegiatan/1/pratinjau?mode=sunting');
        const kak = page.locator('[data-lembar="kak"]');
        await kak.getByTestId('sunting-sasaran').click();
        const editor = page.getByTestId('penyunting-sasaran');
        const teks = editor.getByRole('textbox', { name: 'Sasaran' });
        const awal = await teks.inputValue();
        await expect(editor.getByTestId('belum-disimpan')).toBeHidden();
        await teks.fill(`${awal} Tambahan uji.`);
        await expect(editor.getByTestId('belum-disimpan')).toBeVisible();
        await expect(editor).toHaveAttribute('data-sunting-kotor', 'ya');
        expect(await drafLokal()).toBe(1);
        await editor.screenshot({ path: `storage/playwright/shots/pratinjau-belum-disimpan-${testInfo.project.name}.png` });

        // Ganti mode: konfirmasi ditolak → tetap menyunting.
        page.once('dialog', (dialog) => dialog.dismiss());
        await page.getByTestId('mode-pratinjau').getByRole('button', { name: 'Baca' }).click();
        await expect(editor).toBeVisible();
        await expect(page).toHaveURL(/mode=sunting/);

        // Pindah bidang & setujui: ketikan sasaran tetap tersimpan sebagai draf lokal (titik di tombol Sunting).
        page.once('dialog', (dialog) => dialog.accept());
        await kak.getByTestId('sunting-tujuan').click();
        await expect(page.getByTestId('penyunting-tujuan')).toBeVisible();
        await page.getByTestId('penyunting-tujuan').getByTestId('batal-sunting').click();
        await expect(page.getByTestId('penyunting-tujuan')).toHaveCount(0);
        await expect(kak.getByTestId('sunting-sasaran').locator('[data-draf-lokal]')).toBeVisible();

        // Buka lagi: tawarkan pulihkan.
        await kak.getByTestId('sunting-sasaran').click();
        await expect(editor.getByTestId('draf-lokal')).toBeVisible();
        await editor.getByTestId('pulihkan-draf').click();
        await expect(teks).toHaveValue(`${awal} Tambahan uji.`);
        await expect(editor.getByTestId('belum-disimpan')).toBeVisible();

        // Batal & setujui: draf lokal dibuang, data kegiatan tidak berubah.
        page.once('dialog', (dialog) => dialog.accept());
        await editor.getByTestId('batal-sunting').click();
        await expect(editor).toHaveCount(0);
        await expect(kak.getByTestId('sunting-sasaran').locator('[data-draf-lokal]')).toBeHidden();
        await expect(kak.locator('[data-sunting="sasaran"]')).not.toContainText('Tambahan uji');
        expect(await drafLokal()).toBe(0);
    });

    test('penanda isian kosong: lencana bagian, tautan melengkapi, navigasi kosong berikutnya', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/2/pratinjau');
        const navigasi = page.getByTestId('navigasi-kosong');
        const jumlah = Number(await navigasi.getAttribute('data-jumlah'));
        expect(jumlah).toBeGreaterThan(0);
        const ruang = page.getByTestId('ruang-lembar');
        await expect(ruang.locator('[data-kosong]')).toHaveCount(jumlah);
        await expect(navigasi).toContainText(`${jumlah} kosong`);

        // Penanda di bagian 4 (matrik) menautkan tempat melengkapinya.
        const bagian4 = page.locator('#cetak-kak-bagian-4');
        await expect(bagian4.getByTestId('penanda-bagian')).toContainText('belum lengkap');
        await expect(bagian4.locator('[data-kosong][data-asal="matrik"]').getByTestId('lengkapi-kosong')).toHaveAttribute('href', /\/kegiatan\/2\/matrik$/);

        // Berikutnya: penanda pertama disorot & terlihat, lalu berpindah; sebelumnya dari awal = penanda terakhir.
        await navigasi.getByTestId('kosong-berikutnya').click();
        const pertama = ruang.locator('[data-kosong]').first();
        await expect(pertama).toBeInViewport();
        await expect(pertama).toHaveClass(/penanda-sorot/);
        await expect(navigasi).toContainText(`1/${jumlah} kosong`);
        await page.screenshot({ path: `storage/playwright/shots/pratinjau-penanda-kosong-${testInfo.project.name}.png` });
        if (jumlah > 1) {
            await navigasi.getByTestId('kosong-berikutnya').click();
            await expect(navigasi).toContainText(`2/${jumlah} kosong`);
            await expect(ruang.locator('[data-kosong]').nth(1)).toBeInViewport();
        }
        await page.reload();
        await page.getByTestId('navigasi-kosong').getByTestId('kosong-sebelumnya').click();
        await expect(page.getByTestId('navigasi-kosong')).toContainText(`${jumlah}/${jumlah} kosong`);
        await expect(ruang.locator('[data-kosong]').last()).toBeInViewport();
    });

    test('kaidah: RAB yang melebihi pagu ditandai gagal', async ({ page }) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/3/pratinjau?sumber=Komite');
        const pagu = page.getByTestId('panel-kaidah').locator('[data-kaidah="pagu"]');
        await expect(pagu).toHaveAttribute('data-status', 'gagal');
        await expect(pagu).toContainText('Melebihi pagu Rp450.000.');
        await expect(page.getByTestId('ringkasan-kaidah')).toContainText('perlu perhatian');

        // Cap DRAF: alasan & cara menghilangkannya, penanda di kepala tiap dokumen, cap air di kertas.
        await expect(page.getByTestId('dokumen-pratinjau').getByTestId('kepala-draf')).toHaveCount(3);
        await expect(page.getByTestId('dokumen-pratinjau').getByTestId('cap-draf')).toHaveCount(3);
        await page.getByTestId('keadaan-cetak').click();
        const alasan = page.getByTestId('alasan-draf');
        await expect(alasan).toBeVisible();
        await expect(alasan.locator('[data-alasan="pagu"]')).toContainText('RAB Komite melebihi pagu Rp450.000.');
        await alasan.locator('[data-alasan="pagu"]').getByRole('link').click();
        await expect(page).toHaveURL(/\/kegiatan\/3\/rab\?sumber=Komite$/);
    });

    test('halaman RAB membuka letak tempat cetak di pratinjau', async ({ page }) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/kegiatan/1/rab');
        await page.getByTestId('sinkron-dokumen').locator('[data-tempat="rab-total"]').getByRole('link').click();
        await expect(page).toHaveURL(/\/kegiatan\/1\/pratinjau\?sumber=Komite#cetak-rab-total$/);
        await expect(page.locator('#cetak-rab-total')).toBeInViewport();
        await expect(page.locator('#cetak-rab-total')).toContainText('10.000.000');
    });
});
