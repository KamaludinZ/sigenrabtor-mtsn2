import { expect, test } from '@playwright/test';

test.describe('Halaman RAB (data tiruan)', () => {
    test('header, rincian, total, dan ringkasan pagu tampil', async ({ page }, testInfo) => {
        await page.goto('/kegiatan/1/rab');
        await expect(page.getByRole('heading', { name: 'Rencana Anggaran Biaya' })).toBeVisible();
        await expect(page.getByRole('tab', { name: /RAB Komite/ })).toHaveAttribute('aria-selected', 'true');
        await expect(page.getByTestId('header-rab')).toContainText('Tim Penjamin Mutu');
        await expect(page.getByTestId('header-rab')).toContainText('6.17.12');
        await expect(page.getByTestId('ringkasan-rab')).toContainText('Rp10.000.000');
        await expect(page.getByTestId('total-anggaran-status')).toHaveText('Sesuai pagu');
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

        // RAB masih kosong: tidak ada "Nol Rupiah", kalimat pembiayaan diganti peringatan.
        await expect(page.getByTestId('kalimat-pembiayaan-kosong')).toBeVisible();
        await expect(page.getByText('Nol Rupiah')).toHaveCount(0);

        await page.getByRole('button', { name: 'Tambah item belanja' }).click();
        await expect(page.locator('#item_uraian')).toBeFocused();
        await page.locator('#item_uraian').fill('Honorarium Narasumber');
        await page.locator('#item_volume').fill('3');
        await page.locator('#item_satuan').fill('OJ');
        await page.locator('#item_harga_satuan').pressSequentially('600000');
        await expect(page.locator('#item_harga_satuan')).toHaveValue('600.000');
        await expect(page.getByTestId('jumlah-item')).toHaveText('Rp1.800.000');
        await expect(page.getByTestId('jumlah-item-terbilang')).toHaveText('Satu Juta Delapan Ratus Ribu Rupiah');
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
        await expect(page.getByTestId('kalimat-pembiayaan')).toContainText('(Dua Juta Seratus Ribu Rupiah) yang dibebankan pada dana DIPA');
        await expect(page.getByTestId('ringkasan-rab')).toContainText('Melebihi pagu');
    });
});

test.describe('Daftar item dengan subtotal', () => {
    test('mode per kelompok belanja & per kode akun menampilkan subtotal', async ({ page }, testInfo) => {
        await page.goto('/kegiatan/1/rab');
        await page.getByRole('button', { name: 'Per kelompok belanja' }).click();
        await expect(page).toHaveURL(/tampil=kategori/);
        const tampilan = testInfo.project.name === 'desktop' ? page.locator('table') : page.getByTestId('rincian-kartu');
        await expect(tampilan).toContainText('Subtotal');
        await expect(tampilan).toContainText('3.930.000');
        await expect(tampilan).toContainText('5.920.000');
        await page.screenshot({ path: `storage/playwright/shots/subtotal-${testInfo.project.name}.png`, fullPage: true });

        await page.getByRole('button', { name: 'Per kode akun' }).click();
        await expect(page).toHaveURL(/tampil=kode/);
        await expect(tampilan).toContainText('521213 · Belanja Honor Output Kegiatan');

        await page.reload();
        await expect(page.getByRole('button', { name: 'Per kode akun' })).toHaveAttribute('aria-pressed', 'true');
    });
});

