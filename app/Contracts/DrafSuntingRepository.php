<?php

namespace App\Contracts;

/**
 * Draf sunting teks di pratinjau yang belum diterapkan (PRD F5, N5; tabel `draf_sunting_dokumen` migrasi 000042): satu
 * draf per kegiatan × akun × bidang SuntingDokumen::BIDANG, menimpa yang sama. Bentuk: {id (teks), kegiatan_id, user_id,
 * bidang, teks, teks_awal|null, dibuat_pada, diperbarui_pada (ISO 8601 bermikrodetik zona aplikasi; = versi draf)}.
 * `simpan()`/`hapus()` melempar RuntimeException bila gagal ditulis.
 */
interface DrafSuntingRepository
{
    /**
     * Draf satu akun untuk kegiatan itu, urut SuntingDokumen::BIDANG.
     *
     * @return list<array<string, mixed>>
     */
    public function untuk(int $kegiatanId, int $userId): array;

    /** @return array<string, mixed>|null */
    public function temukan(int $kegiatanId, int $userId, string $bidang): ?array;

    /**
     * Simpan (atau timpa) draf; `teks_awal` & `dibuat_pada` draf yang ditimpa tetap.
     *
     * @return array<string, mixed>
     */
    public function simpan(int $kegiatanId, int $userId, string $bidang, string $teks, ?string $teksAwal): array;

    /** @return bool false bila draf tidak ada. */
    public function hapus(int $kegiatanId, int $userId, string $bidang): bool;
}
