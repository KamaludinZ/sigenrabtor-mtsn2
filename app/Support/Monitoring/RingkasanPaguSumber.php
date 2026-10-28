<?php

namespace App\Support\Monitoring;

use App\Contracts\KegiatanRepository;
use App\Enums\SumberDana;

/**
 * Ringkasan pagu DIPA vs Komite (PRD §11 "Ringkasan Tahun Aktif": total pagu DIPA vs Komite) untuk endpoint
 * `monitoring.pagu` — kegiatan yang cocok dengan saringan Monitoring (SaringanMonitoring, aturan sama dengan halaman
 * & ekspor), lalu dari RingkasanAnggaran: per sumber dana (pagu, total RAB, selisih & persen pemakaian — null bila ada
 * RAB tanpa pagu, porsi, kelebihan atas pagunya sendiri, kondisi RAB), gabungan, kombinasi sumber dana per kegiatan,
 * dan pagu & total RAB per leading per sumber dana (leading yang punya kegiatan).
 */
final class RingkasanPaguSumber
{
    /**
     * @param  array<string, mixed>  $nilai  Saringan mentah (parameter URL Monitoring).
     * @return array<string, mixed>
     */
    public static function untuk(array $nilai): array
    {
        $saringan = SaringanMonitoring::dari($nilai);
        $ringkasan = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), $saringan->untukRingkasan());
        $perbandingan = $ringkasan->perbandinganSumber();

        return [
            'tahun' => $saringan->tahunDipantau(),
            'saringan' => $saringan->query(),
            'label_saringan' => $saringan->label(),
            'rentang_terbalik' => $saringan->rentangTerbalik(),
            'jumlah_kegiatan' => $ringkasan->jumlahKegiatan(),
            'tanpa_jadwal' => $ringkasan->tanpaJadwal,
            'sumber' => array_map(fn (string $s) => ['sumber' => $s, 'keterangan' => SumberDana::from($s)->keterangan()]
                + RingkasanAnggaran::sel($perbandingan[$s]), SumberDana::urutan()),
            'gabungan' => RingkasanAnggaran::sel($ringkasan->total()),
            'kombinasi_sumber' => $ringkasan->kombinasiSumber(),
            'per_leading' => array_values(array_map(fn (array $b) => [
                'kode' => $b['kode'],
                'nama_unit' => $b['nama_unit'],
                'jumlah' => $b['jumlah'],
                'sumber' => array_map(fn (array $s) => RingkasanAnggaran::sel($s), $b['sumber']),
            ], array_filter($ringkasan->perLeading(false), fn (array $b) => $b['jumlah'] > 0))),
        ];
    }
}
