<?php

namespace App\Contracts;

/**
 * Hitungan permintaan AI per pengguna per hari (PRD §21 "Batas Pemakaian"): dipakai BatasAi untuk menolak permintaan
 * setelah batas harian tercapai. Backend menghitungnya dari tabel ai_suggestions/antrean.
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
}