test.describe('Ubah & hapus item belanja', () => {
    test('ubah volume item, hapus item lalu urungkan', async ({ page }, testInfo) => {
        // Buat kegiatan sendiri agar tidak mengubah data contoh yang dipakai tes lain.
        await page.goto('/kegiatan/buat');
        await page.locator('#nama_kegiatan').fill(`Kegiatan Ubah ${testInfo.project.name}`);
        await page.getByTestId('sumber-komite').getByRole('checkbox').check();
        await expect(page.locator('#pagu_komite')).toBeEnabled();
        await page.locator('#pagu_komite').pressSequentially('5000000');
        await page.getByRole('button', { name: 'Simpan & lanjut ke RAB' }).click();
        await expect(page).toHaveURL(/\/rab/);

        for (const [uraian, volume, harga] of [['Snack Peserta', '20', '10000'], ['Spanduk kegiatan', '1', '150000']]) {
            await page.getByRole('button', { name: 'Tambah item belanja' }).click();
            await page.locator('#item_uraian').fill(uraian);
            await page.locator('#item_volume').fill(volume);
            await page.locator('#item_satuan').fill('buah');
            await page.locator('#item_harga_satuan').pressSequentially(harga);
            await page.getByRole('button', { name: 'Simpan item' }).click();
            await expect(page.getByText(`Item ditambahkan: ${uraian}`)).toBeVisible();
        }
        await expect(page.getByTestId('ringkasan-rab')).toContainText('Rp350.000');

        await page.getByRole('button', { name: 'Ubah item 1' }).click();
        await expect(page.locator('#item_volume')).toHaveValue('20');
        await page.locator('#item_volume').fill('1.000');
        await expect(page.getByTestId('jumlah-item')).toHaveText('Rp10.000.000');
        await page.locator('#item_volume').fill('25');
        await page.getByRole('button', { name: 'Simpan perubahan' }).click();
        await expect(page.getByText('Item diperbarui: Snack Peserta (Rp250.000).')).toBeVisible();
        await expect(page.getByTestId('ringkasan-rab')).toContainText('Rp400.000');

        await page.getByRole('button', { name: 'Hapus item 2' }).click();
        await expect(page.getByText('“Spanduk kegiatan” dihapus dari RAB Komite.')).toBeVisible();
        await expect(page.getByTestId('ringkasan-rab')).toContainText('Rp250.000');
        await page.screenshot({ path: `storage/playwright/shots/hapus-urungkan-${testInfo.project.name}.png` });
        await page.getByRole('button', { name: 'Urungkan' }).click();
        await expect(page.getByTestId('ringkasan-rab')).toContainText('Rp400.000');

        // Total KAK = total RAB: halaman Data Kegiatan menampilkan angka yang sama.
        await page.getByRole('link', { name: 'Data Kegiatan' }).last().click();
        await expect(page.getByTestId('rekap-rab-komite')).toContainText('Rp400.000');
        await expect(page.getByTestId('lanjut-rab')).toContainText('Rp400.000 dari pagu Rp5.000.000');
    });
});

async function buatKegiatanDenganItem(page, nama, items) {
    await page.goto('/kegiatan/buat');
    await page.locator('#nama_kegiatan').fill(nama);
    await page.getByTestId('sumber-komite').getByRole('checkbox').check();
    await expect(page.locator('#pagu_komite')).toBeEnabled();
    await page.locator('#pagu_komite').pressSequentially('5000000');
    await page.getByRole('button', { name: 'Simpan & lanjut ke RAB' }).click();
    await expect(page).toHaveURL(/\/rab/);
    for (const [uraian, volume, harga] of items) {
        await page.getByRole('button', { name: 'Tambah item belanja' }).click();
        await page.locator('#item_uraian').fill(uraian);
        await page.locator('#item_volume').fill(volume);
        await page.locator('#item_satuan').fill('buah');
        await page.locator('#item_harga_satuan').pressSequentially(harga);
        await page.getByRole('button', { name: 'Simpan item' }).click();
        await expect(page.getByText(`Item ditambahkan: ${uraian}`)).toBeVisible();
    }
}

