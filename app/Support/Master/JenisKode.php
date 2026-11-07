<?php

namespace App\Support\Master;

use App\Support\MasterKode;
use App\Support\Stub\StubData;

/**
 * Jenis master kode per tahun anggaran (PRD F7, §14) — lima kode yang dipilih kegiatan (SK, IKSK, RKTM, EDM, 5 Budaya
 * Mutu) dan Rencana Aksi cascade Perkin (bertag kode-kode itu): label, asal di Excel, kolom kegiatan yang memakainya,
 * serta baris & jumlahnya dari MasterKode. Dipakai halaman Master Kode (tab per jenis) dan unggah Excel.
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
        'rencana_aksi' => ['label' => 'Rencana Aksi', 'judul' => 'Rencana Aksi (cascade Perkin)', 'asal' => 'Sheet Perkin — rencana aksi per IKSK dengan kode RKTM, unit penerima mandat, anggaran, dan bulan; tag EDM otomatis dari relasi EDM ↔ RKTM.', 'kolom_kegiatan' => 'nama_kegiatan', 'kunci' => 'kode'],
    ];

    /** Jenis yang dipilih langsung di isian kode kegiatan (selain Rencana Aksi). */
    public const KODE_KEGIATAN = ['sk', 'iksk', 'rktm', 'edm', 'budaya'];

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
            'rencana_aksi' => MasterKode::rencanaAksi($tahun),
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
     * Rencana Aksi: kegiatan yang namanya sama dengan rencana aksi tahun $tahun (RencanaAksi::pemakaian).
     *
     * @param  iterable<array<string, mixed>>  $kegiatan
     * @return array<string, int> Kode (atau nomor) → jumlah kegiatan.
     */
    public static function pemakaian(string $jenis, iterable $kegiatan, ?int $tahun = null): array
    {
        if ($jenis === 'rencana_aksi') {
            return RencanaAksi::pemakaian($kegiatan, $tahun ?? StubData::tahunAktif()['tahun']);
        }
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
