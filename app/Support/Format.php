<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Format tampilan baku Indonesia (tanggal, angka) yang dipakai layar dan dokumen.
 */
class Format
{
    /**
     * 10000000 → "10.000.000" (pemisah ribuan titik); pecahan memakai koma, mis. 1.5 → "1,5".
     */
    public static function angka(int|float $nilai): string
    {
        if (is_float($nilai) && floor($nilai) != $nilai) {
            return rtrim(rtrim(number_format($nilai, 2, ',', '.'), '0'), ',');
        }

        return number_format($nilai, 0, ',', '.');
    }

    /**
     * 10000000 → "Rp10.000.000"; nilai negatif → "-Rp600.000" (tanda di depan, bukan "Rp-600.000").
     */
    public static function rupiah(int|float $nilai): string
    {
        return ($nilai < 0 ? '-' : '').'Rp'.self::angka(abs($nilai));
    }

    /**
     * Gaya baku dokumen KAK/RAB madrasah: 10000000 → "Rp10.000.000,-".
     */
    public static function rupiahDokumen(int|float $nilai): string
    {
        return self::rupiah($nilai).',-';
    }

    /**
     * Ambil nilai rupiah bulat dari teks ketikan ("Rp 10.000.000", "10000000"); null bila tidak ada angka.
     */
    public static function parseRupiah(string|int|null $teks): ?int
    {
        if (is_int($teks)) {
            return $teks;
        }

        $digit = preg_replace('/\D/', '', (string) $teks);

        return $digit === '' ? null : (int) $digit;
    }

    /**
     * "2025-12-23" → "23 Desember 2025". Mengembalikan null bila kosong/tidak valid.
     */
    public static function tanggal(?string $tanggal): ?string
    {
        if ($tanggal === null || trim($tanggal) === '') {
            return null;
        }

        try {
            return Carbon::parse($tanggal)->locale('id')->translatedFormat('j F Y');
        } catch (Throwable) {
            return null;
        }
    }
}