test.describe('Perkiraan total RAB lokal', () => {
    test('total, terbilang, dan status pagu ikut ketikan sebelum item disimpan', async ({ page }, testInfo) => {
        await buatKegiatanDenganItem(page, `Kegiatan Total Lokal ${testInfo.project.name}`, [['Snack rapat', '10', '300000']]);
        const nilai = page.getByTestId('perkiraan-nilai');
        const status = page.getByTestId('perkiraan-status');

        await page.getByRole('button', { name: 'Tambah item belanja' }).click();
        await expect(nilai).toHaveText('Rp3.000.000');
        await expect(page.getByTestId('perkiraan-total')).toContainText('Total RAB Komite saat ini');

        await page.locator('#item_uraian').fill('Honorarium narasumber');
        await page.locator('#item_volume').fill('1');
        await page.locator('#item_satuan').fill('OJ');
        await page.locator('#item_harga_satuan').pressSequentially('2500000');
        await expect(nilai).toHaveText('Rp5.500.000');
        await expect(page.getByTestId('perkiraan-terbilang')).toHaveText('Lima Juta Lima Ratus Ribu Rupiah');
        await expect(status).toHaveText('Melebihi pagu Rp500.000');
        await expect(status).toHaveClass(/text-red-600/);

        await page.locator('#item_harga_satuan').fill('');
        await page.locator('#item_harga_satuan').pressSequentially('2000000');
        await expect(nilai).toHaveText('Rp5.000.000');
        await expect(status).toHaveText('Sesuai pagu');
        await page.getByTestId('form-item').screenshot({ path: `storage/playwright/shots/perkiraan-total-${testInfo.project.name}.png` });

        // Angka lokal = angka server setelah disimpan.
        await page.getByRole('button', { name: 'Simpan item' }).click();
        await expect(page.getByTestId('form-item')).toHaveCount(0);
        await expect(page.getByTestId('total-anggaran-nilai')).toHaveText('Rp5.000.000');
        await expect(page.getByTestId('total-anggaran-status')).toHaveText('Sesuai pagu');

        // Mode ubah: jumlah lama diganti jumlah baru, bukan ditambahkan.
        await page.getByRole('button', { name: 'Ubah item 1' }).click();
        await expect(nilai).toHaveText('Rp5.000.000');
        await page.locator('#item_volume').fill('5');
        await expect(nilai).toHaveText('Rp3.500.000');
        await expect(status).toHaveText('Sisa pagu Rp1.500.000');
        await page.getByTestId('form-item').getByRole('button', { name: 'Batal' }).click();
        await expect(page.getByTestId('total-anggaran-nilai')).toHaveText('Rp5.000.000');
    });
});

test.describe('Kelompok kategori interaktif', () => {
    test('pindah kategori (seret di desktop, pilihan di mobile), ciutkan, dan lompat dari ringkasan', async ({ page }, testInfo) => {
        await buatKegiatanDenganItem(page, `Kegiatan Kelompok ${testInfo.project.name}`, [
            ['Air Mineral', '3', '40000'],
            ['Honorarium Narasumber', '2', '500000'],
        ]);
        await page.getByRole('button', { name: 'Per kelompok belanja' }).click();
        await expect(page).toHaveURL(/tampil=kategori/);

        if (testInfo.project.name === 'desktop') {
            // Gerakan bertahap & bidik ulang: baris bergeser selama diseret, jadi posisi sasaran dihitung ulang.
            const pegangan = page.getByLabel('Seret item 1');
            // Pastikan asal & sasaran sama-sama di dalam layar, jauh dari tepi bawah (autoscroll Sortable menggeser sasaran).
            await page.locator('tbody[data-kelompok="lain"]').evaluate((el) => el.scrollIntoView({ block: 'center', behavior: 'instant' }));
            const asal = await pegangan.boundingBox();
            await page.mouse.move(asal.x + asal.width / 2, asal.y + asal.height / 2);
            await page.mouse.down();
            await page.mouse.move(asal.x + 30, asal.y + 20, { steps: 5 });
            const sasaran = page.locator('tbody[data-kelompok="lain"] td').first();
            // Bidik ulang sampai baris yang diseret benar-benar berada di kelompok sasaran (tata letak bergeser selama diseret).
            const sudahMasuk = () => page.locator('tbody[data-kelompok="lain"]').getByLabel(/Seret item/).count();
            for (let i = 0; i < 10 && (await sudahMasuk()) === 0; i++) {
                const kotak = await sasaran.boundingBox();
                await page.mouse.move(kotak.x + 60, kotak.y + kotak.height / 2 + (i % 2 ? 6 : -6), { steps: 15 });
                await page.waitForTimeout(150); // beri waktu Sortable memproses dragover saat mesin sibuk
            }
            await page.mouse.up();
        } else {
            await page.getByLabel('Kelompok belanja item 1').selectOption('lain');
        }
        await expect(page.getByText('“Air Mineral” dipindah ke kelompok Lain-lain.')).toBeVisible();
        await expect(page.getByTestId('ringkasan-rab')).toContainText('Lain-lain');

        if (testInfo.project.name === 'desktop') {
            await expect(page.locator('tbody[data-kelompok="lain"]')).toContainText('Air Mineral');
            await page.screenshot({ path: 'storage/playwright/shots/kelompok-interaktif-desktop.png' });

            // Ciutkan semua lalu buka lagi.
            await page.getByRole('button', { name: 'Ciutkan semua' }).click();
            await expect(page.locator('tbody[data-kelompok="jasa"] tr[data-item]')).toBeHidden();
            await page.getByRole('button', { name: 'Buka semua' }).click();
            await expect(page.locator('tbody[data-kelompok="jasa"] tr[data-item]')).toBeVisible();
        }

        // Klik "Jasa" di ringkasan: tampilan kategori + gulir ke kelompoknya.
        await page.getByRole('button', { name: 'Urutan cetak' }).click();
        await page.getByTitle('Tampilkan kelompok Jasa').click();
        await expect(page).toHaveURL(/tampil=kategori/);
    });
});

