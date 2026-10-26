<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

/**
 * Profil madrasah (PRD tabel profil_madrasah, satu baris): identitas untuk kop & dokumen — nama, nama singkat, kota,
 * alamat, telepon, visi, dan logo. Tahap frontend memakai tiruan (berkas JSON + gambar di disk local); backend
 * menggantinya dengan Eloquent tanpa mengubah komponen.
 */
interface ProfilMadrasahRepository
{
    /**
     * @return array{nama: string, nama_singkat: string, kota: string, alamat: string|null, telepon: string|null, visi: string|null, logo_path: string|null, diperbarui_pada: string|null}
     */
    public function ambil(): array;

    /**
     * Simpan isian teks profil (nama, nama_singkat, kota, alamat, telepon, visi); kosong = null.
     *
     * @param  array<string, string|null>  $isian
     */
    public function simpan(array $isian): void;

    /** Ganti logo kop (berkas lama dihapus). */
    public function simpanLogo(UploadedFile $logo): void;

    public function hapusLogo(): void;
}
