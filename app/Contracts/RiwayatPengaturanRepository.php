<?php

namespace App\Contracts;

/**
 * Riwayat perubahan pengaturan admin (PRD N7, tabel activity_logs tanpa activity_id): hanya bisa ditambah, tidak diubah
 * atau dihapus. Tahap frontend memakai tiruan (berkas JSON); backend menggantinya dengan Eloquent.
 */
interface RiwayatPengaturanRepository
{
    /** Tambah satu entri atas nama pengguna yang sedang masuk, pada tahun anggaran aktif. */
    public function catat(string $aksi, string $keterangan): void;

    /**
     * Semua entri, terbaru lebih dulu.
     *
     * @return list<array{id: string, waktu: string, user_id: int, pengguna: string, aksi: string, keterangan: string, tahun: int}>
     */
    public function semua(): array;
}
