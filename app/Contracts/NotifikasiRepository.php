<?php

namespace App\Contracts;

/**
 * Penanda baca notifikasi status dokumen per akun (calon kolom/tabel notifikasi pengguna): notifikasi yang terjadi
 * sesudah waktu ini dihitung belum dibaca. Tahap frontend memakai berkas tiruan di disk local.
 */
interface NotifikasiRepository
{
    /** Waktu ISO 8601 terakhir akun menandai notifikasinya dibaca; null bila belum pernah. */
    public function dibacaSampai(int $penggunaId): ?string;

    public function tandaiDibaca(int $penggunaId, string $pada): void;
}
