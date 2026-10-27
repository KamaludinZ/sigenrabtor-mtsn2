import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';

// Kasus status pagu bersama: juga diuji di tests/Feature/Rab/TotalLokalTest.php terhadap App\Support\Rab\StatusPagu.
const KASUS_STATUS_PAGU = JSON.parse(readFileSync(new URL('../fixtures/status-pagu.json', import.meta.url), 'utf-8'));
// Kasus status termin bersama: juga diuji di tests/Feature/Rab/TabelTerminTest.php terhadap MekanismePembayaran.
const KASUS_STATUS_TERMIN = JSON.parse(readFileSync(new URL('../fixtures/status-termin.json', import.meta.url), 'utf-8'));
// Tanggal Indonesia bersama: juga diuji di tests/Feature/TanggalIndonesiaTest.php terhadap Format::tanggalPanjang().
const KASUS_TANGGAL = JSON.parse(readFileSync(new URL('../fixtures/tanggal.json', import.meta.url), 'utf-8'));
// Ringkasan matrik bersama: juga diuji di tests/Feature/Kegiatan/MatrikJadwalTest.php terhadap MatrikTahapan.
const KASUS_RINGKASAN_MATRIK = JSON.parse(readFileSync(new URL('../fixtures/ringkasan-matrik.json', import.meta.url), 'utf-8'));
// Konsistensi bulan bersama: juga diuji di tests/Feature/Kegiatan/KonsistensiBulanTest.php terhadap KonsistensiBulan.
const KASUS_KONSISTENSI = JSON.parse(readFileSync(new URL('../fixtures/konsistensi-bulan.json', import.meta.url), 'utf-8'));
// NIP bersama: juga diuji di tests/Feature/Pengaturan/NipTest.php terhadap App\Support\Pengaturan\Nip.
const KASUS_NIP = JSON.parse(readFileSync(new URL('../fixtures/nip.json', import.meta.url), 'utf-8'));
// Penanda teks baku bersama: juga diuji di tests/Feature/Pengaturan/LatarBelakangBakuTest.php terhadap TeksBaku.
const KASUS_TEKS_BAKU = JSON.parse(readFileSync(new URL('../fixtures/teks-baku.json', import.meta.url), 'utf-8'));