test.describe('Rekap anggaran per kelompok', () => {
    test('komposisi, tooltip, tabel, dan kartu per kelompok', async ({ page }, testInfo) => {
        await page.goto('/kegiatan/1/rab');
        await page.getByRole('tab', { name: 'Rekap per kelompok' }).click();
        await expect(page).toHaveURL(/halaman=rekap/);
        await expect(page.getByTestId('rekap-total')).toHaveText('Rp10.000.000');

        const segmenJasa = page.getByTestId('batang-komite').getByLabel(/Jasa Rp5\.920\.000/);
        await segmenJasa.hover();
        await expect(page.getByRole('tooltip')).toContainText('Rp5.920.000');
        await expect(page.getByRole('tooltip')).toContainText('Komite · Jasa');

        await expect(page.getByTestId('tabel-rekap-kelompok')).toContainText('3.930.000');
        await expect(page.getByTestId('kartu-kelompok-jasa')).toContainText('Honorarium Pemateri');
        await page.screenshot({ path: `storage/playwright/shots/rekap-${testInfo.project.name}.png`, fullPage: true });

        await page.getByTestId('kartu-kelompok-bahan').getByRole('button', { name: /item lainnya/ }).click();
        await expect(page.getByRole('button', { name: 'Per kelompok belanja' })).toHaveAttribute('aria-pressed', 'true');
    });
});

