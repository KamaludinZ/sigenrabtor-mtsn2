import { expect, test } from '@playwright/test';

test.describe('Monitoring Anggaran', () => {
    test('layout: khusus admin, saringan di URL, kartu ringkasan, kerangka rekap', async ({ page }, testInfo) => {
        const ditolak = await page.goto('/monitoring');
        expect(ditolak.status()).toBe(403);

        await page.goto('/demo/peran/admin');
        await page.goto('/monitoring');
        await expect(page.getByRole('heading', { name: 'Monitoring Anggaran', level: 1 })).toBeVisible();
        const ringkasan = page.getByTestId('ringkasan-monitoring');
        await expect(ringkasan.locator('article')).toHaveCount(4);
        await expect(page.getByTestId('kartu-pagu')).toContainText('DIPA');
        await expect(page.getByTestId('kartu-pagu')).toContainText('Komite');
        await expect(page.locator('section[data-testid^="bagian-monitoring-"]')).toHaveCount(4);

        await page.getByTestId('saringan-monitoring').getByLabel('Sumber dana').selectOption('Komite');
        await expect(page).toHaveURL(/sumber=Komite/);
        await expect(page.getByTestId('saringan-aktif')).toContainText('Dana Komite');
        await expect(page.getByTestId('kartu-pagu').locator('dl > div')).toHaveCount(1);

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/monitoring-${testInfo.project.name}.png`, fullPage: true });

        await page.reload();
        await expect(page.getByTestId('saringan-aktif')).toContainText('Dana Komite');
        await page.getByRole('button', { name: 'Hapus semua' }).click();
        await expect(page.getByTestId('saringan-aktif')).toHaveCount(0);
    });

    test('kartu perbandingan pagu DIPA & Komite: porsi, kartu per sumber, saringan sumber', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/monitoring');
        const kartu = page.getByTestId('perbandingan-sumber');
        await expect(kartu.getByRole('heading', { name: 'Perbandingan pagu DIPA & Komite' })).toBeVisible();
        await expect(kartu.getByRole('img', { name: /^Porsi pagu: DIPA [\d,]+% .*Komite [\d,]+%/ })).toBeVisible();
        await expect(kartu.locator('article[data-testid^="perbandingan-"]')).toHaveCount(2);
        await expect(kartu.getByTestId('perbandingan-dipa').getByRole('meter')).toBeVisible();
        await expect(kartu.getByTestId('perbandingan-komite').locator('li[data-kondisi]')).toHaveCount(5);

        // Porsi kedua sumber selalu berjumlah 100%.
        const lebar = await kartu.getByTestId('porsi-pagu').locator('[data-porsi]').evaluateAll((el) => el.map((e) => parseFloat(e.style.width)));
        expect(lebar.reduce((a, b) => a + b, 0)).toBeCloseTo(100, 5);

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await kartu.screenshot({ path: `storage/playwright/shots/monitoring-perbandingan-${testInfo.project.name}.png` });

        await page.getByTestId('saringan-monitoring').getByLabel('Sumber dana').selectOption('DIPA');
        await expect(kartu.getByTestId('perbandingan-disaring')).toBeVisible();
        await expect(kartu.locator('article[data-testid^="perbandingan-"]')).toHaveCount(1);
        await expect(kartu.getByTestId('porsi-pagu')).toHaveCount(0);
        await kartu.getByRole('button', { name: 'Bandingkan DIPA & Komite' }).click();
        await expect(page).not.toHaveURL(/sumber=/);
        await expect(kartu.locator('article[data-testid^="perbandingan-"]')).toHaveCount(2);
    });

    test('tabel total per leading: DIPA & Komite terpisah, baris jumlah, kartu di layar sempit', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/monitoring');
        const bagian = page.getByTestId('bagian-monitoring-leading');
        await expect(bagian.getByRole('heading', { name: 'Total per leading' })).toBeVisible();
        const lebar = testInfo.project.name === 'desktop';
        if (lebar) {
            const tabel = bagian.getByRole('table');
            await expect(tabel.locator('thead th[scope="colgroup"]')).toHaveCount(3);
            expect(await tabel.locator('tbody tr').count()).toBeGreaterThanOrEqual(8);
            await expect(tabel.locator('tbody tr[data-leading="1.6"]')).toContainText('Tim Penjamin Mutu');
            await expect(bagian.getByTestId('jumlah-per-leading')).toBeVisible();
        } else {
            await expect(bagian.getByRole('table')).toBeHidden();
            await expect(bagian.locator('li[data-leading-kartu="1.6"]')).toContainText('Tim Penjamin Mutu');
            await expect(bagian.getByTestId('jumlah-per-leading-kartu')).toBeVisible();
        }

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await bagian.screenshot({ path: `storage/playwright/shots/monitoring-leading-${testInfo.project.name}.png` });

        await page.getByTestId('saringan-monitoring').getByLabel('Sumber dana').selectOption('Komite');
        await expect(bagian).toContainText('sumber dana Komite');
        if (lebar) {
            await expect(bagian.locator('thead th[scope="colgroup"]')).toHaveCount(1);
        }
        await page.getByTestId('saringan-monitoring').getByLabel('Leading').selectOption('1.6');
        await expect(page).toHaveURL(/leading=1\.6/);
        await expect(bagian.getByTestId('jumlah-leading')).toHaveText('1 dari 1 leading punya kegiatan');
    });

    test('rincian per kegiatan: urut dari total terbesar, ganti urutan lewat judul & pilihan, kartu di layar sempit', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/monitoring');
        const bagian = page.getByTestId('bagian-monitoring-kegiatan');
        await expect(bagian.getByRole('heading', { name: 'Rincian per kegiatan' })).toBeVisible();
        await expect(bagian.getByTestId('posisi-rincian')).toContainText('Menampilkan 1–');
        const lebar = testInfo.project.name === 'desktop';
        if (lebar) {
            const tabel = bagian.getByTestId('tabel-rincian');
            await expect(tabel.locator('th[aria-sort="descending"]')).toHaveText(/Total RAB/);
            await expect(tabel.locator('tbody[data-kegiatan="3"] td[data-rab]')).toHaveCount(2);
            await tabel.getByRole('button', { name: 'Total RAB' }).click();
            await expect(page).toHaveURL(/arah=asc/);
            await expect(tabel.locator('th[aria-sort="ascending"]')).toHaveText(/Total RAB/);
        } else {
            await expect(bagian.getByTestId('tabel-rincian')).toBeHidden();
            await expect(bagian.locator('li[data-kegiatan-kartu="3"] a[href*="/rab"]')).toHaveCount(2);
        }

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await bagian.screenshot({ path: `storage/playwright/shots/monitoring-rincian-${testInfo.project.name}.png` });

        await bagian.getByTestId('urut-rincian').selectOption('nama');
        await expect(page).toHaveURL(/urut=nama/);
        await expect(page).toHaveURL(/arah=asc/);
        await page.reload();
        await expect(bagian.getByTestId('urut-rincian')).toHaveValue('nama');
    });

    test('pengurutan tabel per leading: judul kolom & pilihan Urutkan, tersimpan di URL', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/monitoring');
        const bagian = page.getByTestId('bagian-monitoring-leading');
        const lebar = testInfo.project.name === 'desktop';
        if (lebar) {
            const tabel = bagian.getByRole('table');
            await expect(tabel.locator('th[aria-sort="ascending"]')).toHaveText(/Leading/);
            await tabel.locator('thead th').filter({ hasText: 'Selisih' }).nth(1).getByRole('button').click();
            await expect(page).toHaveURL(/urut_leading=komite_selisih/);
            await expect(tabel.locator('th[aria-sort="ascending"]')).toHaveCount(1);
            await expect(tabel.locator('tbody tr').first()).toHaveAttribute('data-leading', '1.6');
            await bagian.screenshot({ path: `storage/playwright/shots/monitoring-leading-urut-${testInfo.project.name}.png` });
            await tabel.locator('thead th').filter({ hasText: 'Kegiatan' }).getByRole('button').click();
            await expect(page).toHaveURL(/urut_leading=jumlah/);
            await expect(page).toHaveURL(/arah_leading=desc/);
        }
        await bagian.getByTestId('urut-leading').selectOption('semua_total');
        await expect(page).toHaveURL(/urut_leading=semua_total/);
        await expect(bagian.locator(lebar ? 'tbody tr' : 'li[data-leading-kartu]').first()).toHaveAttribute(lebar ? 'data-leading' : 'data-leading-kartu', '1.6');
        await page.reload();
        await expect(bagian.getByTestId('urut-leading')).toHaveValue('semua_total');
        await page.getByTestId('saringan-monitoring').getByLabel('Sumber dana').selectOption('DIPA');
        await expect(bagian.getByTestId('urut-leading')).toHaveValue('kode');
        await expect(page).not.toHaveURL(/urut_leading=/);
    });

    test('rekap per kelompok belanja: legenda, batang per sumber dana dengan tooltip, tabel, kode akun', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/monitoring');
        const bagian = page.getByTestId('bagian-monitoring-kelompok');
        await expect(bagian.getByRole('heading', { name: 'Rekap per kelompok belanja' })).toBeVisible();
        await expect(bagian.locator('li[data-kelompok-legenda]')).toHaveCount(3);
        await expect(bagian.getByTestId('batang-gabungan')).toBeVisible();
        await expect(bagian.getByTestId('tabel-monitoring-kelompok').locator('tbody tr')).toHaveCount(3);
        await expect(bagian.getByTestId('kode-akun-monitoring').locator('[data-kode-akun]').first()).toBeVisible();

        await bagian.getByTestId('batang-komite').getByLabel(/^Jasa Rp/).hover();
        await expect(bagian.getByRole('tooltip')).toContainText('Komite · Jasa');
        await page.mouse.move(0, 0);
        await expect(bagian.getByRole('tooltip')).toBeHidden();

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await bagian.screenshot({ path: `storage/playwright/shots/monitoring-kelompok-${testInfo.project.name}.png` });

        await page.getByTestId('saringan-monitoring').getByLabel('Sumber dana').selectOption('Komite');
        await expect(bagian.getByTestId('batang-gabungan')).toHaveCount(0);
        await expect(bagian.getByTestId('batang-komite')).toBeVisible();
    });

    test('rekap per operator: pembuat kegiatan, jumlah kegiatan per status, total RAB, perubahan terakhir', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/monitoring');
        const bagian = page.getByTestId('bagian-monitoring-operator');
        await expect(bagian.getByRole('heading', { name: 'Rekap per operator' })).toBeVisible();
        await expect(bagian.locator('li[data-operator="1"]')).toContainText('Muslimin, M.Pd.');
        await expect(bagian.locator('li[data-operator="2"]')).toContainText('Koordinator PUSKOM');
        await expect(bagian.locator('li[data-operator="2"]')).toContainText('melebihi pagu');
        await expect(bagian.getByTestId('jumlah-operator')).toContainText('operator');
        await expect(page.getByText('Segera disiapkan')).toHaveCount(0);

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await bagian.screenshot({ path: `storage/playwright/shots/monitoring-operator-${testInfo.project.name}.png` });

        await page.getByTestId('saringan-monitoring').getByLabel('Status dokumen').selectOption('final');
        await expect(bagian.getByTestId('operator-kosong')).toBeVisible();
    });

    test('saringan tahun anggaran & rentang tanggal pelaksanaan: tersimpan di URL, chip, rentang terbalik', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/monitoring');
        const saringan = page.getByTestId('saringan-monitoring');
        await expect(saringan.getByLabel('Tahun anggaran')).toHaveValue('');

        await saringan.getByLabel('Dari tanggal').fill('2026-07-01');
        await saringan.getByLabel('Sampai tanggal').fill('2026-07-31');
        await expect(page).toHaveURL(/dari=2026-07-01/);
        await expect(page).toHaveURL(/sampai=2026-07-31/);
        await expect(page.getByTestId('saringan-aktif')).toContainText('Pelaksanaan 1 Juli 2026 – 31 Juli 2026');
        await expect(page.getByTestId('bagian-monitoring-kegiatan')).toContainText('Workshop Penyusunan Kurikulum');
        await expect(page.getByTestId('bagian-monitoring-kegiatan')).not.toContainText('Review RKJM');

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await saringan.screenshot({ path: `storage/playwright/shots/monitoring-saringan-${testInfo.project.name}.png` });

        await saringan.getByLabel('Dari tanggal').fill('2026-09-01');
        await expect(saringan.getByTestId('rentang-terbalik')).toBeVisible();
        await saringan.getByRole('button', { name: 'Kosongkan tanggal' }).click();
        await expect(page).not.toHaveURL(/dari=/);
        await expect(saringan.getByLabel('Dari tanggal')).toHaveValue('');

        await saringan.getByLabel('Tahun anggaran').selectOption('2025');
        await expect(page).toHaveURL(/tahun=2025/);
        await expect(page.getByTestId('tahun-monitoring')).toContainText('TA 2025');
        await expect(page.getByTestId('tahun-bukan-aktif')).toBeVisible();
        await expect(page.getByTestId('rincian-kosong')).toBeVisible();
        await page.reload();
        await expect(saringan.getByLabel('Tahun anggaran')).toHaveValue('2025');
        await page.getByTestId('saringan-aktif').getByRole('button', { name: 'Lepas saringan TA 2025' }).click();
        await expect(page).not.toHaveURL(/tahun=/);
        await expect(page.getByTestId('tahun-monitoring')).toContainText('TA 2026');
    });
});
