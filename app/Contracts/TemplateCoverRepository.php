<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

/**
 * Desain cover per tahun anggaran (PRD tabel cover_templates, Fase 5 "Template Cover"): gambar JPG/PNG beserta kotak
 * teks di atasnya (TeksCover). Tepat satu cover default per tahun; variasi per leading & sumber dana menyusul.
 * Tahap frontend memakai tiruan (berkas JSON + gambar di disk "local"); backend menggantinya dengan Eloquent + Storage.
 */
interface TemplateCoverRepository
{
    /**
     * Cover satu tahun anggaran, default lebih dulu lalu terbaru.
     *
     * @return list<array{id: string, tahun: int, nama: string, gambar_path: string, lebar: int, tinggi: int, teks_items: list<array<string, mixed>>, is_default: bool, leading: string|null, sumber_dana: string|null, diperbarui_pada: string}>
     */
    public function semua(int $tahun): array;

    /**
     * @return array{id: string, tahun: int, nama: string, gambar_path: string, lebar: int, tinggi: int, teks_items: list<array<string, mixed>>, is_default: bool, leading: string|null, sumber_dana: string|null, diperbarui_pada: string}|null
     */
    public function temukan(string $id): ?array;

    /**
     * Simpan cover baru dari gambar yang sudah diunggah (berkasnya ikut disimpan); cover pertama tahun itu menjadi default.
     *
     * @param  list<array<string, mixed>>  $teksItems
     */
    public function tambah(int $tahun, string $nama, UploadedFile $gambar, array $teksItems): string;

    /** Hapus cover beserta gambarnya; bila cover default yang dihapus, cover terbaru berikutnya menjadi default. */
    public function hapus(string $id): void;
}
