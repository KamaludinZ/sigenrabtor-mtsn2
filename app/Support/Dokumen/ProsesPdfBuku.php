<?php

namespace App\Support\Dokumen;

/**
 * Tahap pembuatan PDF buku (PembuatPdfBuku) untuk tampilan status: urutan tahap, persen kemajuan, dan keadaan tiap
 * langkah (selesai/berjalan/menunggu/gagal) dari status `pdf` catatan buku.
 */
final class ProsesPdfBuku
{
    /** Tahap → label (urutan = urutan kerja mesin dokumen). */
    public const TAHAP = [
        'antre' => 'Menunggu antrean',
        'susun' => 'Menyusun dokumen kegiatan',
        'konversi' => 'Mengubah ke PDF',
        'gabung' => 'Menggabungkan buku',
        'nomor' => 'Menomori halaman',
    ];

    /** Tahap sesudah $tahap; null bila sudah tahap terakhir. */
    public static function berikutnya(string $tahap): ?string
    {
        $urutan = array_keys(self::TAHAP);
        $i = array_search($tahap, $urutan, true);

        return $i === false ? null : ($urutan[$i + 1] ?? null);
    }

    /** Persen kemajuan saat tahap $tahap sedang berjalan (selesai = 100). */
    public static function persen(string $tahap, string $status): int
    {
        if ($status === 'selesai') {
            return 100;
        }
        $i = array_search($tahap, array_keys(self::TAHAP), true);

        return $i === false ? 0 : (int) round($i / count(self::TAHAP) * 100);
    }

    /**
     * Langkah untuk penanda tahap di layar.
     *
     * @param  array{status: string, tahap: string}  $pdf
     * @return list<array{kunci: string, label: string, keadaan: string}>
     */
    public static function langkah(array $pdf): array
    {
        $urutan = array_keys(self::TAHAP);
        $aktif = array_search($pdf['tahap'], $urutan, true);

        return array_map(fn (string $kunci, int $i) => [
            'kunci' => $kunci,
            'label' => self::TAHAP[$kunci],
            'keadaan' => match (true) {
                $pdf['status'] === 'selesai', $i < $aktif => 'selesai',
                $i === $aktif && $pdf['status'] === 'gagal' => 'gagal',
                $i === $aktif => 'berjalan',
                default => 'menunggu',
            },
        ], $urutan, array_keys($urutan));
    }

    /** Pembuatan masih berjalan (perlu dipantau). */
    public static function berjalan(?array $pdf): bool
    {
        return $pdf !== null && in_array($pdf['status'], ['antre', 'proses'], true);
    }
}
