import { expect, test } from '@playwright/test';

test.describe('Riwayat Dokumen', () => {
    test('layout: ringkasan, kelompok per tahun, tabel/kartu (kronologis)', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/dokumen?tampilan=kronologis');
        await expect(page.getByRole('heading', { name: 'Riwayat Dokumen', level: 1 })).toBeVisible();
        await expect(page.getByTestId('ringkasan-riwayat').locator('[data-ringkasan="arsip"]')).toContainText('1');

        const tahun = page.getByTestId('riwayat-tahun');
        await expect(tahun).toHaveCount(2);
        await expect(tahun.first()).toHaveAttribute('data-tahun', '2026');
        const mobile = testInfo.project.name === 'mobile';
        const butir = page.getByTestId(mobile ? 'kartu-riwayat' : 'dokumen-riwayat');
        await expect(butir.first()).toBeVisible();
        await expect(page.getByTestId(mobile ? 'dokumen-riwayat' : 'kartu-riwayat').first()).toBeHidden();
        await expect(page.locator(`[data-testid="${mobile ? 'kartu-riwayat' : 'dokumen-riwayat'}"][data-id="rd-2025-5"]`)).toContainText(mobile ? 'Lomba Kreativitas Siswa' : 'KAK/031/2025');

        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(lebar).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/riwayat-dokumen-${testInfo.project.name}.png`, fullPage: true });

    });

    test('tampilan per kegiatan & kronologis', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/dokumen');
        await expect(page.getByTestId('tampilan-riwayat')).toHaveAttribute('data-tampilan', 'kegiatan');
        const lomba = page.getByTestId('kegiatan-riwayat').filter({ hasText: 'Lomba Kreativitas Siswa Akhir Tahun' });
        await expect(lomba.getByTestId('kegiatan-tidak-tersimpan')).toBeVisible();
        await expect(lomba.getByTestId('dokumen-kegiatan')).toHaveAttribute('data-versi', '2');
        await expect(lomba.getByTestId('versi-sebelumnya')).toBeHidden();
        await lomba.getByTestId('versi-lama').locator('summary').click();
        await expect(lomba.getByTestId('versi-sebelumnya')).toHaveAttribute('data-versi', '1');
        await expect(page.getByTestId('kegiatan-riwayat').filter({ hasText: 'Supervisi Akademik' }).getByTestId('buka-kegiatan-riwayat')).toHaveAttribute('href', /\/kegiatan\/2\/edit$/);
        await page.screenshot({ path: `storage/playwright/shots/riwayat-per-kegiatan-${testInfo.project.name}.png`, fullPage: true });

        await page.getByTestId('tampilan-riwayat').locator('[data-pilih-tampilan="kronologis"]').click();
        await expect(page).toHaveURL(/tampilan=kronologis/);
        await page.getByTestId('urut-riwayat').selectOption('terlama');
        await expect(page).toHaveURL(/urut=terlama/);
        await expect(page.getByTestId('riwayat-tahun').first()).toHaveAttribute('data-tahun', '2025');
        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(lebar).toBeLessThanOrEqual(0);
    });

    test('pencarian kata kunci: sorotan, hitungan, pintasan, hapus', async ({ page }) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/dokumen');
        const cari = page.getByTestId('cari-riwayat');

        await page.locator('body').click({ position: { x: 5, y: 5 } });
        await page.keyboard.press('/');
        await expect(cari).toBeFocused();
        await cari.pressSequentially('lomba', { delay: 20 });
        await expect(page).toHaveURL(/cari=lomba/);
        await expect(page.getByTestId('cari-riwayat-hasil')).toContainText('2 dokumen cocok dengan “lomba” dari 9');
        await expect(page.getByTestId('kegiatan-riwayat')).toHaveCount(1);
        await expect(page.getByTestId('kegiatan-riwayat').locator('mark').first()).toHaveText(/lomba/i);

        await cari.press('Escape');
        await expect(page).not.toHaveURL(/cari=/);
        await expect(page.getByTestId('kegiatan-riwayat')).toHaveCount(4);

        await cari.fill('tidak ada sama sekali');
        await expect(page.getByTestId('riwayat-tanpa-hasil')).toBeVisible();
        await page.getByTestId('cari-riwayat-hapus').click();
        await expect(page.getByTestId('riwayat-tanpa-hasil')).toBeHidden();
        await expect(cari).toHaveValue('');

        await page.goto('/dokumen?tampilan=kronologis&cari=KAK%2F031');
        await expect(page.getByTestId('riwayat-tahun')).toHaveCount(1);
        await expect(page.getByTestId('cari-riwayat-hasil')).toContainText('1 dokumen cocok');
    });

    test('saringan jenis, format, rentang tanggal: chip, peringatan, pintasan, hapus', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/dokumen?tampilan=kronologis');
        const mobile = testInfo.project.name === 'mobile';
        const butir = (id) => page.locator(`[data-testid="${mobile ? 'kartu-riwayat' : 'dokumen-riwayat'}"][data-id="${id}"]`);
        const chip = (kunci) => page.locator(`[data-testid="chip-saringan"][data-kunci="${kunci}"]`);
        await expect(butir('rd-2026-6')).toBeVisible();

        if (mobile) {
            await expect(page.getByTestId('saring-jenis')).toBeHidden();
            await page.getByTestId('buka-saringan-riwayat').click();
            await expect(page.getByTestId('buka-saringan-riwayat')).toHaveAttribute('aria-expanded', 'true');
        }
        await page.getByTestId('saring-format').selectOption('docx');
        await expect(page).toHaveURL(/format=docx/);
        await expect(chip('format')).toContainText('Word');
        await expect(butir('rd-2026-5')).toBeVisible();
        await expect(butir('rd-2026-6')).toHaveCount(0);
        await chip('format').click();
        await expect(page).not.toHaveURL(/format=/);

        await page.getByTestId('saring-jenis').selectOption('RAB');
        await page.getByTestId('saring-dari').fill('2026-02-01');
        await page.getByTestId('saring-dari').blur();
        await expect(page).toHaveURL(/dari=2026-02-01/);
        await page.getByTestId('saring-sampai').fill('2026-02-28');
        await page.getByTestId('saring-sampai').blur();
        await expect(chip('tanggal')).toContainText('1 Februari 2026 – 28 Februari 2026');
        await expect(chip('jenis')).toContainText('RAB');
        await expect(butir('rd-2026-1')).toBeVisible();
        await expect(butir('rd-2026-6')).toHaveCount(0);
        await page.screenshot({ path: `storage/playwright/shots/riwayat-saringan-${testInfo.project.name}.png`, fullPage: true });

        await page.goto('/dokumen?tampilan=kronologis&dari=2026-03-01&sampai=2026-02-01');
        await expect(page.getByTestId('rentang-terbalik')).toBeVisible();
        await expect(chip('tanggal')).toHaveCount(0);
        await expect(butir('rd-2026-7')).toBeVisible();
        if (mobile) {
            await expect(page.getByTestId('saring-dari')).toBeVisible();
        }
        await page.getByTestId('pintasan-ta').click();
        await expect(page.getByTestId('rentang-terbalik')).toBeHidden();
        await expect(chip('tanggal')).toContainText('1 Januari 2026 – 31 Desember 2026');
        await expect(butir('rd-2025-5')).toHaveCount(0);

        await page.getByTestId('saring-jenis').selectOption('KAK');
        await expect(page.getByTestId('riwayat-tanpa-hasil')).toBeVisible();
        await page.getByTestId('riwayat-tanpa-hasil').getByRole('button').click();
        await expect(page.getByTestId('chip-saringan-riwayat')).toBeHidden();
        await expect(page).not.toHaveURL(/jenis=|dari=|sampai=/);
        await expect(butir('rd-2025-5')).toBeVisible();
        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(lebar).toBeLessThanOrEqual(0);
    });
});
