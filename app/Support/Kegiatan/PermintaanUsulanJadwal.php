<?php

namespace App\Support\Kegiatan;

/**
 * Permintaan usulan jadwal ke PenyusunUsulanJadwal: jenis kegiatan, instruksi tambahan, bulan acuan dari Data Kegiatan,
 * bahan teks dari KonteksAi::teks() (isian kegiatan + catatan konteks yang NIP-nya sudah disembunyikan), dan nama
 * kegiatan untuk penanda {nama_kegiatan} template prompt.
 */
final readonly class PermintaanUsulanJadwal
{
    /**
     * @param  array{persiapan: int, pelaksanaan: list<int>, laporan: int}|null  $acuan  Null bila bulan pelaksanaan belum diisi.
     */
    public function __construct(
        public int $kegiatanId,
        public string $jenis,
        public string $instruksi,
        public ?array $acuan,
        public string $bahan,
        public string $namaKegiatan = '',
    ) {}
}
