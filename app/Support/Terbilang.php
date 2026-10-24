<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Terbilang Rupiah Bahasa Indonesia (PRD F3): 10.000.000 → "Sepuluh Juta Rupiah".
 *
 * Kaidah: "se-" untuk 10, 11, 100, dan 1.000 (sepuluh, sebelas, seratus, seribu), "satu" untuk juta ke atas
 * (Satu Juta, Satu Miliar). Kembaran di sisi klien: resources/js/format.js → terbilang().
 */
class Terbilang
{
    /** Batas atas yang didukung: di bawah 1.000 triliun. */
    public const MAKSIMUM = 999_999_999_999_999;

    private const SATUAN = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    private const SKALA = [
        1_000_000_000_000 => 'triliun',
        1_000_000_000 => 'miliar',
        1_000_000 => 'juta',
    ];

    /**
     * Terbilang lengkap dengan "Rupiah", huruf awal tiap kata kapital: 32000000 → "Tiga Puluh Dua Juta Rupiah".
     */
    public static function rupiah(int $nilai): string
    {
        return self::kapital(self::kata($nilai).' rupiah');
    }

    /**
     * Terbilang untuk ditampilkan: null bila nilai kosong atau nol, agar layar/dokumen menampilkan keterangan
     * kontekstual (mis. "belum ada rincian") alih-alih "Nol Rupiah" untuk anggaran yang belum diisi.
     */
    public static function tampil(?int $nilai): ?string
    {
        return $nilai === null || $nilai === 0 ? null : self::rupiah($nilai);
    }

    /**
     * Terbilang huruf kecil tanpa satuan mata uang: 1001001 → "satu juta seribu satu".
     */
    public static function kata(int $nilai): string
    {
        if (abs($nilai) > self::MAKSIMUM) {
            throw new InvalidArgumentException('Nilai terlalu besar untuk diterbilangkan (maksimal 999 triliun).');
        }

        if ($nilai === 0) {
            return 'nol';
        }

        return $nilai < 0 ? 'minus '.self::eja(-$nilai) : self::eja($nilai);
    }

    private static function eja(int $n): string
    {
        foreach (self::SKALA as $besar => $nama) {
            if ($n >= $besar) {
                return self::gabung(self::eja(intdiv($n, $besar)).' '.$nama, $n % $besar);
            }
        }

        return match (true) {
            $n < 12 => self::SATUAN[$n],
            $n < 20 => self::SATUAN[$n - 10].' belas',
            $n < 100 => self::gabung(self::SATUAN[intdiv($n, 10)].' puluh', $n % 10),
            $n < 200 => self::gabung('seratus', $n - 100),
            $n < 1_000 => self::gabung(self::SATUAN[intdiv($n, 100)].' ratus', $n % 100),
            $n < 2_000 => self::gabung('seribu', $n - 1_000),
            default => self::gabung(self::eja(intdiv($n, 1_000)).' ribu', $n % 1_000),
        };
    }

    private static function gabung(string $depan, int $sisa): string
    {
        return $sisa === 0 ? $depan : $depan.' '.self::eja($sisa);
    }

    private static function kapital(string $teks): string
    {
        return implode(' ', array_map(fn (string $kata) => mb_strtoupper(mb_substr($kata, 0, 1)).mb_substr($kata, 1), explode(' ', $teks)));
    }
}
