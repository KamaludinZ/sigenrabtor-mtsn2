<?php

namespace App\Contracts;

/**
 * Riwayat penyusunan buku dokumen per leading × sumber dana (PRD tabel `buku_dokumen`: tahun, leading, sumber dana,
 * berkas PDF, jumlah kegiatan, pembuat, waktu). Tahap frontend memakai tiruan (berkas JSON, tanpa berkas PDF).
 */
interface BukuDokumenRepository
{
    /**
     * Buku yang pernah disusun pada tahun itu, terbaru dulu.
     *
     * @return list<array{id: string, tahun: int, leading: string, sumber: string, jumlah_kegiatan: int, halaman: int, ada_draf: bool, kegiatan: list<array{id: int, nama: string, halaman: int|null, hasil: string}>, file_pdf_path: string|null, dibuat_oleh: string, dibuat_pada: string}>
     */
    public function riwayat(int $tahun): array;

    /**
     * Catat satu hasil penyusunan buku.
     *
     * @param  array{tahun: int, leading: string, sumber: string, jumlah_kegiatan: int, halaman: int, ada_draf: bool, kegiatan: list<array<string, mixed>>, dibuat_oleh: string}  $buku
     * @return array<string, mixed> Buku tersimpan (dengan id & dibuat_pada).
     */
    public function catat(array $buku): array;

    /** @return array<string, mixed>|null */
    public function temukan(string $id): ?array;

    /**
     * Timpa sebagian kolom satu buku (mis. status pembuatan PDF, berkasnya).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function perbarui(string $id, array $data): array;
}
