<?php

namespace App\Support\Rab;

use App\Support\Format;

/**
 * Batas pagu di server (PRD aturan 5: "total RAB tidak boleh melebihi pagu kegiatan; peringatan bila melebihi atau
 * berbeda"). Selama menyusun, kondisi pagu hanya peringatan (PeringatanPagu, cap DRAF); RAB sumber dana yang dipilih
 * yang melebihi pagunya menghalangi pengajuan (Data Kegiatan) dan persetujuan (Pemeriksaan). Pagu belum diisi atau
 * masih bersisa tidak menghalangi.
 */
final class BatasPagu
{
    /**
     * RAB sumber dana terpilih yang melebihi pagunya.
     *
     * @param  iterable<AnggaranSumber>  $anggaran  Dari AnggaranKegiatan::untuk()/perSumber().
     * @return array<string, string> Sumber dana → pesan.
     */
    public static function terlampaui(iterable $anggaran): array
    {
        $galat = [];
        foreach ($anggaran as $a) {
            if ($a->dipilih && $a->keteranganPagu()->melebihi()) {
                $galat[$a->sumber] = self::pesan($a);
            }
        }

        return $galat;
    }

    /** "RAB Komite Rp7.950.000 melebihi pagu Rp7.500.000 sebesar Rp450.000 — …". */
    public static function pesan(AnggaranSumber $a): string
    {
        return "RAB {$a->sumber} ".Format::rupiah($a->total()).' melebihi pagu '.Format::rupiah((int) $a->pagu())
            .' sebesar '.Format::rupiah(-(int) $a->keteranganPagu()->selisih).' — kurangi rincian di halaman RAB atau perbarui pagu.';
    }
}
