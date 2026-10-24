<?php

namespace App\Support\Stub;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Support\Rab\KalkulatorRab;

/**
 * Data anggaran tiruan untuk fitur Terbilang Otomatis (PRD F3): nominal nyata dari kegiatan & RAB tiruan,
 * kasus uji ejaan terbilang, dan contoh kesalahan pada dokumen lama.
 */
class StubAnggaran
{
    /**
     * Nominal yang perlu diterbilangkan untuk tiap kegiatan × sumber dana: total RAB (= total KAK), pagu, dan termin.
     *
     * @return list<array{kegiatan_id: int, kegiatan: string, sumber: string, jenis: string, label: string, nilai: int}>
     */
    public static function anggaranKegiatan(): array
    {
        $kegiatanRepo = app(KegiatanRepository::class);
        $rabRepo = app(RabRepository::class);
        $daftar = [];

        foreach ($kegiatanRepo->terbaru(50) as $ringkas) {
            $kegiatan = $kegiatanRepo->temukan($ringkas['id']);

            foreach ($kegiatan['sumber_dana'] ?? [] as $sumber) {
                $hasil = KalkulatorRab::hitung($rabRepo->rincian($kegiatan['id'], $sumber['sumber']), $sumber['pagu']);
                $dasar = ['kegiatan_id' => $kegiatan['id'], 'kegiatan' => $kegiatan['nama_kegiatan'] ?? '', 'sumber' => $sumber['sumber']];

                $daftar[] = $dasar + ['jenis' => 'total', 'label' => 'Total RAB '.$sumber['sumber'], 'nilai' => $hasil->total];

                if ($sumber['pagu'] !== null) {
                    $daftar[] = $dasar + ['jenis' => 'pagu', 'label' => 'Pagu '.$sumber['sumber'], 'nilai' => (int) $sumber['pagu']];
                }

                foreach (self::terminContoh($kegiatan['id'], $sumber['sumber']) as $nomor => $nominal) {
                    $daftar[] = $dasar + ['jenis' => 'termin', 'label' => 'Termin '.$nomor, 'nilai' => $nominal];
                }
            }
        }

        return $daftar;
    }

    /**
     * Termin pembayaran contoh dari Form RAB Workshop (kegiatan 1, Komite): jumlahnya = total RAB Rp10.000.000.
     *
     * @return array<int, int> nomor termin → nominal
     */
    public static function terminContoh(int $kegiatanId, string $sumber): array
    {
        return $kegiatanId === 1 && $sumber === 'Komite' ? [1 => 7_680_000, 2 => 2_320_000] : [];
    }

    /**
     * Kasus uji ejaan terbilang Rupiah (huruf awal kapital tiap kata, diakhiri "Rupiah").
     *
     * @return list<array{nilai: int, terbilang: string, catatan: string}>
     */
    public static function kasusUji(): array
    {
        return [
            ['nilai' => 0, 'terbilang' => 'Nol Rupiah', 'catatan' => 'nol'],
            ['nilai' => 1, 'terbilang' => 'Satu Rupiah', 'catatan' => 'satuan'],
            ['nilai' => 10, 'terbilang' => 'Sepuluh Rupiah', 'catatan' => '"se-" untuk sepuluh'],
            ['nilai' => 11, 'terbilang' => 'Sebelas Rupiah', 'catatan' => 'sebelas'],
            ['nilai' => 15, 'terbilang' => 'Lima Belas Rupiah', 'catatan' => 'belasan'],
            ['nilai' => 100, 'terbilang' => 'Seratus Rupiah', 'catatan' => '"se-" untuk seratus'],
            ['nilai' => 115_000, 'terbilang' => 'Seratus Lima Belas Ribu Rupiah', 'catatan' => 'ratusan ribu + belasan'],
            ['nilai' => 1_000, 'terbilang' => 'Seribu Rupiah', 'catatan' => '"se-" untuk seribu'],
            ['nilai' => 1_001_001, 'terbilang' => 'Satu Juta Seribu Satu Rupiah', 'catatan' => 'seribu di tengah angka'],
            ['nilai' => 1_100_000, 'terbilang' => 'Satu Juta Seratus Ribu Rupiah', 'catatan' => 'juta + seratus ribu'],
            ['nilai' => 2_320_000, 'terbilang' => 'Dua Juta Tiga Ratus Dua Puluh Ribu Rupiah', 'catatan' => 'termin 2 Form RAB'],
            ['nilai' => 2_500_750, 'terbilang' => 'Dua Juta Lima Ratus Ribu Tujuh Ratus Lima Puluh Rupiah', 'catatan' => 'tiga kelompok'],
            ['nilai' => 7_680_000, 'terbilang' => 'Tujuh Juta Enam Ratus Delapan Puluh Ribu Rupiah', 'catatan' => 'termin 1 Form RAB'],
            ['nilai' => 9_450_000, 'terbilang' => 'Sembilan Juta Empat Ratus Lima Puluh Ribu Rupiah', 'catatan' => 'KAK Paket Meeting (PRD)'],
            ['nilai' => 9_540_000, 'terbilang' => 'Sembilan Juta Lima Ratus Empat Puluh Ribu Rupiah', 'catatan' => 'RAB Paket Meeting (PRD)'],
            ['nilai' => 10_000_000, 'terbilang' => 'Sepuluh Juta Rupiah', 'catatan' => 'contoh PRD F3'],
            ['nilai' => 20_000_000, 'terbilang' => 'Dua Puluh Juta Rupiah', 'catatan' => 'data TOR baris 2'],
            ['nilai' => 32_000_000, 'terbilang' => 'Tiga Puluh Dua Juta Rupiah', 'catatan' => 'dulu ditulis "Tiga Puluh Juta"'],
            ['nilai' => 1_000_000_000, 'terbilang' => 'Satu Miliar Rupiah', 'catatan' => 'miliar'],
            ['nilai' => 1_000_000_000_000, 'terbilang' => 'Satu Triliun Rupiah', 'catatan' => 'triliun'],
            [
                'nilai' => 999_999_999_999,
                'terbilang' => 'Sembilan Ratus Sembilan Puluh Sembilan Miliar Sembilan Ratus Sembilan Puluh Sembilan Juta Sembilan Ratus Sembilan Puluh Sembilan Ribu Sembilan Ratus Sembilan Puluh Sembilan Rupiah',
                'catatan' => 'angka terpanjang di bawah satu triliun',
            ],
        ];
    }

    /**
     * Kesalahan terbilang pada dokumen lama (PRD "Kondisi saat ini") untuk ditampilkan sebagai pembanding.
     *
     * @return list<array{nilai: int, tertulis: string, masalah: string}>
     */
    public static function kesalahanDokumenLama(): array
    {
        return [
            ['nilai' => 10_000_000, 'tertulis' => '#NAME?', 'masalah' => 'Formula terbilang Excel tidak jalan.'],
            ['nilai' => 10_000_000, 'tertulis' => 'Sepuluh Juta Puluh', 'masalah' => 'Diketik manual, susunan kata rusak.'],
            ['nilai' => 32_000_000, 'tertulis' => 'Tiga Puluh Juta', 'masalah' => 'Angka & terbilang tidak cocok.'],
        ];
    }
}
