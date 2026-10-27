<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

/**
 * Arsip final PDF bertanda tangan/pindaian per kegiatan × sumber dana (PRD F18, calon tabel `arsip_final`): berkas,
 * pengunggah, dan waktu unggah tercatat. Tahap frontend memakai berkas tiruan di disk local.
 */
interface ArsipFinalRepository
{
    /**
     * Arsip satu kegiatan, berkunci sumber dana.
     *
     * @return array<string, array{sumber: string, nama_berkas: string, ukuran: int, path: string, diunggah_oleh: string, diunggah_oleh_id: int|null, diunggah_pada: string}>
     */
    public function untukKegiatan(int $kegiatanId): array;

    /**
     * Simpan (atau ganti) arsip satu sumber dana; berkas lama dihapus.
     *
     * @param  array{id: int|null, name: string}  $pengguna  Pengunggah.
     * @return array{sumber: string, nama_berkas: string, ukuran: int, path: string, diunggah_oleh: string, diunggah_oleh_id: int|null, diunggah_pada: string}
     */
    public function simpan(int $kegiatanId, string $sumber, UploadedFile $berkas, array $pengguna): array;

    /** Hapus arsip satu sumber dana beserta berkasnya (tidak apa-apa bila belum ada). */
    public function hapus(int $kegiatanId, string $sumber): void;
}
