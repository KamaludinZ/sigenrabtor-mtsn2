<?php

namespace App\Support;

/**
 * Bacaan satu nominal rupiah dalam semua gaya baku Indonesia (PRD aturan 2 & 10) untuk endpoint `format.rupiah`
 * (`GET /format/rupiah?nilai=`, semua pengguna): masukan teks/angka dibaca Format::parseRupiah (titik ribuan, "Rp",
 * akhiran sen ",-"/",00"; tanda minus di depan = negatif) lalu ditulis lewat Format (angka, rupiah, dokumen ",-",
 * desimal ",00") dan Terbilang (kata, rupiah, `dengan_angka`; `terbilang` null untuk 0 seperti Terbilang::tampil).
 * Tanpa angka atau di atas Terbilang::MAKSIMUM → `sah` false + `galat`.
 */
final class FormatRupiah
{
    public const GALAT_KOSONG = 'Isi angka rupiah, mis. 10.000.000.';

    public const GALAT_BESAR = 'Nilai terlalu besar untuk diterbilangkan (maksimal 999 triliun).';

    /** @return array<string, mixed> */
    public static function untuk(string|int|null $masukan): array
    {
        $nilai = Format::parseRupiah($masukan);
        if ($nilai !== null && is_string($masukan) && str_starts_with(ltrim($masukan), '-')) {
            $nilai = -$nilai;
        }

        $galat = match (true) {
            $nilai === null => self::GALAT_KOSONG,
            abs($nilai) > Terbilang::MAKSIMUM => self::GALAT_BESAR,
            default => null,
        };
        if ($galat !== null) {
            return ['masukan' => $masukan, 'nilai' => null, 'sah' => false, 'galat' => $galat];
        }

        return [
            'masukan' => $masukan,
            'nilai' => $nilai,
            'sah' => true,
            'galat' => null,
            'angka' => Format::angka($nilai),
            'rupiah' => Format::rupiah($nilai),
            'rupiah_dokumen' => Format::rupiahDokumen($nilai),
            'rupiah_desimal' => Format::rupiahDesimal($nilai),
            'terbilang' => Terbilang::tampil($nilai),
            'terbilang_kata' => Terbilang::kata($nilai),
            'terbilang_rupiah' => Terbilang::rupiah($nilai),
            'dengan_angka' => Terbilang::denganAngka($nilai),
        ];
    }
}
