<?php

namespace App\Support\Kegiatan;

/**
 * Permintaan saran kalimat ke PenyusunSaranKalimat: bagian (kunci BagianAi::BAGIAN), teks yang sedang tertulis, instruksi
 * perbaikan (opsional), bahan bagian itu (BagianAi::bahan, label → nilai terisi), dan bahan umum KonteksAi::teks()
 * (isian kegiatan + catatan konteks yang NIP-nya sudah disembunyikan), serta ketersediaan konteks bagian itu
 * (KetersediaanKonteks: aspek catatan yang belum ditulis + arahan agar AI tidak mengarang rinciannya).
 */
final readonly class PermintaanSaranKalimat
{
    /**
     * @param  array<string, string>  $bahanBagian
     * @param  list<string>  $aspekKurang  Aspek catatan (kunci KonteksAi::ASPEK) yang dipakai bagian ini tetapi belum ditulis.
     */
    public function __construct(
        public int $kegiatanId,
        public string $bagian,
        public string $teks,
        public string $instruksi,
        public array $bahanBagian,
        public string $bahan,
        public string $namaKegiatan,
        public array $aspekKurang = [],
        public ?string $arahanKonteks = null,
    ) {}
}
