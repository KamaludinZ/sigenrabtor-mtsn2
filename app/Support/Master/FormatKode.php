<?php

namespace App\Support\Master;

/**
 * Format kode SK & IKSK master (PRD §14) — satu aturan untuk pembaca Excel master, impor cascade Perkin, dan pemeriksaan
 * database. Dua gaya penulisan yang dipakai madrasah sama-sama diterima:
 * - gaya lama berurutan: `SK.10`, `IKSK 10.2`;
 * - gaya kode Kemenag (Perjanjian Kinerja/cascade): `SK.1.2133`, `SK 6708.1`, `IKSK.1.2133.1`, `IKSK.4422.1.1.a`.
 *
 * Bentuk baku (dipakai sebagai kunci, jadi harus deterministik):
 * - SK  = "SK." + bagian angka bertitik, mis. "SK.10", "SK.1.2133", "SK.6708.1".
 * - IKSK = tepat dua bagian angka → "IKSK n.m" (bentuk lama tetap utuh); selain itu → "IKSK." + bagian bertitik,
 *   bagian terakhir boleh satu huruf kecil, mis. "IKSK.1.2133.1", "IKSK.4422.1.1.a", "IKSK.6708.1.4".
 * Pemisah yang ditulis dengan spasi, titik, atau tanda hubung dirapikan; nol di depan angka dibuang.
 * IKSK sejalan dengan SK induknya bila bagian angka SK menjadi awalan bagian IKSK (SK.10 → IKSK 10.2,
 * SK.4422.1 → IKSK.4422.1.1.a).
 */
final class FormatKode
{
    /** Pola database (CHECK) untuk bentuk baku — kembaran `sk()`/`iksk()`. */
    public const POLA_SK = '^SK\.[0-9]+(\.[0-9]+)*$';

    public const POLA_IKSK = '^IKSK( [0-9]+\.[0-9]+|\.[0-9]+(\.[0-9]+)*\.([0-9]+|[a-z]))$';

    /** Contoh untuk pesan galat. */
    public const CONTOH_SK = 'SK.10 atau SK.1.2133';

    public const CONTOH_IKSK = 'IKSK 10.2 atau IKSK.1.2133.1';

    /** "sk 10", "SK10", "SK-10", "SK 6708.1", "sk.1.2133" → bentuk baku; null bila bukan kode SK. */
    public static function sk(string $kode): ?string
    {
        $bagian = self::bagian($kode, 'SK');
        if ($bagian === null || $bagian['huruf'] !== null) {
            return null;
        }

        return 'SK.'.implode('.', $bagian['angka']);
    }

    /** "iksk 10.2", "IKSK10-2", "IKSK.1.2133.1", "IKSK 6708.1.4", "IKSK.4422.1.1.a" → bentuk baku; null bila bukan IKSK. */
    public static function iksk(string $kode): ?string
    {
        $bagian = self::bagian($kode, 'IKSK');
        if ($bagian === null || count($bagian['angka']) + ($bagian['huruf'] !== null ? 1 : 0) < 2) {
            return null;
        }
        if ($bagian['huruf'] === null && count($bagian['angka']) === 2) {
            return 'IKSK '.implode('.', $bagian['angka']);
        }

        return 'IKSK.'.implode('.', [...$bagian['angka'], ...($bagian['huruf'] !== null ? [$bagian['huruf']] : [])]);
    }

    /** IKSK (bentuk baku) sejalan dengan SK induk (bentuk baku): bagian SK menjadi awalan bagian IKSK. */
    public static function sejalan(string $iksk, string $sk): bool
    {
        $angkaSk = explode('.', substr($sk, 3));
        $angkaIksk = preg_split('/[ .]/', substr($iksk, 5)) ?: [];
        $angkaIksk = array_values(array_filter($angkaIksk, fn (string $b) => ctype_digit($b)));

        return count($angkaIksk) > count($angkaSk) && array_slice($angkaIksk, 0, count($angkaSk)) === $angkaSk;
    }

    /** Kunci urut alami kode SK/IKSK (bagian angka dipisah titik) untuk version_compare. */
    public static function urutan(string $kode): string
    {
        return preg_replace('/^[A-Z]+[ .]/', '', $kode) ?? $kode;
    }

    /**
     * @return array{angka: list<int>, huruf: string|null}|null
     */
    private static function bagian(string $kode, string $awalan): ?array
    {
        $kode = trim($kode);
        if (! preg_match('/^'.$awalan.'\s*[.\-]?\s*(\d+(?:\s*[.\-]\s*\d+)*)(?:\s*[.\-]\s*([a-z]))?$/i', $kode, $cocok)) {
            return null;
        }

        return [
            'angka' => array_map('intval', preg_split('/\s*[.\-]\s*/', $cocok[1]) ?: []),
            'huruf' => isset($cocok[2]) && $cocok[2] !== '' ? mb_strtolower($cocok[2]) : null,
        ];
    }
}
