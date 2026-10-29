import { expect, test } from '@playwright/test';

test.describe('Masuk & Akun', () => {
    test('masuk: galat surel, akun contoh, akun saya, keluar', async ({ page }, testInfo) => {
        await page.goto('/masuk');
        await expect(page.getByRole('heading', { name: 'Masuk', level: 1 })).toBeVisible();
        const mobile = testInfo.project.name === 'mobile';
        await expect(page.getByTestId('panel-merek')).toBeVisible({ visible: ! mobile });
        await page.screenshot({ path: `storage/playwright/shots/masuk-${testInfo.project.name}.png`, fullPage: true });

        await page.getByTestId('tombol-masuk').click();
        await expect(page.getByTestId('galat-surel')).toContainText('Isi surel');
        // Surel unik per jalankan: percobaan gagal dibatasi per surel (cache server bertahan antarjalankan).
        await page.getByTestId('isian-surel').fill(`tidak-${testInfo.project.name}-${Date.now()}@ada.id`);
        await page.getByTestId('isian-sandi').fill('rahasia');
        await page.getByTestId('lihat-sandi').click();
        await expect(page.getByTestId('isian-sandi')).toHaveAttribute('type', 'text');
        await page.getByTestId('tombol-masuk').click();
        await expect(page.getByTestId('galat-surel')).toContainText('belum terdaftar');

        await page.getByTestId('akun-demo').locator('[data-peran="leading"]').click();
        await expect(page).toHaveURL(/\/dashboard/);
        await expect(page.locator('[data-testid="toast"]').filter({ hasText: 'Selamat datang kembali, Muslimin, M.Pd.' })).toBeVisible();

        await page.goto('/akun');
        await expect(page.getByTestId('profil-akun')).toContainText('muslimin@example.com');
        await expect(page.getByTestId('leading-akun')).toContainText('1.6');
        await page.getByTestId('tab-akun').locator('[data-tab="aktivitas"]').click();
        await expect(page.getByTestId('riwayat-masuk').first()).toBeVisible();
        await page.screenshot({ path: `storage/playwright/shots/akun-saya-${testInfo.project.name}.png`, fullPage: true });
        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(lebar).toBeLessThanOrEqual(0);

        await page.locator('header').getByRole('button', { expanded: false }).last().click();
        await page.getByTestId('menu-keluar').click();
        await expect(page).toHaveURL(/\/masuk/);
        await expect(page.locator('[data-testid="toast"]').filter({ hasText: 'Anda sudah keluar.' })).toBeVisible();
    });
    test('form masuk: format surel saat blur, Caps Lock, ingat surel, jeda percobaan', async ({ page }, testInfo) => {
        await page.goto('/masuk');
        await page.getByTestId('isian-surel').fill('bukan-surel');
        await page.getByTestId('isian-surel').blur();
        await expect(page.getByTestId('galat-surel')).toContainText('Surel tidak sah');

        const sandi = page.getByTestId('isian-sandi');
        await sandi.evaluate((el) => el.dispatchEvent(new KeyboardEvent('keyup', { key: 'A', modifierCapsLock: true, bubbles: true })));
        await expect(page.getByTestId('capslock-aktif')).toBeVisible();
        await sandi.evaluate((el) => el.dispatchEvent(new KeyboardEvent('keyup', { key: 'a', bubbles: true })));
        await expect(page.getByTestId('capslock-aktif')).toBeHidden();

        // Lima percobaan gagal untuk satu surel → peringatan sisa percobaan lalu jeda dengan hitung mundur.
        const surel = `jeda-${testInfo.project.name}-${Date.now()}@ada.id`;
        await page.getByTestId('isian-surel').fill(surel);
        for (let i = 1; i <= 5; i++) {
            await sandi.fill('salah');
            await page.getByTestId('tombol-masuk').click();
            if (i === 3) {
                await expect(page.getByTestId('sisa-percobaan')).toContainText('Sisa 2 percobaan');
            }
            if (i < 5) {
                await expect(sandi).toHaveValue('');
            }
        }
        await expect(page.getByTestId('jeda-masuk')).toBeVisible();
        await expect(page.getByTestId('jeda-masuk')).toContainText(/Coba lagi dalam \d+ detik/);
        await expect(page.getByTestId('tombol-masuk')).toBeHidden();

        // Ingat saya: surel diisikan kembali di kunjungan berikutnya dan fokus pindah ke sandi.
        await page.goto('/masuk');
        await page.getByTestId('isian-surel').fill('muslimin@example.com');
        await page.getByTestId('ingat-saya').check();
        await sandi.fill('apa-saja');
        await sandi.press('Enter');
        await expect(page).toHaveURL(/\/dashboard/);
        await page.goto('/masuk');
        await expect(page.getByTestId('isian-surel')).toHaveValue('muslimin@example.com');
        await expect(page.getByTestId('ingat-saya')).toBeChecked();
        await expect(sandi).toBeFocused();
        await page.screenshot({ path: `storage/playwright/shots/masuk-ingat-${testInfo.project.name}.png`, fullPage: true });

        // Dari halaman Daftar: ?surel= mengisi surel.
        await page.goto('/masuk?surel=Nur.Laili@Example.com');
        await expect(page.getByTestId('isian-surel')).toHaveValue('nur.laili@example.com');
    });
    test('sesi akun: nama, surel & leading akun yang masuk, kartu sudah masuk, keluar', async ({ page }, testInfo) => {
        await page.goto('/masuk');
        await page.getByTestId('isian-surel').fill('siti.rohmah@example.com');
        await page.getByTestId('isian-sandi').fill('apa-saja');
        await page.getByTestId('tombol-masuk').click();
        await expect(page).toHaveURL(/\/dashboard/);
        await expect(page.getByTestId('sapaan-pengguna')).toContainText('Siti Rohmah, M.Pd.');
        await expect(page.locator('[data-testid="toast"]').filter({ hasText: 'Selamat datang kembali, Siti Rohmah, M.Pd.' })).toBeVisible();
        if (testInfo.project.name !== 'mobile') {
            await expect(page.getByTestId('nama-pengguna')).toHaveText('Siti Rohmah, M.Pd.');
        }
        await page.locator('header').getByRole('button', { expanded: false }).last().click();
        await expect(page.getByTestId('surel-pengguna')).toHaveText('siti.rohmah@example.com');
        await expect(page.getByTestId('waktu-masuk')).toContainText('Masuk ');

        await page.goto('/akun');
        await expect(page.getByTestId('profil-akun')).toContainText('siti.rohmah@example.com');
        await expect(page.getByTestId('leading-akun')).toContainText('1.1');
        // Data profil: penanda tangan tercetak per leading, kegiatan & dokumen buatan, kuota AI, sesi ini.
        await expect(page.getByTestId('tercetak-leading')).toHaveAttribute('data-leading', '1.1');
        await expect(page.getByTestId('tercetak-leading')).toHaveAttribute('data-sumber', /^(akun|pejabat|kosong)$/);
        await expect(page.getByTestId('kegiatan-buatan')).toHaveAttribute('data-jumlah', /^\d+$/);
        await expect(page.getByTestId('kuota-akun')).toContainText('permintaan AI hari ini');
        await expect(page.getByTestId('sesi-ini')).toContainText('Masuk ');
        await page.screenshot({ path: `storage/playwright/shots/profil-data-${testInfo.project.name}.png`, fullPage: true });
        const lebarProfil = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(lebarProfil).toBeLessThanOrEqual(0);

        await page.goto('/masuk');
        await expect(page.getByTestId('sudah-masuk')).toHaveAttribute('data-akun', '3');
        await page.screenshot({ path: `storage/playwright/shots/sudah-masuk-${testInfo.project.name}.png`, fullPage: true });
        await page.getByTestId('keluar-dari-masuk').click();
        await expect(page.locator('[data-testid="toast"]').filter({ hasText: 'Anda sudah keluar.' })).toBeVisible();
        await expect(page.getByTestId('sudah-masuk')).toHaveCount(0);
    });
});
