<?php

namespace App\Support\Rab;

use App\Support\Format;
use App\Support\Terbilang;

/**
 * Data termin pembayaran satu kegiatan (PRD F11 "Termin Pembayaran", aturan 6) untuk endpoint `kegiatan.termin`: per
 * sumber dana yang dipilih — total RAB, jumlah & total termin, selisih, status & teksnya (MekanismePembayaran), tiap
 * termin (nomor, tanggal ISO & terbaca, nominal, terbilang & tulisan dokumen, persen, kumulatif), nominal per bulan,
 * serta baris blok "Mekanisme Pembayaran" persis seperti tercetak di RAB (kosong bila dibayar sekaligus).
 */
final class DataTermin
{
    /**
     * @param  array<string, mixed>  $kegiatan  Data KegiatanRepository.
     * @return array{kegiatan_id: int, nama_kegiatan: string, sumber: list<array<string, mixed>>}
     */
    public static function untuk(array $kegiatan): array
    {
        return [
            'kegiatan_id' => (int) $kegiatan['id'],
            'nama_kegiatan' => (string) ($kegiatan['nama_kegiatan'] ?? ''),
            'sumber' => array_map(self::sumber(...), AnggaranKegiatan::untuk($kegiatan)),
        ];
    }

    /** @return array<string, mixed> */
    private static function sumber(AnggaranSumber $a): array
    {
        $mekanisme = MekanismePembayaran::dari($a);

        return [
            'sumber' => $a->sumber,
            'total_rab' => $a->total(),
            'jumlah' => count($a->termin),
            'total_termin' => $mekanisme->jumlah(),
            'selisih' => $mekanisme->selisih(),
            'status' => $mekanisme->status(),
            'keterangan' => $mekanisme->teksStatus(),
            'termin' => array_map(fn (array $b) => [
                'nomor' => $b['nomor'],
                'tanggal' => $b['tanggal']?->toDateString(),
                'tanggal_terbaca' => Format::tanggalPanjang($b['tanggal']?->toDateString()),
                'nominal' => $b['nominal'],
                'terbilang' => $b['terbilang'],
                'dokumen' => $b['terbilang'] === null ? null : Terbilang::denganAngka($b['nominal']),
                'persen' => $b['persen'],
                'kumulatif' => $b['kumulatif'],
            ], $mekanisme->barisTabel()),
            'per_bulan' => array_map(fn (int $bulan, int $nominal) => ['bulan' => $bulan === 0 ? null : $bulan, 'nominal' => $nominal], array_keys($mekanisme->perBulan()), $mekanisme->perBulan()),
            'dokumen' => $mekanisme->barisDokumen(),
        ];
    }
}
