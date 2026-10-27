<?php

namespace App\Support\Rab;

use App\Livewire\Forms\ItemBelanjaForm;

/**
 * Satu-satunya tempat perhitungan RAB (PRD F2 & aturan "Total dihitung, tidak diketik"):
 * jumlah = volume × harga satuan; total = Σ jumlah; total KAK = total RAB = jumlah yang diajukan.
 *
 * Volume disimpan hingga 2 desimal; perhitungan memakai bilangan bulat (volume × 100) agar bebas galat float,
 * lalu dibulatkan setengah-ke-atas ke rupiah penuh.
 */
class KalkulatorRab
{
    public static function jumlah(int|float $volume, int $hargaSatuan): int
    {
        $volumeSeratus = (int) round($volume * 100);
        $hasil = $volumeSeratus * $hargaSatuan;

        // Pembulatan setengah-ke-atas (simetris untuk nilai negatif, walau validasi menolak volume ≤ 0).
        return $hasil >= 0 ? intdiv($hasil + 50, 100) : -intdiv(-$hasil + 50, 100);
    }

    /**
     * Hitung seluruh rincian satu RAB.
     *
     * @param  list<array<string, mixed>>  $rincian
     */
    public static function hitung(array $rincian, ?int $pagu = null): HasilRab
    {
        $baris = array_map(
            fn (array $item) => ['jumlah' => self::jumlah($item['volume'] + 0, (int) $item['harga_satuan'])] + $item,
            $rincian,
        );

        $perKategori = array_fill_keys(array_keys(ItemBelanjaForm::LABEL_KATEGORI), 0);
        $perKode = [];

        foreach ($baris as $item) {
            $kategori = $item['kategori'] ?? 'lain';
            $perKategori[$kategori] = ($perKategori[$kategori] ?? 0) + $item['jumlah'];
            $kode = (string) ($item['kode'] ?? '');
            $perKode[$kode] = ($perKode[$kode] ?? 0) + $item['jumlah'];
        }

        ksort($perKode, SORT_STRING);

        return new HasilRab(
            rincian: $baris,
            total: array_sum(array_column($baris, 'jumlah')),
            perKategori: $perKategori,
            perKode: $perKode,
            pagu: $pagu,
        );
    }

    /**
     * Rekap gabungan beberapa RAB (mis. satu sumber dana dari banyak kegiatan): rincian disambung berurutan, total,
     * subtotal per kelompok belanja (urut LABEL_KATEGORI), dan per kode akun (urut kode) dijumlahkan; tanpa pagu.
     *
     * @param  iterable<HasilRab>  $hasil
     */
    public static function gabung(iterable $hasil): HasilRab
    {
        $rincian = [];
        $perKategori = array_fill_keys(array_keys(ItemBelanjaForm::LABEL_KATEGORI), 0);
        $perKode = [];

        foreach ($hasil as $h) {
            array_push($rincian, ...$h->rincian);
            foreach ($h->perKategori as $kunci => $nilai) {
                $perKategori[$kunci] = ($perKategori[$kunci] ?? 0) + $nilai;
            }
            foreach ($h->perKode as $kunci => $nilai) {
                $perKode[$kunci] = ($perKode[$kunci] ?? 0) + $nilai;
            }
        }

        ksort($perKode, SORT_STRING);

        return new HasilRab($rincian, array_sum(array_column($rincian, 'jumlah')), $perKategori, $perKode);
    }
}
