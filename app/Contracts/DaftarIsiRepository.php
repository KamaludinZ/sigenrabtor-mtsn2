<?php

namespace App\Contracts;

/**
 * Konfigurasi halaman daftar isi buku per tahun anggaran (PRD tabel daftar_isi_templates): satu konfigurasi bawaan
 * untuk semua buku, dan pengaturan khusus per leading × sumber dana yang menimpanya. Tahap frontend memakai tiruan
 * (berkas JSON); backend menggantinya dengan Eloquent.
 */
interface DaftarIsiRepository
{
    /**
     * Konfigurasi bawaan tahun itu (DaftarIsi::BAWAAN bila belum pernah diubah).
     *
     * @return array<string, mixed>
     */
    public function bawaan(int $tahun): array;

    /**
     * Pengaturan khusus tahun itu, berkunci "leading|sumber".
     *
     * @return array<string, array<string, mixed>>
     */
    public function khusus(int $tahun): array;

    /** @param array<string, mixed> $konfigurasi Sudah dirapikan DaftarIsi::rapikan(). */
    public function simpanBawaan(int $tahun, array $konfigurasi): void;

    /** @param array<string, mixed> $konfigurasi Sudah dirapikan DaftarIsi::rapikan(). */
    public function simpanKhusus(int $tahun, string $leading, string $sumber, array $konfigurasi): void;

    /** Hapus pengaturan khusus sehingga buku itu kembali memakai bawaan. */
    public function hapusKhusus(int $tahun, string $leading, string $sumber): void;
}
