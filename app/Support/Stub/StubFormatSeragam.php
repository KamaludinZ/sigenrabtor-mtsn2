<?php

namespace App\Support\Stub;

/**
 * Data tiruan halaman Format Seragam selama penyimpanannya belum dibuat: aturan penomoran per jenis dokumen (pola
 * FormatSeragam::TOKEN, panjang nomor urut, reset tahunan, nomor terakhir TA aktif) dan pilihan template per jenis kegiatan
 * (KonteksAi::jenisKegiatan) beserta template khusus leading.
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

    /**
     * Template per jenis kegiatan (null = susunan baku aplikasi / cover default) dan template khusus leading.
     *
     * @return array{jenis: array<string, array{kak: string|null, cover: string|null}>, leading: list<array{leading: string, jenis: string, kak: string|null, cover: string|null}>}
     */
    public static function template(): array
    {
        return [
            'jenis' => [
                'workshop' => ['kak' => 'Master KAK/TOR Workshop 2026', 'cover' => null],
                'supervisi' => ['kak' => null, 'cover' => null],
                'lomba' => ['kak' => 'Master KAK/TOR Lomba Siswa', 'cover' => 'Cover Kesiswaan'],
                'siswa' => ['kak' => null, 'cover' => 'Cover Kesiswaan'],
                'umum' => ['kak' => null, 'cover' => null],
            ],
            'leading' => [
                ['leading' => '1.6', 'jenis' => 'supervisi', 'kak' => 'Master KAK/TOR Penjaminan Mutu', 'cover' => null],
                ['leading' => '1.3', 'jenis' => 'lomba', 'kak' => null, 'cover' => 'Cover Kesiswaan Berprestasi'],
            ],
        ];
    }
}