test.describe('Format mata uang di klien', () => {
    test('helper $rupiah/$angka sama dengan format server', async ({ page }) => {
        await page.goto('/kegiatan/1/rab');
        const hasil = await page.evaluate(() => {
            const f = window.SIRABTOR;
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

    test('terbilang klien sama dengan server & tampil di ringkasan RAB', async ({ page }) => {
        await page.goto('/kegiatan/1/rab');
        await expect(page.getByTestId('total-anggaran-terbilang').first()).toHaveText('“Sepuluh Juta Rupiah”');
        const hasil = await page.evaluate(() => [0, 11, 115000, 1000, 1001001, 32000000, 9540000, 1000000000000].map((n) => window.SIRABTOR.terbilang(n)));
        expect(hasil).toEqual([
            'Nol Rupiah',
            'Sebelas Rupiah',
            'Seratus Lima Belas Ribu Rupiah',
            'Seribu Rupiah',
            'Satu Juta Seribu Satu Rupiah',
            'Tiga Puluh Dua Juta Rupiah',
            'Sembilan Juta Lima Ratus Empat Puluh Ribu Rupiah',
            'Satu Triliun Rupiah',
        ]);
    });

    test('deteksi NIP & aspek konteks di klien sama dengan server', async ({ page }) => {
        await page.goto('/kegiatan/1/rab');
        const hasil = await page.evaluate(() => {
            const f = window.SIRABTOR;
            const aspek = { peserta: ['Peserta', 'Peserta: ', ['peserta', 'guru']], output: ['Output', 'Output: ', ['output', 'dokumen']] };
            return {
                nip: ['Narasumber 197210082007101002', 'NIP. 19721008 200710 1 002', 'nip pengawas', 'anggaran 10000000', '1972100820071010021'].map(f.memuatNip),
                aspek: f.aspekTerisi('Peserta 54 GURU', aspek),
            };
        });
        // Kasus sama dengan tests/Feature/Kegiatan/KonteksKegiatanTest (19 digit bukan NIP).
        expect(hasil).toEqual({ nip: [true, true, true, false, false], aspek: { peserta: true, output: false } });
    });

    test('tanggal Indonesia klien sama dengan server, tidak bergantung locale browser', async ({ page }) => {
        await page.goto('/kegiatan/1/rab');
        const hasil = await page.evaluate((kasus) => kasus.map((k) => ({
            panjang: window.SIRABTOR.tanggalPanjang(k.iso),
            akhirPekan: window.SIRABTOR.akhirPekan(k.iso),
        })), KASUS_TANGGAL);
        expect(hasil).toEqual(KASUS_TANGGAL.map(({ panjang, akhirPekan }) => ({ panjang, akhirPekan })));
    });

    test('status & persen termin klien sama dengan server', async ({ page }) => {
        await page.goto('/kegiatan/1/rab');
        const hasil = await page.evaluate((kasus) => kasus.map((k) => {
            const s = window.SIRABTOR.statusTermin(k.total, k.nominal);
            return { status: s.status, teks: s.teks, jumlah: s.jumlah, persen: k.nominal.map((n) => window.SIRABTOR.persenTermin(n, k.total)) };
        }), KASUS_STATUS_TERMIN);
        expect(hasil).toEqual(KASUS_STATUS_TERMIN.map(({ status, teks, jumlah, persen }) => ({ status, teks, jumlah, persen })));
    });

    test('ringkasan matrik klien sama dengan server', async ({ page }) => {
        await page.goto('/kegiatan/1/matrik');
        const hasil = await page.evaluate((kasus) => kasus.map((k) => window.SIRABTOR.ringkasanMatrik(k.matrik)), KASUS_RINGKASAN_MATRIK);
        expect(hasil).toEqual(KASUS_RINGKASAN_MATRIK.map(({ jumlahTanda, lengkap }) => ({ jumlahTanda, lengkap })));
    });

    test('konsistensi bulan klien sama dengan server', async ({ page }) => {
        await page.goto('/kegiatan/1/matrik');
        const hasil = await page.evaluate((kasus) => kasus.map((k) => window.SIRABTOR.periksaKonsistensiBulan(k)), KASUS_KONSISTENSI);
        expect(hasil).toEqual(KASUS_KONSISTENSI.map((k) => k.temuan));
    });

    test('status pagu klien sama dengan server', async ({ page }) => {
        await page.goto('/kegiatan/1/rab');
        const hasil = await page.evaluate((kasus) => kasus.map((k) => window.SIRABTOR.statusPagu(k.total, k.pagu)), KASUS_STATUS_PAGU);
        expect(hasil).toEqual(KASUS_STATUS_PAGU.map(({ status, teks, nada }) => ({ status, teks, nada })));
    });
});

test.describe('NIP di klien', () => {
    test('nip() & galatNip() sama dengan server', async ({ page }) => {
        await page.goto('/kegiatan/1/rab');
        const hasil = await page.evaluate((kasus) => kasus.map((k) => ({ format: window.SIRABTOR.nip(k.masukan), galat: window.SIRABTOR.galatNip(k.masukan) })), KASUS_NIP);
        expect(hasil).toEqual(KASUS_NIP.map(({ format, galat }) => ({ format, galat })));
    });
});

test.describe('Teks baku di klien', () => {
    test('penanda teks baku klien sama dengan TeksBaku server', async ({ page }) => {
        await page.goto('/kegiatan/1/rab');
        const hasil = await page.evaluate((kasus) => kasus.map((k) => ({
            isi: window.SIRABTOR.isiTeksBaku(k.teks, k.tahun, k.nama),
            asing: window.SIRABTOR.penandaAsing(k.teks, ['{tahun}', '{nama_kegiatan}']),
        })), KASUS_TEKS_BAKU);
        expect(hasil).toEqual(KASUS_TEKS_BAKU.map(({ isi, asing }) => ({ isi, asing })));
    });
});
