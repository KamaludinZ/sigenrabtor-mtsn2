<?php

namespace App\Contracts;

/**
 * Draf catatan pemeriksaan admin per kegiatan (calon tabel `catatan_pemeriksaan`): catatan yang sedang ditulis sebelum
 * keputusan dikirim — tetap ada bila halaman ditutup dan terlihat admin lain, lalu dibuang begitu keputusan tersimpan
 * (catatannya pindah ke entri riwayat_status). Satu draf per kegiatan; menyimpan lagi menimpanya.
 */
interface CatatanPemeriksaanRepository
{
    /**
     * @return array{catatan: string, user_id: int|null, oleh: string, diperbarui_pada: string}|null Waktu ISO 8601
     *                                                                                               bermikrodetik zona aplikasi.
     */
    public function draf(int $kegiatanId): ?array;

    /**
     * @param  array{id: int, name: string}  $pengguna
     * @return array{catatan: string, user_id: int|null, oleh: string, diperbarui_pada: string}
     */
    public function simpan(int $kegiatanId, string $catatan, array $pengguna): array;

    public function hapus(int $kegiatanId): void;
}
