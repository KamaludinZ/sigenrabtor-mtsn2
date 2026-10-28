<?php

namespace App\Support\Pengaturan;

/**
 * NIP pegawai (18 angka: tanggal lahir 8, TMT CPNS tahun-bulan 6, jenis kelamin 1, nomor urut 3) untuk blok tanda
 * tangan. Disimpan tanpa pemisah; tampil "19700101 199503 1 001". Galat juga untuk NIP yang mustahil: TMT sebelum usia
 * USIA_MIN_TMT tahun atau sesudah bulan ini, dan nomor urut 000. Kembaran klien: nip()/galatNip() di
 * resources/js/format.js (kasus bersama tests/fixtures/nip.json). NIP tidak pernah dikirim ke AI.
 */
final class Nip
{
    /** Usia minimal (tahun) saat TMT CPNS. */
    public const USIA_MIN_TMT = 18;

    /** Buang spasi, titik, dan tanda hubung. */
    public static function bersihkan(?string $teks): string
    {
        return preg_replace('/[\s.\-]+/u', '', (string) $teks);
    }

    /** "197001011995031001" → "19700101 199503 1 001"; selain 18 angka dikembalikan apa adanya (sudah dibersihkan). */
    public static function format(?string $teks): string
    {
        $digit = self::bersihkan($teks);

        return preg_match('/^\d{18}$/', $digit) ? substr($digit, 0, 8).' '.substr($digit, 8, 6).' '.substr($digit, 14, 1).' '.substr($digit, 15, 3) : $digit;
    }

    /** Pesan galat NIP, atau null bila sah atau kosong (wajib/tidaknya diatur pemakai). */
    public static function galat(?string $teks): ?string
    {
        $digit = self::bersihkan($teks);

        return match (true) {
            $digit === '' => null,
            ! preg_match('/^\d+$/', $digit) => 'NIP hanya boleh berisi angka (spasi, titik, atau tanda hubung diabaikan).',
            strlen($digit) !== 18 => 'NIP harus 18 angka (sekarang '.strlen($digit).').',
            ! checkdate((int) substr($digit, 4, 2), (int) substr($digit, 6, 2), (int) substr($digit, 0, 4)) || (int) substr($digit, 0, 4) < 1900 => '8 angka pertama NIP (tanggal lahir) bukan tanggal yang sah.',
            (int) substr($digit, 12, 2) < 1 || (int) substr($digit, 12, 2) > 12 || (int) substr($digit, 8, 4) < 1900 => 'Angka ke-9 sampai 14 NIP (tahun & bulan TMT) tidak sah.',
            substr($digit, 8, 6) < sprintf('%04d', (int) substr($digit, 0, 4) + self::USIA_MIN_TMT).substr($digit, 4, 2) => 'TMT pada NIP (angka ke-9 sampai 14) lebih awal dari usia '.self::USIA_MIN_TMT.' tahun menurut tanggal lahirnya.',
            substr($digit, 8, 6) > now()->format('Ym') => 'TMT pada NIP (angka ke-9 sampai 14) belum terjadi.',
            ! in_array($digit[14], ['1', '2'], true) => 'Angka ke-15 NIP (jenis kelamin) harus 1 atau 2.',
            substr($digit, 15, 3) === '000' => 'Nomor urut NIP (3 angka terakhir) tidak boleh 000.',
            default => null,
        };
    }
}
