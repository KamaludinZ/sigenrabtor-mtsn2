<?php

namespace App\Contracts;

/**
 * Tahun anggaran (PRD tabel tahun_anggaran, F6): tepat satu tahun aktif yang menentukan tahun di seluruh dokumen,
 * dasar hukum, pejabat penanda tangan, dan master kode. Tahap frontend memakai tiruan (berkas JSON); backend
 * menggantinya dengan Eloquent tanpa mengubah komponen.
 */
interface TahunAnggaranRepository
{
    /**
     * Semua tahun anggaran, terbaru lebih dulu.
     *
     * @return list<array{id: int, tahun: int, is_active: bool, is_locked: bool, no_sk_rktm: string|null, tanggal_sk_rktm: string|null, diaktifkan_oleh: string|null, diaktifkan_pada: string|null}>
     */
    public function semua(): array;

    /**
     * Tahun yang sedang aktif; null bila belum ada (aplikasi menampilkan data kosong).
     *
     * @return array{id: int, tahun: int, is_active: bool, is_locked: bool, no_sk_rktm: string|null, tanggal_sk_rktm: string|null, diaktifkan_oleh: string|null, diaktifkan_pada: string|null}|null
     */
    public function aktif(): ?array;

    /**
     * Jadikan $tahun satu-satunya tahun aktif dan catat siapa & kapan (diaktifkan_oleh/diaktifkan_pada).
     *
     * @throws \InvalidArgumentException Tahun belum dibuat.
     */
    public function aktifkan(int $tahun, string $oleh): void;
}
