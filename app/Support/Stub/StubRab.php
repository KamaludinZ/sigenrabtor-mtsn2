<?php

namespace App\Support\Stub;

/**
 * Rincian RAB tiruan untuk tahap frontend (PRD tabel budget_items, satu daftar per sumber dana).
 *
 * Contoh untuk kegiatan id 1 (Komite) mengikuti "Form RAB Workshop" madrasah: 15 baris, total Rp10.000.000.
 */
class StubRab
{
    /**
     * @return list<array{kode: string, uraian: string, kategori: string, volume: float|int, satuan: string, harga_satuan: int}>
     */
    public static function rincian(int $kegiatanId, string $sumber): array
    {
        if ($kegiatanId !== 1 || $sumber !== 'Komite') {
            return [];
        }

        return [
            ['kode' => '521213', 'uraian' => 'Honorarium Pemateri', 'kategori' => 'jasa', 'volume' => 6, 'satuan' => 'OJ', 'harga_satuan' => 600_000],
            ['kode' => '521211', 'uraian' => 'Nasi Kotak Pemateri dan Pengawas madrasah', 'kategori' => 'bahan', 'volume' => 2, 'satuan' => 'kotak', 'harga_satuan' => 30_000],
            ['kode' => '521211', 'uraian' => 'Nasi Kotak Kamad dan Ka. TU', 'kategori' => 'bahan', 'volume' => 2, 'satuan' => 'kotak', 'harga_satuan' => 30_000],
            ['kode' => '521211', 'uraian' => 'Nasi Kotak Guru', 'kategori' => 'bahan', 'volume' => 54, 'satuan' => 'kotak', 'harga_satuan' => 30_000],
            ['kode' => '521211', 'uraian' => 'Kue Kotak Pemateri dan Pengawas madrasah', 'kategori' => 'bahan', 'volume' => 2, 'satuan' => 'kotak', 'harga_satuan' => 15_000],
            ['kode' => '521211', 'uraian' => 'Kue Kotak Kamad dan Ka. TU', 'kategori' => 'bahan', 'volume' => 2, 'satuan' => 'kotak', 'harga_satuan' => 15_000],
            ['kode' => '521211', 'uraian' => 'Kue Kotak Guru', 'kategori' => 'bahan', 'volume' => 54, 'satuan' => 'kotak', 'harga_satuan' => 15_000],
            ['kode' => '521211', 'uraian' => 'ATK peserta workshop', 'kategori' => 'bahan', 'volume' => 56, 'satuan' => 'pack', 'harga_satuan' => 15_000],
            ['kode' => '521211', 'uraian' => 'Fotokopi modul', 'kategori' => 'bahan', 'volume' => 56, 'satuan' => 'pack', 'harga_satuan' => 5_000],
            ['kode' => '524113', 'uraian' => 'Transport Pengawas', 'kategori' => 'lain', 'volume' => 1, 'satuan' => 'OK', 'harga_satuan' => 150_000],
            ['kode' => '521211', 'uraian' => 'Air Mineral', 'kategori' => 'bahan', 'volume' => 3, 'satuan' => 'dus', 'harga_satuan' => 40_000],
            ['kode' => '521211', 'uraian' => 'Jilid Laporan', 'kategori' => 'bahan', 'volume' => 2, 'satuan' => 'kali', 'harga_satuan' => 40_000],
            ['kode' => '521213', 'uraian' => 'Penyelesaian tugas penyusunan perangkat di luar jam kerja (Kamad)', 'kategori' => 'jasa', 'volume' => 2, 'satuan' => 'OJ', 'harga_satuan' => 30_000],
            ['kode' => '521213', 'uraian' => 'Penyelesaian tugas penyusunan perangkat di luar jam kerja (6 orang: Ka. TU, 4 Waka, TPM)', 'kategori' => 'jasa', 'volume' => 12, 'satuan' => 'OJ', 'harga_satuan' => 25_000],
            ['kode' => '521213', 'uraian' => 'Penyelesaian tugas penyusunan perangkat di luar jam kerja (49 guru)', 'kategori' => 'jasa', 'volume' => 98, 'satuan' => 'OJ', 'harga_satuan' => 20_000],
        ];
    }
}
