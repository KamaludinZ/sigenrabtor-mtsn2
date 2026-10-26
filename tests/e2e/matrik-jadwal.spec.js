import { expect, test } from '@playwright/test';

/** Kegiatan sendiri (10–12 Agustus 2026, laporan 22 Agustus) agar contoh bersama tidak berubah; membuka tab Matrik & Jadwal. */
async function bukaMatrikKegiatanBaru(page, nama) {
    page.on('dialog', (dialog) => dialog.accept());
    await page.goto('/kegiatan/buat');
    await page.locator('#nama_kegiatan').fill(nama);
    await page.locator('#tanggal_mulai').fill('2026-08-10');
    await page.locator('#lama_hari').fill('3');
    await page.locator('#lama_hari').press('Tab');
    await expect(page.locator('#tanggal_laporan')).toHaveValue('2026-08-22', { timeout: 30_000 });
    await page.getByRole('button', { name: 'Simpan Draf' }).first().click();
    await expect(page).toHaveURL(/\/kegiatan\/\d+\/edit/, { timeout: 30_000 });
    const id = page.url().match(/\/kegiatan\/(\d+)\/edit/)[1];
    await page.goto(`/kegiatan/${id}/matrik`);

    return id;
}

test.describe('Matrik & Jadwal Kegiatan', () => {
    test('tab ruang kerja membuka halaman: acuan bulan pelaksanaan, matrik tahapan, dan 6 baris jadwal', async ({ page }, testInfo) => {
        await page.goto('/kegiatan/1/edit');
        await page.getByRole('link', { name: 'Matrik & Jadwal' }).click();
        await expect(page).toHaveURL(/\/kegiatan\/1\/matrik$/, { timeout: 30_000 });
        await expect(page.getByRole('heading', { name: 'Matrik & Jadwal Kegiatan' })).toBeVisible();

        await expect(page.getByTestId('acuan-pelaksanaan')).toContainText('Bulan pelaksanaan Juli 2026');
        const matrik = page.getByTestId('matrik-tahapan');
        await expect(matrik.locator('tbody[data-tahapan]')).toHaveCount(3);
        await expect(matrik.locator('thead th[data-bulan]')).toHaveCount(12);
        await expect(matrik.locator('thead th[data-bulan="7"]')).toHaveClass(/bg-brand-50/);
        await expect(matrik.getByTestId('butir-persiapan-1')).toContainText('Rapat dan pembagian job deskripsi');

        const jadwal = page.getByTestId('jadwal-kegiatan');
        await expect(jadwal.locator('li[data-testid^="baris-jadwal-"]')).toHaveCount(6);
        await expect(page.getByTestId('ringkasan-jadwal')).toHaveText('0 dari 6 baris terisi');
        await expect(page.getByTestId('kelengkapan-matrik')).toHaveAttribute('data-lengkap', 'tidak');

        // Tabel matrik digulir di dalam kartunya; halaman tidak bergeser ke samping di layar sempit.
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        if (testInfo.project.name === 'mobile') {
            // Layar sempit: tabel langsung digeser ke bulan pelaksanaan (Juli).
            await expect.poll(() => page.getByTestId('matrik-wadah').evaluate((el) => el.scrollLeft)).toBeGreaterThan(0);
            await expect(matrik.locator('thead th[data-bulan="7"]')).toBeInViewport();
        }
        await page.screenshot({ path: `storage/playwright/shots/matrik-jadwal-${testInfo.project.name}.png`, fullPage: true });

        await page.getByRole('link', { name: 'Ubah di Data Kegiatan' }).click();
        await expect(page).toHaveURL(/\/kegiatan\/1\/edit#pelaksanaan$/, { timeout: 30_000 });
    });

    test('grid: klik/ketuk sel, seret beberapa bulan, keyboard, isi dari jadwal pelaksanaan, lalu simpan', async ({ page }, testInfo) => {
        const mobile = testInfo.project.name === 'mobile';
        await bukaMatrikKegiatanBaru(page, `Kegiatan Matrik ${testInfo.project.name} ${Date.now()}`);

        const sel = (tahapan, butir, bulan) => page.getByTestId(`butir-${tahapan}-${butir}`).locator(`td[data-bulan="${bulan}"] button`);
        const tekan = (target) => (mobile ? target.tap() : target.click());
        const tengah = async (target) => {
            await target.evaluate((el) => el.scrollIntoView({ block: 'center', inline: 'center', behavior: 'instant' }));
            const kotak = await target.boundingBox();
            return [kotak.x + kotak.width / 2, kotak.y + kotak.height / 2];
        };

        // Menandai sel hanya mengubah state lokal: tidak ada request ke server sampai aksi berikutnya (Simpan, isi otomatis).
        const permintaan = [];
        const catat = (r) => r.method() === 'POST' && r.url().includes('livewire') && permintaan.push(r.url());
        page.on('request', catat);

        // Klik (mouse) / ketuk (layar sentuh) menandai satu bulan seketika.
        await tekan(sel('persiapan', 1, 7));
        await expect(sel('persiapan', 1, 7)).toHaveAttribute('aria-pressed', 'true');
        await expect(page.getByTestId('ringkasan-matrik')).toHaveText('1 tanda bulan');
        await expect(page.getByTestId('belum-disimpan')).toBeVisible();
        await expect(page.getByTestId('kelengkapan-matrik')).toContainText('Tandai minimal satu bulan di setiap tahapan.');

        // Pindah tab ruang kerja (wire:navigate) selagi ada perubahan lokal meminta konfirmasi; batal → tetap di sini.
        page.removeAllListeners('dialog');
        let pesanDialog = '';
        page.once('dialog', (dialog) => {
            pesanDialog = dialog.message();
            dialog.dismiss();
        });
        await page.getByRole('navigation', { name: 'Breadcrumb' }).getByRole('link', { name: 'Data Kegiatan' }).click();
        await expect.poll(() => pesanDialog).toContain('Perubahan matrik & jadwal belum disimpan.');
        await expect(page).toHaveURL(/\/matrik$/);
        await expect(sel('persiapan', 1, 7)).toHaveAttribute('aria-pressed', 'true');
        page.on('dialog', (dialog) => dialog.accept());

        if (!mobile) {
            // Tekan lalu seret: Agu–Okt ditandai sekaligus; seret lagi dari sel bertanda untuk menghapus.
            const [x8, y8] = await tengah(sel('pelaksanaan', 2, 8));
            const [x10] = await tengah(sel('pelaksanaan', 2, 10));
            await page.mouse.move(x8, y8);
            await page.mouse.down();
            await page.mouse.move((x8 + x10) / 2, y8, { steps: 4 });
            await page.mouse.move(x10, y8, { steps: 4 });
            await page.mouse.up();
            for (const bulan of [8, 9, 10]) {
                await expect(sel('pelaksanaan', 2, bulan)).toHaveAttribute('aria-pressed', 'true');
            }
            await expect(sel('pelaksanaan', 2, 7)).toHaveAttribute('aria-pressed', 'false');

            const [x9, y9] = await tengah(sel('pelaksanaan', 2, 9));
            const [x10b] = await tengah(sel('pelaksanaan', 2, 10));
            await page.mouse.move(x9, y9);
            await page.mouse.down();
            await page.mouse.move(x10b, y9, { steps: 4 });
            await page.mouse.up();
            await expect(sel('pelaksanaan', 2, 9)).toHaveAttribute('aria-pressed', 'false');
            await expect(sel('pelaksanaan', 2, 10)).toHaveAttribute('aria-pressed', 'false');
            await expect(sel('pelaksanaan', 2, 8)).toHaveAttribute('aria-pressed', 'true');

            // Keyboard: Spasi menandai/melepas sel yang sedang fokus.
            await sel('evaluasi_pelaporan', 2, 12).focus();
            await page.keyboard.press('Space');
            await expect(sel('evaluasi_pelaporan', 2, 12)).toHaveAttribute('aria-pressed', 'true');
            await page.keyboard.press('Space');
            await expect(sel('evaluasi_pelaporan', 2, 12)).toHaveAttribute('aria-pressed', 'false');
            await expect(page.getByTestId('ringkasan-matrik')).toHaveText('2 tanda bulan');
        }
        expect(permintaan).toEqual([]);
        page.off('request', catat);

        // Isi dari jadwal pelaksanaan (Persiapan Juli, Pelaksanaan & laporan Agustus) — bisa diurungkan.
        await page.getByRole('button', { name: 'Isi dari jadwal pelaksanaan' }).click();
        await expect(page.getByTestId('matrik-berubah')).toContainText('Matrik diisi dari jadwal pelaksanaan.');
        await expect(sel('persiapan', 2, 7)).toHaveAttribute('aria-pressed', 'true');
        await expect(sel('pelaksanaan', 1, 8)).toHaveAttribute('aria-pressed', 'true');
        await expect(sel('evaluasi_pelaporan', 2, 8)).toHaveAttribute('aria-pressed', 'true');
        await expect(page.getByTestId('kelengkapan-matrik')).toHaveAttribute('data-lengkap', 'ya');
        await page.getByTestId('matrik-tahapan').screenshot({ path: `storage/playwright/shots/matrik-grid-${testInfo.project.name}.png` });

        await page.getByTestId('matrik-berubah').getByRole('button', { name: 'Urungkan' }).click();
        await expect(sel('persiapan', 2, 7)).toHaveAttribute('aria-pressed', 'false');
        await expect(sel('persiapan', 1, 7)).toHaveAttribute('aria-pressed', 'true');

        await page.getByRole('button', { name: 'Isi dari jadwal pelaksanaan' }).click();
        await expect(sel('persiapan', 2, 7)).toHaveAttribute('aria-pressed', 'true');
        await page.getByRole('button', { name: 'Simpan', exact: true }).click();
        await expect(page.getByText('Matrik & jadwal tersimpan.')).toBeVisible({ timeout: 30_000 });
        await expect(page.getByTestId('belum-disimpan')).toBeHidden();

        await page.reload();
        await expect(sel('pelaksanaan', 2, 8)).toHaveAttribute('aria-pressed', 'true');
        await expect(page.getByTestId('ringkasan-matrik')).toHaveText('6 tanda bulan');
        await expect(page.getByTestId('kelengkapan-matrik')).toHaveAttribute('data-lengkap', 'ya');
    });

    test('jadwal: ketik uraian, pilih bulan & s.d., isi jadwal baku yang bisa diurungkan, lalu simpan', async ({ page }, testInfo) => {
        await bukaMatrikKegiatanBaru(page, `Kegiatan Jadwal ${testInfo.project.name} ${Date.now()}`);
        const uraian = (baris) => page.locator(`#jadwal-${baris - 1}-uraian`);
        const mulai = (baris) => page.locator(`#jadwal-${baris - 1}-mulai`);
        const sampai = (baris) => page.locator(`#jadwal-${baris - 1}-sampai`);
        const waktu = (baris) => page.getByTestId(`waktu-jadwal-${baris}`);

        await expect(sampai(1)).toBeDisabled();
        await uraian(1).fill('Rapat panitia');
        await mulai(1).selectOption('7');
        await expect(waktu(1)).toHaveText('Juli 2026');
        await expect(sampai(1)).toBeEnabled();
        await expect(sampai(1).locator('option')).toHaveCount(6); // — + Agustus s.d. Desember
        await sampai(1).selectOption('8');
        await expect(waktu(1)).toHaveText('Juli – Agustus 2026');
        await expect(page.getByTestId('ringkasan-jadwal')).toHaveText('1 dari 6 baris terisi');
        await expect(page.getByTestId('belum-disimpan')).toBeVisible();

        // Bulan tanpa uraian ditolak saat disimpan; baris itu bisa dihapus (galatnya ikut hilang).
        await mulai(2).selectOption('8');
        await page.getByRole('button', { name: 'Simpan', exact: true }).click();
        await expect(page.getByTestId('baris-jadwal-2')).toContainText('Isi uraian kegiatan untuk bulan yang sudah dipilih.');
        await page.getByRole('button', { name: 'Hapus baris 2' }).click();
        await expect(page.getByTestId('jadwal-berubah')).toContainText('Baris 2 dihapus.');
        await expect(mulai(2)).toHaveValue('');
        await expect(page.getByTestId('baris-jadwal-2')).not.toContainText('Isi uraian kegiatan');
        await expect(page.getByTestId('ringkasan-jadwal')).toHaveText('1 dari 5 baris terisi');

        // Isi jadwal baku dari jadwal pelaksanaan (Agustus) — bisa diurungkan.
        await page.getByRole('button', { name: 'Isi jadwal baku' }).click();
        await expect(page.getByTestId('jadwal-berubah')).toContainText('Jadwal baku diisi dari jadwal pelaksanaan.');
        await expect(uraian(4)).toHaveValue('Pelaksanaan kegiatan');
        await expect(waktu(1)).toHaveText('Juli 2026');
        await expect(waktu(3)).toHaveText('Juli – Agustus 2026');
        await expect(waktu(6)).toHaveText('Agustus 2026');
        await page.getByTestId('jadwal-kegiatan').screenshot({ path: `storage/playwright/shots/jadwal-kegiatan-${testInfo.project.name}.png` });
        await page.getByTestId('jadwal-berubah').getByRole('button', { name: 'Urungkan' }).click();
        await expect(uraian(1)).toHaveValue('Rapat panitia');
        await expect(uraian(4)).toHaveValue('');

        await page.getByRole('button', { name: 'Isi jadwal baku' }).click();
        await expect(uraian(6)).toHaveValue('Penyusunan dan penyerahan laporan');
        await page.getByRole('button', { name: 'Simpan', exact: true }).click();
        await expect(page.getByText('Matrik & jadwal tersimpan.')).toBeVisible({ timeout: 30_000 });

        await page.reload();
        await expect(uraian(6)).toHaveValue('Penyusunan dan penyerahan laporan');
        await expect(waktu(4)).toHaveText('Agustus 2026');
        await expect(page.getByTestId('kelengkapan-jadwal')).toHaveAttribute('data-lengkap', 'ya');
    });

    test('jadwal: tambah baris, naikkan, hapus lalu urungkan, simpan urutan', async ({ page }, testInfo) => {
        await bukaMatrikKegiatanBaru(page, `Kegiatan Baris ${testInfo.project.name} ${Date.now()}`);
        const uraian = (baris) => page.locator(`#jadwal-${baris - 1}-uraian`);
        const daftar = page.getByTestId('jadwal-kegiatan').locator('li[data-testid^="baris-jadwal-"]');

        await page.getByRole('button', { name: 'Tambah baris' }).click();
        await expect(daftar).toHaveCount(7);
        await expect(uraian(7)).toBeFocused();
        await uraian(7).fill('Rapat evaluasi bersama komite');
        await page.locator('#jadwal-6-mulai').selectOption('9');
        await expect(page.getByTestId('waktu-jadwal-7')).toHaveText('September 2026');
        await expect(page.getByTestId('jumlah-baris-jadwal')).toContainText('7 dari maks. 12 baris');

        await page.getByRole('button', { name: 'Naikkan baris 7' }).click();
        await expect(uraian(6)).toHaveValue('Rapat evaluasi bersama komite');
        await expect(page.getByTestId('waktu-jadwal-6')).toHaveText('September 2026');
        await expect(uraian(7)).toHaveValue('');

        await page.getByRole('button', { name: 'Hapus baris 6' }).click();
        await expect(daftar).toHaveCount(6);
        await expect(page.getByTestId('jadwal-berubah')).toContainText('Baris 6 “Rapat evaluasi bersama komite” dihapus.');
        await page.getByTestId('jadwal-berubah').getByRole('button', { name: 'Urungkan' }).click();
        await expect(daftar).toHaveCount(7);
        await expect(uraian(6)).toHaveValue('Rapat evaluasi bersama komite');

        // Baris kosong terakhir dibuang lalu simpan: jumlah & urutan baris tetap setelah dimuat ulang.
        await page.getByRole('button', { name: 'Hapus baris 7' }).click();
        await expect(daftar).toHaveCount(6);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.getByTestId('jadwal-kegiatan').screenshot({ path: `storage/playwright/shots/jadwal-baris-${testInfo.project.name}.png` });
        await page.getByRole('button', { name: 'Simpan', exact: true }).click();
        await expect(page.getByText('Matrik & jadwal tersimpan.')).toBeVisible({ timeout: 30_000 });

        await page.reload();
        await expect(daftar).toHaveCount(6);
        await expect(uraian(6)).toHaveValue('Rapat evaluasi bersama komite');
        await expect(page.getByTestId('waktu-jadwal-6')).toHaveText('September 2026');
    });

    test('usul jadwal AI: permintaan, pilih versi, pakai lalu urungkan', async ({ page }, testInfo) => {
        await bukaMatrikKegiatanBaru(page, `Kegiatan Usul ${testInfo.project.name} ${Date.now()}`);
        const panel = page.getByTestId('panel-usul-jadwal');

        await page.getByTestId('buka-usul-jadwal').click();
        await expect(panel).toBeVisible();
        await expect(panel.getByTestId('acuan-usul-jadwal')).toContainText('Agustus 2026');
        await expect(panel.getByRole('radio', { name: 'Kegiatan lainnya' })).toBeChecked();

        // Escape menutup panel; membuka lagi mempertahankan permintaan.
        await page.keyboard.press('Escape');
        await expect(panel).toBeHidden();
        await page.getByTestId('buka-usul-jadwal').click();

        await panel.getByText('Workshop / pelatihan guru').click();
        await expect(panel.getByRole('radio', { name: 'Workshop / pelatihan guru' })).toBeChecked();
        await panel.getByRole('button', { name: '+ lebih ringkas' }).click();
        await panel.getByRole('button', { name: '+ persiapan lebih awal' }).click();
        await expect(panel.getByLabel(/Instruksi tambahan/)).toHaveValue('lebih ringkas; persiapan lebih awal');
        await panel.screenshot({ path: `storage/playwright/shots/usul-jadwal-permintaan-${testInfo.project.name}.png` });

        await panel.getByRole('button', { name: 'Buat usulan' }).click();
        await expect(panel.getByTestId('usul-hasil')).toContainText('dengan instruksi “lebih ringkas; persiapan lebih awal”');
        await expect(panel.locator('article[data-testid^="versi-usulan-"]')).toHaveCount(3);
        await expect(panel.getByTestId('versi-usulan-2')).toContainText('5 baris');
        await expect(panel.getByTestId('versi-usulan-2')).toContainText('Juni – Agustus 2026');
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await panel.screenshot({ path: `storage/playwright/shots/usul-jadwal-versi-${testInfo.project.name}.png` });

        await panel.getByRole('button', { name: 'Pakai Versi 2' }).click();
        await expect(panel).toBeHidden();
        await expect(page.getByTestId('jadwal-berubah')).toContainText('Jadwal diganti dengan usulan AI (Versi 2)');
        await expect(page.locator('#jadwal-0-uraian')).toHaveValue('Pembentukan panitia dan penyusunan TOR workshop');
        await expect(page.getByTestId('waktu-jadwal-1')).toHaveText('Juni 2026');
        await expect(page.getByTestId('ringkasan-jadwal')).toHaveText('5 dari 5 baris terisi');
        await expect(page.getByTestId('belum-disimpan')).toBeVisible();

        await page.getByTestId('jadwal-berubah').getByRole('button', { name: 'Urungkan' }).click();
        await expect(page.locator('#jadwal-0-uraian')).toHaveValue('');
        await expect(page.getByTestId('ringkasan-jadwal')).toHaveText('0 dari 6 baris terisi');
    });
});
