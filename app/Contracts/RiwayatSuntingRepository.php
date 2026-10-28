<?php

namespace App\Contracts;

/**
 * Jejak sunting teks langsung di pratinjau (PRD F5, N7; tabel `riwayat_sunting_dokumen` migrasi 000041): hanya tambah,
 * satu catatan per penyimpanan teks yang berubah. Bentuk (disusun App\Support\Dokumen\RiwayatSunting::entri()): {id
 * (teks), kegiatan_id, bidang (SuntingDokumen::BIDANG), nomor_bagian, asal isian|teks_khusus, teks_lama|null,
 * teks_baru|null (null = kosong / teks baku), user_id|null, pengguna, dibuat_pada (ISO 8601 bermikrodetik zona
 * aplikasi)}. `catat()` melempar RuntimeException bila gagal ditulis.
 */
interface RiwayatSuntingRepository
{
    /**
     * @param  array{bidang: string, nomor_bagian: int, asal: string, teks_lama: string|null, teks_baru: string|null, user_id: int|null, pengguna: string}  $entri
     * @return array<string, mixed> Catatan tersimpan.
     */
    public function catat(int $kegiatanId, array $entri): array;

    /**
     * Catatan satu kegiatan (opsional satu bidang), terbaru dulu, paling banyak $batas.
     *
     * @return list<array<string, mixed>>
     */
    public function untukKegiatan(int $kegiatanId, ?string $bidang = null, int $batas = 50): array;
}