test.describe('RAB per sumber dana (contoh DIPA + Komite)', () => {
    test('dua RAB terpisah dengan pagu, total, dan terbilang masing-masing', async ({ page }, testInfo) => {
        await page.goto('/kegiatan/2/rab');
        await expect(page.getByRole('tab', { name: /RAB DIPA/ })).toHaveAttribute('aria-selected', 'true');
        await expect(page.getByRole('tab', { name: /RAB DIPA/ })).toContainText('Pagu Rp6.000.000');
        await expect(page.getByTestId('total-rab')).toHaveText('5.400.000');
        await expect(page.getByTestId('header-terbilang')).toHaveText('Lima Juta Empat Ratus Ribu Rupiah');
        await expect(page.getByTestId('ringkasan-rab')).toContainText('Sisa pagu Rp600.000');

        await expect(page.getByTestId('total-kegiatan')).toContainText('Rp7.900.000');
        await expect(page.getByTestId('status-pagu-dipa')).toHaveText('Sisa pagu Rp600.000');

        // Label sumber & pagu tetap terlihat di kepala rincian; header RAB menyebut sumber dana seperti KAK.
        await expect(page.getByTestId('pagu-rincian')).toBeVisible();
        await expect(page.getByTestId('pagu-rincian')).toContainText('Pagu Rp6.000.000');
        await expect(page.getByTestId('pagu-rincian')).toContainText('Sisa pagu Rp600.000');
        await expect(page.getByTestId('header-rab')).toContainText('DIPA MTsN 2 Kota Malang Tahun 2026');
        await expect(page.getByTestId('kalimat-pembiayaan')).toContainText('dana DIPA MTsN 2 Kota Malang Tahun 2026');
        await page.getByTestId('rincian-rab').screenshot({ path: `storage/playwright/shots/rincian-label-${testInfo.project.name}.png` });

        // Dari Rekap, kartu sumber membuka rincian sumber itu.
        await page.getByRole('tab', { name: 'Rekap per kelompok' }).click();
        await expect(page).toHaveURL(/halaman=rekap/);
        await page.getByRole('tab', { name: /RAB Komite/ }).click();
        await expect(page).toHaveURL(/sumber=Komite/);
        await expect(page.getByRole('tab', { name: 'Rincian belanja' })).toHaveAttribute('aria-selected', 'true');
        await expect(page.getByTestId('total-rab')).toHaveText('2.500.000');
        await expect(page.getByTestId('kalimat-pembiayaan')).toContainText('dibebankan pada dana KOMITE');
        await expect(page.getByTestId('ringkasan-rab')).toContainText('Sesuai pagu');
        await page.screenshot({ path: `storage/playwright/shots/rab-dua-sumber-${testInfo.project.name}.png`, fullPage: true });
    });

    test('Data Kegiatan: rincian tiap RAB bisa dibuka tanpa meninggalkan halaman', async ({ page }, testInfo) => {
        await page.goto('/kegiatan/2/edit');
        const rincianDipa = page.getByTestId('rincian-rab-dipa');
        await rincianDipa.scrollIntoViewIfNeeded();
        await expect(rincianDipa.getByTestId('tabel-rab-dipa')).toBeHidden();
        await rincianDipa.getByText('Lihat rincian RAB DIPA').click();
        await expect(rincianDipa.getByTestId('tabel-rab-dipa')).toBeVisible();
        await expect(rincianDipa).toContainText('Honorarium tim supervisor');
        await expect(rincianDipa).toContainText('Rp5.400.000,-');
        await expect(page.getByTestId('rincian-rab-komite').getByTestId('tabel-rab-komite')).toBeHidden();
        await page.getByTestId('sumber-dipa').screenshot({ path: `storage/playwright/shots/rincian-rab-data-kegiatan-${testInfo.project.name}.png` });
    });

    test('Semua RAB: kedua RAB tampil berdampingan, tombol ubah membuka rinciannya', async ({ page }, testInfo) => {
        await page.goto('/kegiatan/2/rab');
        await page.getByRole('tab', { name: 'Semua RAB' }).click();
        await expect(page).toHaveURL(/halaman=semua/);
        await expect(page.getByTestId('semua-rab-dipa')).toContainText('5.400.000');
        await expect(page.getByTestId('semua-rab-dipa')).toContainText('Lima Juta Empat Ratus Ribu Rupiah');
        await expect(page.getByTestId('semua-rab-komite')).toContainText('Piagam penghargaan guru terbaik');
        await page.screenshot({ path: `storage/playwright/shots/semua-rab-${testInfo.project.name}.png`, fullPage: true });

        await page.getByRole('button', { name: 'Ubah RAB Komite' }).click();
        await expect(page).toHaveURL(/sumber=Komite/);
        await expect(page.getByRole('tab', { name: 'Rincian belanja' })).toHaveAttribute('aria-selected', 'true');
        await expect(page.getByTestId('total-rab')).toHaveText('2.500.000');
    });
});
