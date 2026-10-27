<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Format tampilan baku Indonesia (tanggal, angka) yang dipakai layar dan dokumen.
 */
class Format
{
    /** Nilai baca parseRupiah() untuk angka yang melebihi batas terbilang (Terbilang::MAKSIMUM + 1). */
    public const MAKS_BACA_RUPIAH = 1_000_000_000_000_000;

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

        // Lebih dari 15 digit (> 999 triliun, batas Terbilang) dibaca sebagai 1.000 triliun: tetap ditolak validasi
        // "terlalu besar" tanpa meluapkan bilangan bulat saat dijumlahkan (mis. ketikan yang belum divalidasi).
        if (strlen(ltrim($digit, '0')) > 15) {
            return self::MAKS_BACA_RUPIAH;
        }

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

    /**
     * "2026-08-03" → "Senin, 3 Agustus 2026"; null bila kosong atau bukan tanggal sah (mis. 2026-02-30).
     * Kembaran klien: tanggalPanjang() di resources/js/format.js (uji bersama tests/fixtures/tanggal.json).
     */
    public static function tanggalPanjang(?string $iso): ?string
    {
        return self::tanggalIso($iso)?->locale('id')->translatedFormat('l, j F Y');
    }

    /** Nama hari bila tanggal jatuh di Sabtu/Minggu, selain itu null (kembaran akhirPekan() di klien). */
    public static function akhirPekan(?string $iso): ?string
    {
        $tanggal = self::tanggalIso($iso);

        return $tanggal?->isWeekend() ? $tanggal->locale('id')->translatedFormat('l') : null;
    }

    private static function tanggalIso(?string $iso): ?Carbon
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $iso, $bagian) || ! checkdate((int) $bagian[2], (int) $bagian[3], (int) $bagian[1])) {
            return null;
        }

        return Carbon::createFromDate((int) $bagian[1], (int) $bagian[2], (int) $bagian[3])->startOfDay();
    }
}
