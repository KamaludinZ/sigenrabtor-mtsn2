import { expect, test } from '@playwright/test';

test.describe('Dashboard', () => {
    test('halaman utama: ringkasan, pemakaian pagu, status dokumen, kegiatan per leading', async ({ page }, testInfo) => {
        await page.goto('/');
        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.getByRole('heading', { name: 'Dashboard', level: 1 })).toBeVisible();
        await expect(page.getByTestId('cakupan-dashboard')).toContainText('1.6 · Tim Penjamin Mutu');
        await expect(page.getByTestId('ringkasan-monitoring').locator('article')).toHaveCount(4);
        await expect(page.getByTestId('pemakaian-pagu').getByRole('meter')).toHaveCount(2);
        await expect(page.getByTestId('status-dokumen').locator('a[data-status-dokumen]')).toHaveCount(5);

        // Akun leading hanya melihat leading-nya sendiri.
        const baris = page.getByTestId('kegiatan-per-leading').locator('tbody tr');
        await expect(baris).toHaveCount(1);
        await expect(baris.first()).toHaveAttribute('data-leading', '1.6');
        await expect(page.getByRole('link', { name: 'Monitoring Anggaran' })).toHaveCount(0);

        let overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/dashboard-leading-${testInfo.project.name}.png`, fullPage: true });

        await page.getByTestId('status-dokumen').locator('a[data-status-dokumen="draf"]').click();
        await expect(page).toHaveURL(/\/kegiatan\?status=draf/);

        await page.goto('/demo/peran/admin');
        await page.goto('/dashboard');
        await expect(page.getByTestId('cakupan-dashboard')).toContainText('Semua leading');
        expect(await baris.count()).toBeGreaterThan(1);
        await expect(page.getByTestId('kegiatan-per-leading').locator('tbody tr[data-leading="1.6"]')).toBeVisible();
        await expect(page.getByRole('link', { name: 'Monitoring Anggaran' }).last()).toBeVisible();

        overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/dashboard-admin-${testInfo.project.name}.png`, fullPage: true });
    });
    test('bagian pribadi: perlu tindakan, terakhir diubah, aktivitas, pintasan per peran', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/leading');
        await page.goto('/dashboard');
        const tindakan = page.getByTestId('perlu-tindakan');
        await expect(tindakan).toBeVisible();
        await expect(tindakan.getByTestId('tindakan').first()).toHaveAttribute('data-jenis', /^(perbaiki|arsip|ajukan|lengkapi)$/);
        await expect(page.getByTestId('pintasan-buat')).toHaveAttribute('href', /\/kegiatan\/buat$/);
        await expect(page.getByTestId('kegiatan-terakhir').first()).toBeVisible();
        await expect(page.getByTestId('aktivitas').first()).toBeVisible();
        await expect(page.getByTestId('akun-ringkas')).toContainText('Muslimin');
        await page.screenshot({ path: `storage/playwright/shots/dashboard-beranda-${testInfo.project.name}.png`, fullPage: true });

        await tindakan.getByTestId('buka-tindakan').first().click();
        await expect(page).toHaveURL(/\/kegiatan\/\d+\/(edit|pratinjau)/);

        await page.goto('/demo/peran/admin');
        await page.goto('/dashboard');
        await expect(page.getByTestId('pintasan-pemeriksaan')).toBeVisible();
        await expect(page.getByTestId('pintasan-buat')).toHaveCount(0);
        await expect(page.getByTestId('perlu-tindakan').locator('[data-jenis="permohonan"]')).toBeVisible();
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.goto('/demo/peran/leading');
    });

    test('panduan langkah: persiapan admin & tahapan kegiatan leading', async ({ page }, testInfo) => {
        // Leading: delapan tahap, tepat satu tahap aktif terbuka beserta tombolnya; tahap lain bisa dibuka.
        await page.goto('/demo/peran/leading');
        await page.goto('/dashboard');
        const panduan = page.getByTestId('panduan-leading');
        await expect(panduan.getByTestId('tahap-panduan')).toHaveCount(8);
        const aktif = panduan.locator('[data-testid="tahap-panduan"][aria-current="step"]');
        await expect(aktif).toHaveCount(1);
        await expect(aktif.getByTestId('isi-tahap')).toBeVisible();
        await expect(aktif.getByTestId('buka-tahap')).toBeVisible();
        const pertama = panduan.getByTestId('tahap-panduan').first();
        if ((await pertama.getAttribute('aria-current')) === null) {
            await expect(pertama.getByTestId('isi-tahap')).toBeHidden();
            await pertama.getByTestId('judul-tahap').click();
            await expect(pertama.getByTestId('isi-tahap')).toBeVisible();
        }

        // Ganti kegiatan yang dipandu.
        const pilih = panduan.getByTestId('pilih-kegiatan-panduan');
        const nilai = await pilih.locator('option').evaluateAll((opsi) => opsi.map((o) => o.value));
        await pilih.selectOption(nilai[nilai.length - 1]);
        await expect(panduan).toHaveAttribute('data-fokus', nilai[nilai.length - 1]);
        await panduan.screenshot({ path: `storage/playwright/shots/panduan-leading-${testInfo.project.name}.png` });

        // Admin: langkah persiapan, langkah berikutnya bertautan ke tempat mengaturnya.
        await page.goto('/demo/peran/admin');
        await page.goto('/dashboard');
        const persiapan = page.getByTestId('panduan-admin');
        await expect(persiapan.getByTestId('langkah-admin')).toHaveCount(11);
        const berikutnya = persiapan.locator('[data-testid="langkah-admin"][data-status="sekarang"]');
        if ((await persiapan.getAttribute('data-lengkap')) === '0') {
            await expect(berikutnya).toHaveCount(1);
            await expect(berikutnya.getByTestId('buka-langkah')).toBeVisible();
        }
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await persiapan.screenshot({ path: `storage/playwright/shots/panduan-admin-${testInfo.project.name}.png` });
    });
});

