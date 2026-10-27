<?php

namespace App\Support\Dokumen;

use App\Support\MasterKode;
use App\Support\Stub\StubData;

/**
 * Kotak teks di atas gambar cover (PRD cover_templates.teks_items: {field, x, y, width, font_size, align, weight, color}).
 * Posisi & lebar dalam persen halaman A4 potret (x = tepi kiri, y = tepi atas) sehingga tidak bergantung resolusi
 * gambar; ukuran huruf dalam pt pada lebar A4 (595 pt). Isi tiap kotak diturunkan dari data kegiatan × sumber dana.
 */
final class TeksCover
{
    /** Kolom isian cover: kunci → label. */
    public const FIELD = [
        'kop' => 'Kop madrasah',
        'judul' => 'Judul dokumen',
        'nama_kegiatan' => 'Nama kegiatan (versi cover)',
        'sumber_anggaran' => 'Sumber anggaran',
        'disusun_oleh' => '“Disusun oleh”',
        'leading' => 'Nama leading',
        'kota' => 'Kota',
        'tahun' => 'Tahun anggaran',
    ];

    /** Lebar A4 dalam pt — acuan font_size. */
    public const LEBAR_PT = 595;

    /**
     * Susunan bawaan untuk cover baru: semua kotak rata tengah selebar 80% halaman.
     *
     * @return list<array{field: string, x: float, y: float, width: float, font_size: float, align: string, weight: string, color: string}>
     */
    public static function bawaan(): array
    {
        $kotak = fn (string $field, float $y, float $ukuran, string $tebal = 'bold') => [
            'field' => $field, 'x' => 10.0, 'y' => $y, 'width' => 80.0, 'font_size' => $ukuran, 'align' => 'center', 'weight' => $tebal, 'color' => '#1c1917',
        ];

        return [
            $kotak('kop', 7, 13),
            $kotak('judul', 22, 18),
            $kotak('nama_kegiatan', 32, 17),
            $kotak('sumber_anggaran', 48, 12, 'normal'),
            $kotak('disusun_oleh', 64, 12, 'normal'),
            $kotak('leading', 68, 14),
            $kotak('kota', 86, 13),
            $kotak('tahun', 90, 13),
        ];
    }

    /**
     * Kotak teks yang aman ditampilkan: kolom dikenal, posisi 0–100%, ukuran 6–48 pt, perataan & ketebalan baku,
     * warna heksadesimal. Data tersimpan (atau kiriman klien nanti) selalu lewat sini sebelum masuk atribut style.
     *
     * @param  array<mixed>  $items
     * @return list<array{field: string, x: float, y: float, width: float, font_size: float, align: string, weight: string, color: string}>
     */
    public static function rapikan(array $items): array
    {
        $angka = fn (mixed $n, float $min, float $max, float $baku) => is_numeric($n) ? max($min, min($max, round((float) $n, 2))) : $baku;
        $hasil = [];

        foreach ($items as $item) {
            if (! is_array($item) || ! array_key_exists($item['field'] ?? null, self::FIELD)) {
                continue;
            }

            $x = $angka($item['x'] ?? null, 0, 100, 10);
            $hasil[] = [
                'field' => $item['field'],
                'x' => $x,
                'y' => $angka($item['y'] ?? null, 0, 100, 0),
                'width' => $angka($item['width'] ?? null, 5, 100 - $x, min(80, 100 - $x)),
                'font_size' => $angka($item['font_size'] ?? null, 6, 48, 12),
                'align' => in_array($item['align'] ?? null, ['left', 'center', 'right'], true) ? $item['align'] : 'center',
                'weight' => ($item['weight'] ?? null) === 'normal' ? 'normal' : 'bold',
                'color' => is_string($item['color'] ?? null) && preg_match('/^#[0-9a-f]{6}$/i', $item['color']) ? strtolower($item['color']) : '#1c1917',
            ];
        }

        return $hasil;
    }

    /**
     * Isi tiap kotak untuk satu kegiatan × sumber dana (pratinjau & cetak).
     *
     * @param  array<string, mixed>  $kegiatan
     * @return array<string, string>
     */
    public static function nilai(array $kegiatan, ?string $sumber): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $madrasah = StubData::madrasah();
        $nama = trim((string) ($kegiatan['nama_kegiatan_cover'] ?? '')) ?: trim((string) ($kegiatan['nama_kegiatan'] ?? ''));
        $leading = MasterKode::leading($kegiatan['leading'] ?? null);

        return [
            'kop' => mb_strtoupper($madrasah['nama']),
            'judul' => 'KERANGKA ACUAN KERJA (KAK) / TERM OF REFERENCE (TOR)',
            'nama_kegiatan' => $nama === '' ? 'KEGIATAN …' : mb_strtoupper('Kegiatan '.$nama),
            'sumber_anggaran' => 'SUMBER DANA '.($sumber ? mb_strtoupper($sumber) : '…'),
            'disusun_oleh' => 'Disusun oleh:',
            'leading' => $leading ? mb_strtoupper($leading['jabatan_penanda_tangan']) : 'PEJABAT LEADING',
            'kota' => mb_strtoupper($madrasah['kota']),
            'tahun' => "TAHUN ANGGARAN {$tahun}",
        ];
    }
}
