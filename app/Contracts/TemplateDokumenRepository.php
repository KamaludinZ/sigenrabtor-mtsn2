<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

/**
 * Master dokumen .docx ber-variabel per tahun anggaran (PRD tabel document_templates): jenis KAK, TOR, KAK+TOR, atau
 * RAB, dengan daftar variabel yang dipakainya. Tepat satu default per jenis per tahun; variasi per leading menyusul.
 * Tahap frontend memakai tiruan (berkas JSON + .docx di disk "local"); backend menggantinya dengan Eloquent + Storage.
 */
interface TemplateDokumenRepository
{
    public const JENIS = ['KAK+TOR' => 'KAK/TOR (gabungan)', 'KAK' => 'KAK saja', 'TOR' => 'TOR saja', 'RAB' => 'RAB'];

    /**
     * Template satu tahun, default lebih dulu lalu terbaru.
     *
     * @return list<array{id: string, tahun: int, nama: string, jenis: string, file_path: string, nama_berkas: string, variabel: list<string>, is_default: bool, leading: string|null, diperbarui_pada: string}>
     */
    public function semua(int $tahun): array;

    /**
     * @return array{id: string, tahun: int, nama: string, jenis: string, file_path: string, nama_berkas: string, variabel: list<string>, is_default: bool, leading: string|null, diperbarui_pada: string}|null
     */
    public function temukan(string $id): ?array;

    /**
     * Simpan master baru (berkasnya ikut disimpan); template pertama suatu jenis pada tahun itu menjadi default.
     *
     * @param  list<string>  $variabel  Variabel terdaftar yang dipakai.
     */
    public function tambah(int $tahun, string $nama, string $jenis, UploadedFile $berkas, array $variabel): string;

    /** Hapus template beserta berkasnya; default jenis itu pindah ke template terbaru berikutnya. */
    public function hapus(string $id): void;
}
