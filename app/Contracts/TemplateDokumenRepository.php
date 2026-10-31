<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

/**
 * Master dokumen .docx ber-variabel per tahun anggaran (PRD tabel document_templates): jenis KAK, TOR, KAK+TOR, atau
 * RAB, dengan daftar variabel yang dipakainya. Pemilik `leading` (null = umum untuk semua leading; satu leading boleh punya
 * beberapa template per jenis), `catatan` versi, `aktif`; tepat satu default per tahun × jenis × pemilik.
 * Tahap frontend memakai tiruan (berkas JSON + .docx di disk "local"); backend menggantinya dengan Eloquent + Storage.
 */
interface TemplateDokumenRepository
{
    public const JENIS = ['KAK+TOR' => 'KAK/TOR (gabungan)', 'KAK' => 'KAK saja', 'TOR' => 'TOR saja', 'RAB' => 'RAB'];

    /**
     * Template satu tahun, default lebih dulu lalu terbaru.
     *
     * @return list<array{id: string, tahun: int, nama: string, jenis: string, file_path: string, nama_berkas: string, variabel: list<string>, is_default: bool, leading: string|null, catatan: string|null, aktif: bool, diperbarui_pada: string}>
     */
    public function semua(int $tahun): array;

    /**
     * @return array{id: string, tahun: int, nama: string, jenis: string, file_path: string, nama_berkas: string, variabel: list<string>, is_default: bool, leading: string|null, catatan: string|null, aktif: bool, diperbarui_pada: string}|null
     */
    public function temukan(string $id): ?array;

    /**
     * Simpan master baru (berkasnya ikut disimpan). Default: `default` true = menjadi default pemilik × jenis (default
     * lama dilepas), selain itu hanya bila pemilik × jenis tahun itu belum punya default.
     *
     * @param  list<string>  $variabel  Variabel terdaftar yang dipakai.
     * @param  array{leading?: string|null, catatan?: string|null, default?: bool}  $atribut  Pemilik (null = umum) & catatan versi.
     */
    public function tambah(int $tahun, string $nama, string $jenis, UploadedFile $berkas, array $variabel, array $atribut = []): string;

    /**
     * Ubah nama & catatan versi (null = tanpa catatan); berkas, jenis, pemilik tetap.
     *
     * @throws \RuntimeException Template tidak ada.
     */
    public function ubah(string $id, string $nama, ?string $catatan): void;

    /**
     * Jadikan default pemilik × jenis tahun itu; default lama pemilik × jenis itu dilepas.
     *
     * @throws \RuntimeException Template tidak ada.
     */
    public function jadikanDefault(string $id): void;

    /** Hapus template beserta berkasnya; default pemilik × jenis itu pindah ke template terbaru berikutnya. */
    public function hapus(string $id): void;
}
