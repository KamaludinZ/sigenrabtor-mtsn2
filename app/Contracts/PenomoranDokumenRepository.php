<?php

namespace App\Contracts;

/**
 * Penomoran dokumen otomatis per tahun anggaran (PRD §9 "Penomoran Dokumen Otomatis"; aturannya lewat
 * App\Support\Dokumen\PenomoranDokumen): aturan yang diubah admin per jenis dokumen {pola, digit, aktif} (jenis yang tidak
 * tersimpan = aturan bawaan) dan nomor urut terakhir yang terpakai per jenis (calon tabel `number_sequences`).
 */
interface PenomoranDokumenRepository
{
    /** @return array<string, array{pola: string, digit: int, aktif: bool}> Aturan tersimpan per jenis. */
    public function aturan(int $tahun): array;

    /** @return array{diperbarui_pada: string|null} */
    public function meta(int $tahun): array;

    /**
     * @param  array<string, array{pola: string, digit: int, aktif: bool}>  $aturan
     *
     * @throws \RuntimeException Gagal ditulis.
     */
    public function simpanAturan(int $tahun, array $aturan): void;

    /** @return array<string, int> Nomor urut terakhir per jenis (belum ada = 0). */
    public function terakhir(int $tahun): array;

    /** Catat nomor urut terpakai (terakhir = maks(terakhir, $urut)). */
    public function catatPakai(int $tahun, string $jenis, int $urut): void;

    /**
     * Ambil nomor urut berikutnya jenis × tahun secara atomik (terakhir + 1, langsung tercatat) — dua permintaan bersamaan
     * tidak pernah mendapat nomor yang sama.
     *
     * @throws \RuntimeException Gagal ditulis.
     */
    public function ambilBerikutnya(int $tahun, string $jenis): int;
}
