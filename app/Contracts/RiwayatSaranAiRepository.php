<?php

namespace App\Contracts;

/**
 * Riwayat saran AI (PRD F25, tabel ai_suggestions): satu catatan per permintaan "Buat dengan AI"/usulan jadwal beserta
 * versinya — {id, kegiatan_id, bagian, prompt (bahan terkirim), instruksi, penyedia, user_id, pada, diputuskan_pada,
 * versi: [{judul, ciri, teks, status: menunggu|diterima|diedit|ditolak, teks_dipakai}]}. Hanya tambah & putuskan.
 * Backend menyimpannya sebagai baris ai_suggestions per versi yang berbagi id permintaan.
 */
interface RiwayatSaranAiRepository
{
    /**
     * Catat satu permintaan (semua versinya berstatus "menunggu"); mengembalikan id permintaan.
     *
     * @param  array{kegiatan_id: int, bagian: string, prompt: string, instruksi: string, penyedia: string|null, user_id: int, versi: list<array{judul: string, ciri: string, teks: string}>}  $permintaan
     */
    public function catat(array $permintaan): string;

    /**
     * Keputusan atas permintaan $id: versi $versi dipakai (diterima, atau diedit bila $teksDipakai berbeda dari
     * sarannya) dan versi lain ditolak; $versi null = semua ditolak.
     */
    public function putuskan(string $id, ?int $versi, ?string $teksDipakai = null): void;

    /**
     * Semua permintaan, terbaru dulu.
     *
     * @return list<array<string, mixed>>
     */
    public function semua(): array;
}
