import { expect, test } from '@playwright/test';

test.describe('Format mata uang di klien', () => {
    test('helper $rupiah/$angka sama dengan format server', async ({ page }) => {
        await page.goto('/kegiatan/1/rab');
        const hasil = await page.evaluate(() => {
            const f = window.SIGENRABTOR;
            return {
                rupiah: f.rupiah(10000000),
                negatif: f.rupiah(-600000),
                angka: f.angka(1250000),
                ketik: f.rapikanKetikan('Rp 0010000000'),
                jumlah: [f.jumlahRab('1,5', '40.000'), f.jumlahRab('0,29', '100'), f.jumlahRab('1.000', '600'), f.jumlahRab('dua', '1')],
            };
        });
        expect(hasil).toEqual({
            rupiah: 'Rp10.000.000',
            negatif: '-Rp600.000',
            angka: '1.250.000',
            ketik: '10.000.000',
            jumlah: [60000, 29, 600000, null],
        });
    });
});
