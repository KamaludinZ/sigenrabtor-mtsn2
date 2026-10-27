<?php

namespace App\Support\Master;

use App\Support\MasterKode;

/**
 * Lima jenis master kode per tahun anggaran (PRD F7, §14): label, asal di Excel, kolom kegiatan yang memakainya, serta
 * baris & jumlahnya dari MasterKode. Dipakai halaman Master Kode (tab per jenis) dan kelak unggah Excel.
 */
final class JenisKode
{
    /** Kunci jenis → label tab, judul, asal Excel, kolom kegiatan pemakai, dan kunci baris. Urutan = urutan tab. */
    public const DAFTAR = [
        'sk' => ['label' => 'SK', 'judul' => 'Sasaran Kegiatan (SK)', 'asal' => 'Sheet Perkin — kode & uraian sasaran kegiatan.', 'kolom_kegiatan' => 'kode_sk', 'kunci' => 'kode'],
        'iksk' => ['label' => 'IKSK', 'judul' => 'Indikator Kinerja Sasaran Kegiatan (IKSK)', 'asal' => 'Sheet Perkin — kode & uraian indikator, terhubung ke SK.', 'kolom_kegiatan' => 'kode_iksk', 'kunci' => 'kode'],
        'rktm' => ['label' => 'RKTM', 'judul' => 'Rencana Kerja Tahunan Madrasah (RKTM)', 'asal' => 'Sheet SNP — standar, kegiatan, dan sub kegiatan.', 'kolom_kegiatan' => 'kode_rktm', 'kunci' => 'kode'],
        'edm' => ['label' => 'EDM', 'judul' => 'Indikator Evaluasi Diri Madrasah (EDM)', 'asal' => 'Kode indikator (A1, B3, C1, …) & uraian, dikelompokkan per komponen.', 'kolom_kegiatan' => 'kode_edm', 'kunci' => 'kode'],
        'budaya' => ['label' => '5 Budaya Mutu', 'judul' => '5 Budaya Mutu', 'asal' => 'Daftar butir budaya mutu yang dipilih per kegiatan.', 'kolom_kegiatan' => 'budaya_mutu', 'kunci' => 'nomor'],
    ];

    /**
     * Baris master jenis $jenis tahun $tahun (bentuk sama dengan MasterKode).
     *
     * @return list<array<string, mixed>>
     */
    public static function baris(string $jenis, int $tahun): array
    {
        return match ($jenis) {
            'sk' => MasterKode::kodeSk($tahun),
            'iksk' => MasterKode::kodeIksk(null, $tahun),
            'rktm' => MasterKode::kodeRktm($tahun),
            'edm' => MasterKode::kodeEdm($tahun),
            'budaya' => MasterKode::budayaMutu($tahun),
        };
    }

    /**
     * Jumlah baris tiap jenis tahun $tahun.
     *
     * @return array<string, int>
     */
    public static function jumlah(int $tahun): array
    {
        return array_map(fn (string $jenis) => count(self::baris($jenis, $tahun)), array_combine(array_keys(self::DAFTAR), array_keys(self::DAFTAR)));
    }

    /**
     * Banyaknya kegiatan yang memakai tiap kode jenis $jenis.
     *
     * @param  iterable<array<string, mixed>>  $kegiatan
     * @return array<string, int> Kode (atau nomor) → jumlah kegiatan.
     */
    public static function pemakaian(string $jenis, iterable $kegiatan): array
    {
        $kolom = self::DAFTAR[$jenis]['kolom_kegiatan'];
        $jumlah = [];
        foreach ($kegiatan as $k) {
            $kode = $k[$kolom] ?? null;
            if (filled($kode)) {
                $jumlah[(string) $kode] = ($jumlah[(string) $kode] ?? 0) + 1;
            }
        }

        return $jumlah;
    }
}
