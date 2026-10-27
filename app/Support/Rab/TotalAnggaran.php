<?php

namespace App\Support\Rab;

use App\Support\Terbilang;

/**
 * Data total anggaran satu kegiatan (PRD F2–F3: total dihitung, terbilang otomatis) untuk endpoint
 * `kegiatan.total_anggaran`: per sumber dana yang dipilih — total RAB beserta terbilang & tulisan dokumen
 * ("Rp10.000.000,- (Sepuluh Juta Rupiah)"), pagu & terbilangnya, status pagu (StatusPagu), jumlah rincian, dan termin
 * (MekanismePembayaran: status + tiap baris dengan terbilang & tulisan dokumennya) — serta total gabungan. Semua angka
 * dari AnggaranKegiatan dan semua terbilang dari Terbilang::tampil()/denganAngka(), sama dengan layar & dokumen.
 */
final class TotalAnggaran
{
    /**
     * @param  array<string, mixed>  $kegiatan  Data KegiatanRepository.
     * @return array{kegiatan_id: int, nama_kegiatan: string, total: int, terbilang: string|null, dokumen: string|null, sumber: list<array<string, mixed>>}
     */
    public static function untuk(array $kegiatan): array
    {
        $sumber = array_map(function (AnggaranSumber $a) {
            $pagu = $a->keteranganPagu();
            $termin = MekanismePembayaran::dari($a);

            return [
                'sumber' => $a->sumber,
                'total' => $a->total(),
                'terbilang' => $a->terbilang(),
                'dokumen' => $a->terbilang() === null ? null : Terbilang::denganAngka($a->total()),
                'jumlah_item' => $a->jumlahItem(),
                'pagu' => $a->pagu(),
                'pagu_terbilang' => Terbilang::tampil($a->pagu()),
                'status_pagu' => $pagu->status,
                'selisih_pagu' => $pagu->selisih,
                'keterangan_pagu' => $pagu->teks(),
                'termin' => [
                    'jumlah' => count($a->termin),
                    'total' => $a->totalTermin(),
                    'status' => $termin->status(),
                    'keterangan' => $termin->teksStatus(),
                    'baris' => array_map(fn (array $b) => [
                        'nomor' => $b['nomor'],
                        'tanggal' => $b['tanggal']?->toDateString(),
                        'nominal' => $b['nominal'],
                        'terbilang' => $b['terbilang'],
                        'dokumen' => $b['terbilang'] === null ? null : Terbilang::denganAngka($b['nominal']),
                        'persen' => $b['persen'],
                    ], $termin->barisTabel()),
                ],
            ];
        }, AnggaranKegiatan::untuk($kegiatan));

        $total = array_sum(array_column($sumber, 'total'));

        return [
            'kegiatan_id' => (int) $kegiatan['id'],
            'nama_kegiatan' => (string) ($kegiatan['nama_kegiatan'] ?? ''),
            'total' => $total,
            'terbilang' => Terbilang::tampil($total),
            'dokumen' => $total === 0 ? null : Terbilang::denganAngka($total),
            'sumber' => $sumber,
        ];
    }
}
