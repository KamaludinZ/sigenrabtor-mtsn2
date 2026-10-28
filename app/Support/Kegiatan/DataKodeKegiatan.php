<?php

namespace App\Support\Kegiatan;

use App\Support\Master\IkskPerSk;
use App\Support\Master\JenisKode;
use App\Support\MasterKode;

/**
 * Master kode satu kegiatan untuk endpoint `kegiatan.kode_master` (`GET /kegiatan/{id}/kode-master`; PRD F7 & aturan 7
 * "kode wajib valid"): leading (MasterKode::leading — termasuk nonaktif) dan tiap kode RKTM/SK/IKSK/EDM/5 Budaya Mutu
 * kegiatan dicocokkan dengan master **tahun anggaran kegiatannya** (TahunKegiatan): status & pesan KelengkapanKode
 * (terisi/kosong/asing/tidak_sesuai), baris master yang cocok (bentuk sama dengan endpoint `master.kode`; EDM membawa
 * nama komponen), dan tautan daftar pilihannya; pilihan IKSK untuk SK terpilih (IkskPerSk), penanda kode hilang
 * (KodeHilang::dari), riwayat perubahan kode terbaru, kode yang menghalangi pengajuan, dan bisa/tidaknya diubah.
 */
final class DataKodeKegiatan
{
    /** Isian kode kegiatan → jenis master (JenisKode::DAFTAR). */
    public const JENIS = ['kode_rktm' => 'rktm', 'kode_sk' => 'sk', 'kode_iksk' => 'iksk', 'kode_edm' => 'edm', 'budaya_mutu' => 'budaya'];

    public const BATAS_RIWAYAT = 5;

    /**
     * @param  array<string, mixed>  $kegiatan
     * @return array<string, mixed>
     */
    public static function untuk(array $kegiatan): array
    {
        $tahun = TahunKegiatan::dari($kegiatan);
        $leading = filled($kegiatan['leading'] ?? null) ? MasterKode::leading($kegiatan['leading']) : null;
        $komponen = MasterKode::komponenEdm($tahun);

        $kode = [];
        foreach (KelengkapanKode::periksa($kegiatan, $tahun) as $isian => $periksa) {
            $jenis = self::JENIS[$isian];
            $master = $periksa['kode'] === null ? null : MasterKode::cari(JenisKode::baris($jenis, $tahun), $periksa['kode'], JenisKode::DAFTAR[$jenis]['kunci']);
            if ($master !== null && $jenis === 'edm') {
                $master += ['nama_komponen' => $komponen[$master['komponen']] ?? null];
            }
            $kode[$isian] = $periksa + ['jenis' => $jenis, 'master' => $master, 'pilihan' => route('master.kode', ['jenis' => $jenis, 'ta' => $tahun])];
        }
        $kode['kode_iksk']['pilihan'] = route('master.kode', ['jenis' => 'iksk', 'ta' => $tahun] + (filled($kegiatan['kode_sk'] ?? null) ? ['sk' => $kegiatan['kode_sk']] : []));

        return [
            'kegiatan_id' => (int) $kegiatan['id'],
            'tahun' => $tahun,
            'master_tersedia' => MasterKode::tersedia($tahun),
            'leading' => $leading === null ? ['kode' => $kegiatan['leading'] ?? null, 'nama_unit' => null, 'jabatan_penanda_tangan' => null, 'aktif' => null]
                : ['kode' => $leading['kode'], 'nama_unit' => $leading['nama_unit'], 'jabatan_penanda_tangan' => $leading['jabatan_penanda_tangan'], 'aktif' => (bool) ($leading['is_active'] ?? true)],
            'kode' => $kode,
            'terisi' => count(array_filter($kode, fn (array $k) => $k['status'] === 'terisi')),
            'pilihan_iksk' => filled($kegiatan['kode_sk'] ?? null) ? IkskPerSk::pilihan((string) $kegiatan['kode_sk'], $tahun) : [],
            'galat_pengajuan' => KelengkapanKode::galatPengajuan($kegiatan, $tahun),
            'hilang' => KodeHilang::dari($kegiatan),
            'riwayat' => array_slice(array_reverse((array) ($kegiatan['riwayat_kode'] ?? [])), 0, self::BATAS_RIWAYAT),
            'dapat_diubah' => KunciKegiatan::dapatDiubah($kegiatan),
        ];
    }
}
