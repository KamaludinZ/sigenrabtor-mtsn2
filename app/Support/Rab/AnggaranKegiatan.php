<?php

namespace App\Support\Rab;

use App\Contracts\RabRepository;
use App\Enums\SumberDana;

/**
 * Menyusun anggaran per sumber dana satu kegiatan dari data kegiatan (sumber & pagu) dan RabRepository
 * (rincian & termin). Satu-satunya tempat menggabungkan keduanya agar layar RAB, Data Kegiatan, dan dokumen sama.
 */
class AnggaranKegiatan
{
    /** Urutan baku sumber dana di layar dan buku dokumen. */
    public const URUTAN_SUMBER = [SumberDana::Dipa->value, SumberDana::Komite->value];

    /**
     * Anggaran setiap sumber dana kegiatan, urut DIPA lalu Komite. Bila $sertakanTerlepas, RAB tersimpan milik sumber
     * yang sudah tidak dipilih ikut dikembalikan (dipilih = false) agar tidak hilang diam-diam.
     *
     * @param  array<string, mixed>  $kegiatan
     * @return list<AnggaranSumber>
     */
    public static function untuk(array $kegiatan, bool $sertakanTerlepas = false): array
    {
        $repo = app(RabRepository::class);
        $id = (int) ($kegiatan['id'] ?? 0);
        $pagu = array_column($kegiatan['sumber_dana'] ?? [], 'pagu', 'sumber');
        $daftar = array_keys($pagu);

        if ($sertakanTerlepas && $id) {
            $daftar = array_values(array_unique([...$daftar, ...$repo->sumberTersimpan($id)]));
        }

        usort($daftar, fn ($a, $b) => self::posisi($a) <=> self::posisi($b));

        return array_map(fn (string $sumber) => new AnggaranSumber(
            $sumber,
            KalkulatorRab::hitung($id ? $repo->rincian($id, $sumber) : [], isset($pagu[$sumber]) ? (int) $pagu[$sumber] : null),
            $id ? $repo->termin($id, $sumber) : [],
            array_key_exists($sumber, $pagu),
        ), $daftar);
    }

    /**
     * Anggaran per sumber berkunci nama sumber.
     *
     * @param  array<string, mixed>  $kegiatan
     * @return array<string, AnggaranSumber>
     */
    public static function perSumber(array $kegiatan): array
    {
        $hasil = [];

        foreach (self::untuk($kegiatan) as $anggaran) {
            $hasil[$anggaran->sumber] = $anggaran;
        }

        return $hasil;
    }

    /**
     * Jumlah gabungan semua sumber yang dipilih (untuk ringkasan kegiatan; dokumen tetap per sumber).
     *
     * @param  list<AnggaranSumber>  $anggaran
     * @return array{total: int, pagu: int, jumlah_item: int}
     */
    public static function gabungan(array $anggaran): array
    {
        $dipilih = array_filter($anggaran, fn (AnggaranSumber $a) => $a->dipilih);

        return [
            'total' => array_sum(array_map(fn (AnggaranSumber $a) => $a->total(), $dipilih)),
            'pagu' => array_sum(array_map(fn (AnggaranSumber $a) => (int) $a->pagu(), $dipilih)),
            'jumlah_item' => array_sum(array_map(fn (AnggaranSumber $a) => $a->jumlahItem(), $dipilih)),
        ];
    }

    /**
     * Tanda ringkas pagu, total, dan jumlah item tiap RAB (termasuk yang dilepas); berubah begitu salah satunya berubah.
     * Dipakai untuk menyiarkan perubahan anggaran ke tab lain dan membuat ulang komponen klien yang menyimpan total RAB.
     *
     * @param  list<AnggaranSumber>  $anggaran
     */
    public static function tanda(array $anggaran): string
    {
        return md5((string) json_encode(array_map(fn (AnggaranSumber $a) => [$a->sumber, $a->dipilih, $a->pagu(), $a->total(), $a->jumlahItem()], $anggaran)));
    }

    private static function posisi(string $sumber): int
    {
        $posisi = array_search($sumber, self::URUTAN_SUMBER, true);

        return $posisi === false ? count(self::URUTAN_SUMBER) : $posisi;
    }
}
