<?php

namespace App\Contracts;

/**
 * Aturan nomor halaman buku per tahun anggaran (PRD tabel page_number_settings). Tahap frontend memakai tiruan
 * (berkas JSON); backend menggantinya dengan Eloquent.
 */
interface NomorHalamanRepository
{
    /**
     * Aturan tahun itu (NomorHalaman::BAWAAN bila belum pernah diubah).
     *
     * @return array<string, mixed>
     */
    public function untuk(int $tahun): array;

    /** @param array<string, mixed> $aturan Sudah dirapikan NomorHalaman::rapikan(). */
    public function simpan(int $tahun, array $aturan): void;
}
