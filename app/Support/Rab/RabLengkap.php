<?php

namespace App\Support\Rab;

use App\Livewire\Forms\ItemBelanjaForm;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Terbilang;

/**
 * Data RAB lengkap satu kegiatan (PRD F11–F12, RAB per sumber dana) untuk endpoint `kegiatan.rab_lengkap`: tiap sumber
 * dana — termasuk RAB yang tersisa dari sumber yang tidak dipilih lagi (`dipilih: false`, tidak ikut total) — berisi
 * ringkasan TotalAnggaran::sumber() ditambah rincian (nomor urut cetak, kode & nama akun, kelompok, volume, satuan,
 * harga, jumlah hasil KalkulatorRab) serta subtotal per kelompok belanja dan per kode akun. Total kegiatan = sumber
 * yang dipilih, sama dengan dokumen.
 */
final class RabLengkap
{
    /**
     * @param  array<string, mixed>  $kegiatan  Data KegiatanRepository.
     * @return array<string, mixed>
     */
    public static function untuk(array $kegiatan): array
    {
        $sumber = array_map(fn (AnggaranSumber $a) => ['dipilih' => $a->dipilih] + TotalAnggaran::sumber($a) + [
            'rincian' => array_map(fn (array $item, int $i) => [
                'id' => $item['id'] ?? null,
                'nomor' => $i + 1,
                'kode' => (string) ($item['kode'] ?? ''),
                'akun' => ItemBelanjaForm::KODE_AKUN[$item['kode'] ?? ''] ?? null,
                'uraian' => $item['uraian'],
                'kategori' => $item['kategori'],
                'volume' => $item['volume'],
                'satuan' => $item['satuan'],
                'harga_satuan' => (int) $item['harga_satuan'],
                'jumlah' => $item['jumlah'],
            ], $a->hasil->rincian, array_keys($a->hasil->rincian)),
            'per_kategori' => array_map(fn (string $kunci, string $label) => ['kategori' => $kunci, 'label' => $label, 'total' => $a->hasil->perKategori[$kunci] ?? 0], array_keys(ItemBelanjaForm::LABEL_KATEGORI), ItemBelanjaForm::LABEL_KATEGORI),
            'per_kode' => array_map(fn (string $kode, int $total) => ['kode' => $kode, 'akun' => ItemBelanjaForm::KODE_AKUN[$kode] ?? null, 'total' => $total], array_map('strval', array_keys($a->hasil->perKode)), $a->hasil->perKode),
        ], AnggaranKegiatan::untuk($kegiatan, sertakanTerlepas: true));

        $total = array_sum(array_column(array_filter($sumber, fn (array $s) => $s['dipilih']), 'total'));

        return [
            'kegiatan_id' => (int) $kegiatan['id'],
            'nama_kegiatan' => (string) ($kegiatan['nama_kegiatan'] ?? ''),
            'tahun_anggaran' => TahunKegiatan::dari($kegiatan),
            'total' => $total,
            'terbilang' => Terbilang::tampil($total),
            'dokumen' => $total === 0 ? null : Terbilang::denganAngka($total),
            'sumber' => $sumber,
        ];
    }
}
