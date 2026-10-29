<?php

namespace App\Support\Stub;

/**
 * Data tiruan halaman Format Seragam selama penyimpanannya belum dibuat: aturan penomoran per jenis dokumen (pola
 * FormatSeragam::TOKEN, panjang nomor urut, reset tahunan, nomor terakhir TA aktif). Pilihan template kini lewat
 * PilihanTemplateRepository.
 */
final class StubFormatSeragam
{
    /**
     * @return array<string, array{pola: string, digit: int, reset: string, terakhir: int, aktif: bool}>
     */
    public static function penomoran(): array
    {
        return [
            'KAK+TOR' => ['pola' => '{jenis}/{urut}/{madrasah}/{bulan_romawi}/{tahun}', 'digit' => 3, 'reset' => 'tahunan', 'terakhir' => 31, 'aktif' => true],
            'KAK' => ['pola' => 'KAK/{urut}/{tahun}', 'digit' => 3, 'reset' => 'tahunan', 'terakhir' => 4, 'aktif' => true],
            'TOR' => ['pola' => 'TOR/{urut}/{tahun}', 'digit' => 3, 'reset' => 'tahunan', 'terakhir' => 0, 'aktif' => false],
            'RAB' => ['pola' => 'RAB/{urut}/{sumber}/{leading}/{tahun}', 'digit' => 3, 'reset' => 'tahunan', 'terakhir' => 12, 'aktif' => true],
            'lengkap' => ['pola' => 'DOK/{urut}/{leading}/{tahun}', 'digit' => 3, 'reset' => 'tahunan', 'terakhir' => 7, 'aktif' => true],
        ];
    }
}
