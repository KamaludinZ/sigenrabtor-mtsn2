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
            // Pastikan asal & sasaran sama-sama di dalam layar.
            await page.locator('tbody[data-kelompok="lain"]').evaluate((el) => el.scrollIntoView({ block: 'end', behavior: 'instant' }));
            const asal = await pegangan.boundingBox();
            await page.mouse.move(asal.x + asal.width / 2, asal.y + asal.height / 2);
            await page.mouse.down();
            await page.mouse.move(asal.x + 30, asal.y + 20, { steps: 5 });
            const sasaran = page.locator('tbody[data-kelompok="lain"] td').first();
            for (let i = 0; i < 4; i++) {
                const kotak = await sasaran.boundingBox();
                await page.mouse.move(kotak.x + 60, kotak.y + kotak.height / 2, { steps: 15 });
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
