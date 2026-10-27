<?php

namespace App\Contracts;

/**
 * Pembuatan PDF buku dokumen (PRD "Mesin Dokumen" & "Antrean": susun dokumen tiap kegiatan, ubah ke PDF, gabung, nomori
 * halaman — berjalan di belakang). Statusnya disimpan di catatan buku (BukuDokumenRepository, kunci `pdf`:
 * {status: antre|proses|selesai|gagal, tahap, persen, pesan}) lalu dipantau layar. Tahap frontend memakai tiruan yang
 * maju satu tahap tiap dipantau; backend menggantinya dengan job antrean + LibreOffice.
 */
interface PembuatPdfBuku
{
    /**
     * Mulai membuat PDF satu buku; mengembalikan catatan buku baru berstatus antre.
     *
     * @param  array{tahun: int, leading: string, sumber: string, jumlah_kegiatan: int, halaman: int, ada_draf: bool, kegiatan: list<array{id: int, nama: string, halaman: int|null, hasil: string}>, dibuat_oleh: string}  $buku
     * @return array<string, mixed>
     */
    public function mulai(array $buku): array;

    /**
     * Status terbaru pembuatan PDF buku $id (tiruan: sekaligus memajukan satu tahap).
     *
     * @return array<string, mixed>
     */
    public function pantau(string $id): array;
}
