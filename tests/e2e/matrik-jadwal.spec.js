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

        // Klik (mouse) / ketuk (layar sentuh) menandai satu bulan seketika.
        await tekan(sel('persiapan', 1, 7));
        await expect(sel('persiapan', 1, 7)).toHaveAttribute('aria-pressed', 'true');
        await expect(page.getByTestId('ringkasan-matrik')).toHaveText('1 tanda bulan');
        await expect(page.getByTestId('belum-disimpan')).toBeVisible();

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
        }

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
        await expect(page.getByTestId('belum-disimpan')).toHaveCount(0);

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

        // Bulan tanpa uraian ditolak saat disimpan; baris bisa dikosongkan.
        await mulai(2).selectOption('8');
        await page.getByRole('button', { name: 'Simpan', exact: true }).click();
        await expect(page.getByTestId('baris-jadwal-2')).toContainText('Isi uraian kegiatan untuk bulan yang sudah dipilih.');
        await page.getByRole('button', { name: 'Kosongkan baris 2' }).click();
        await expect(mulai(2)).toHaveValue('');
        await expect(page.getByTestId('baris-jadwal-2')).not.toContainText('Isi uraian kegiatan');

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
});
