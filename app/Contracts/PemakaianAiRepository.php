<?php

namespace App\Contracts;

/**
 * Hitungan permintaan AI per pengguna per hari per fitur (PRD §21 "Batas Pemakaian", tabel ai_usage): dipakai BatasAi
 * untuk menolak permintaan setelah batas harian tercapai. Jatah diambil atomik sebelum permintaan dikirim
 * (`ambilJatah`) dan dikembalikan bila penyedia gagal (`kembalikan`). Tanggal = tanggal zona aplikasi.
 */
interface PemakaianAiRepository
{
    /** Banyaknya permintaan AI $userId pada $tanggal (Y-m-d). */
    public function jumlah(int $userId, string $tanggal): int;

    /**
     * Rincian permintaan AI $userId pada $tanggal per fitur.
     *
     * @return array<string, int>
     */
    public function rincian(int $userId, string $tanggal): array;

    /** Catat satu permintaan AI $userId hari ini ($fitur: kunci BagianAi atau "usul_jadwal"). */
    public function catat(int $userId, string $fitur): void;

    /**
     * Ambil satu jatah permintaan hari ini: dicatat hanya bila jumlah hari ini (semua fitur) masih di bawah $batas —
     * diperiksa & dicatat sekaligus sehingga permintaan bersamaan tidak melewati batas. false = jatah habis.
     */
    public function ambilJatah(int $userId, string $fitur, int $batas): bool;

    /** Kembalikan satu jatah $fitur hari ini (permintaan yang gagal tidak dihitung); tanpa catatan = tidak berubah. */
    public function kembalikan(int $userId, string $fitur): void;
}
