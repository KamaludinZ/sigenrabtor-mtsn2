<?php

namespace App\Support\Rab;

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

        $perKategori = ['bahan' => 0, 'jasa' => 0, 'lain' => 0];
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
}
