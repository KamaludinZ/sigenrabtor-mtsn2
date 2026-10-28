<?php

namespace App\Contracts;

/**
 * Impor kegiatan bertahap beserta baris draf pratinjaunya (PRD §19 F22; tabel `impor_kegiatan` & `draf_impor_kegiatan`):
 * satu impor per berkas yang diperiksa (jenis excel|perkin, berkas & sheet, pengimpor, status berjalan → selesai/dibatalkan,
 * rekap siap/dilewati/gagal/dibuat), baris draf berstatus siap/dilewati/gagal dengan data kegiatan, pesan, peringatan,
 * dan kegiatan yang dibuat. Tahap frontend memakai tiruan JSON.
 */
interface ImporKegiatanRepository
{
    /**
     * Catat impor baru (status berjalan) beserta baris drafnya; rekap dihitung dari baris.
     *
     * @param  array{tahun: int, jenis: string, nama_berkas: string, lembar: string|null, user_id: int|null, pengguna: string}  $impor
     * @param  list<array{nomor_baris: int, status: string, data: array<string, mixed>, pesan: list<string>, peringatan: list<string>}>  $baris
     * @return array<string, mixed> Impor tersimpan (id uuid, rekap, dibuat_pada).
     */
    public function mulai(array $impor, array $baris): array;

    /** @return array<string, mixed>|null */
    public function temukan(string $id): ?array;

    /**
     * Baris draf impor, urut nomor baris berkas.
     *
     * @return list<array{nomor_baris: int, status: string, data: array<string, mixed>, pesan: list<string>, peringatan: list<string>, kegiatan_id: int|null, diproses_pada: string|null}>
     */
    public function baris(string $id): array;

    /** Tandai baris siap $nomor sudah disimpan menjadi kegiatan $kegiatanId (rekap `jumlah_dibuat` ikut naik). */
    public function tandaiDibuat(string $id, int $nomor, int $kegiatanId): void;

    /**
     * Ubah status baris yang belum menjadi kegiatan (siap → gagal/dilewati, pemeriksaan ulang) beserta pesannya; rekap
     * jumlah siap/dilewati/gagal ikut disesuaikan.
     *
     * @param  list<string>  $pesan
     */
    public function tandaiBaris(string $id, int $nomor, string $status, array $pesan): void;

    /** Akhiri impor: `selesai` atau `dibatalkan`. */
    public function akhiri(string $id, string $status): array;
}
