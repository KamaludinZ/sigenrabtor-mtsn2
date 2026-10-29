import { expect, test } from '@playwright/test';

// Server E2E berjalan tanpa wajib masuk (SIRABTOR_WAJIB_MASUK=false); jawaban 401/403 aksi Livewire ditiru lewat page.route
// untuk menguji penjaga sesi klien (resources/js/sesi.js). Pengalihan server diuji di tests/Feature/Akun/ProteksiRuteTest.php.
test.describe('Proteksi rute', () => {
    test('aksi Livewire 401 → Masuk lalu kembali ke halaman asal; 403 → notifikasi; halaman galat', async ({ page }) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/akun');
        await expect(page.getByTestId('ubah-profil')).toBeVisible();

        await page.route('**/livewire-*/update', (route) => route.fulfill({ status: 403, contentType: 'text/html', body: '<p>Forbidden</p>' }));
        await page.getByTestId('ubah-profil').click();
        await expect(page.locator('[data-testid="toast"]').filter({ hasText: 'Anda tidak punya akses untuk tindakan ini.' })).toBeVisible();
        await page.unroute('**/livewire-*/update');

        await page.route('**/livewire-*/update', (route) => route.fulfill({ status: 401, contentType: 'application/json', body: JSON.stringify({ pesan: 'Sesi berakhir', masuk: '/masuk' }) }));
        await page.getByTestId('ubah-profil').click();
        await expect(page).toHaveURL(/\/masuk\?sesi=berakhir&kembali=%2Fakun$/);
        await page.unroute('**/livewire-*/update');
        await expect(page.getByTestId('sesi-berakhir')).toBeVisible();

        // Masuk kembali → diteruskan ke halaman asal.
        await page.getByTestId('akun-demo').locator('[data-peran="leading"]').click();
        await expect(page).toHaveURL(/\/akun$/);

        // Halaman khusus admin bagi akun leading: halaman galat berbahasa Indonesia.
        await page.goto('/pengaturan');
        await expect(page.getByTestId('halaman-galat')).toHaveAttribute('data-kode', '403');
        await expect(page.getByTestId('pesan-galat')).toContainText('Halaman ini khusus admin.');
        await page.getByTestId('galat-dashboard').click();
        await expect(page).toHaveURL(/\/dashboard$/);
        await page.goto('/halaman-yang-tidak-ada');
        await expect(page.getByTestId('halaman-galat')).toHaveAttribute('data-kode', '404');
    });
});
