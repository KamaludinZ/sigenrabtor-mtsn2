<?php

namespace App\Support\Stub;

/**
 * Rincian RAB tiruan untuk tahap frontend (PRD tabel budget_items, satu daftar per sumber dana).
 *
 * - Kegiatan 1 (Komite) mengikuti "Form RAB Workshop" madrasah: 15 baris, total Rp10.000.000, dua termin.
 * - Kegiatan 2 (DIPA + Komite) contoh RAB terpisah per sumber dana: DIPA Rp5.400.000 dari pagu Rp6.000.000,
 *   Komite Rp2.500.000 tepat pagu. Kode akun memakai akun belanja yang lazim (5212xx/5241xx).
 */
class StubRab
{
    /** Kegiatan contoh → sumber dana yang punya RAB contoh. */
    public const DAFTAR_CONTOH = [1 => ['Komite'], 2 => ['DIPA', 'Komite']];

    /**
     * @return list<array{kode: string, uraian: string, kategori: string, volume: float|int, satuan: string, harga_satuan: int}>
     */
    public static function rincian(int $kegiatanId, string $sumber): array
    {
        return match ("{$kegiatanId}:{$sumber}") {
            '1:Komite' => self::workshopKurikulum(),
            '2:DIPA' => [
                ['kode' => '521211', 'uraian' => 'Penggandaan instrumen supervisi akademik', 'kategori' => 'bahan', 'volume' => 60, 'satuan' => 'eksemplar', 'harga_satuan' => 7_500],
                ['kode' => '521211', 'uraian' => 'Konsumsi rapat koordinasi tim supervisor', 'kategori' => 'bahan', 'volume' => 12, 'satuan' => 'kotak', 'harga_satuan' => 30_000],
                ['kode' => '521213', 'uraian' => 'Honorarium tim supervisor (6 orang x 4 kali)', 'kategori' => 'jasa', 'volume' => 24, 'satuan' => 'OK', 'harga_satuan' => 150_000],
                ['kode' => '521211', 'uraian' => 'ATK pelaksanaan supervisi', 'kategori' => 'bahan', 'volume' => 1, 'satuan' => 'paket', 'harga_satuan' => 590_000],
                ['kode' => '524113', 'uraian' => 'Transport pengawas madrasah', 'kategori' => 'lain', 'volume' => 2, 'satuan' => 'OK', 'harga_satuan' => 150_000],
                ['kode' => '521211', 'uraian' => 'Penjilidan laporan hasil supervisi', 'kategori' => 'bahan', 'volume' => 4, 'satuan' => 'eksemplar', 'harga_satuan' => 25_000],
            ],
            '2:Komite' => [
                ['kode' => '521211', 'uraian' => 'Konsumsi refleksi hasil supervisi guru', 'kategori' => 'bahan', 'volume' => 56, 'satuan' => 'kotak', 'harga_satuan' => 25_000],
                ['kode' => '521213', 'uraian' => 'Honorarium narasumber refleksi pembelajaran', 'kategori' => 'jasa', 'volume' => 2, 'satuan' => 'OJ', 'harga_satuan' => 400_000],
                ['kode' => '521219', 'uraian' => 'Piagam penghargaan guru terbaik', 'kategori' => 'lain', 'volume' => 3, 'satuan' => 'lembar', 'harga_satuan' => 100_000],
            ],
            default => [],
        };
    }

    /**
     * Termin pembayaran contoh (Form RAB Workshop: Rp7.680.000 + Rp2.320.000 = total Rp10.000.000).
     *
     * @return list<array{tanggal: string, nominal: int}>
     */
    public static function termin(int $kegiatanId, string $sumber): array
    {
        $tahun = StubData::tahunAktif()['tahun'];

        return $kegiatanId === 1 && $sumber === 'Komite'
            ? [['tanggal' => "{$tahun}-06-17", 'nominal' => 7_680_000], ['tanggal' => "{$tahun}-06-19", 'nominal' => 2_320_000]]
            : [];
    }

    /**
     * @return list<array{kode: string, uraian: string, kategori: string, volume: float|int, satuan: string, harga_satuan: int}>
     */
    private static function workshopKurikulum(): array
    {
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
