<?php

namespace App\Support\Kegiatan;

/**
 * Permintaan saran kalimat ke PenyusunSaranKalimat: bagian (kunci BagianAi::BAGIAN), teks yang sedang tertulis, instruksi
 * perbaikan (opsional), bahan bagian itu (BagianAi::bahan, label → nilai terisi), dan bahan umum KonteksAi::teks()
 * (isian kegiatan + catatan konteks yang NIP-nya sudah disembunyikan).
 */
final readonly class PermintaanSaranKalimat
{
    /**
     * @param  array<string, string>  $bahanBagian
     */
    public function __construct(
        public int $kegiatanId,
        public string $bagian,
        public string $teks,
        public string $instruksi,
        public array $bahanBagian,
        public string $bahan,
        public string $namaKegiatan,
    ) {}
}
