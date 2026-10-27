<?php

namespace App\Contracts;

/**
 * Dasar hukum & latar belakang baku KAK/TOR per tahun anggaran (PRD F9, §13): butir dasar hukum berurutan (tercetak
 * bernomor di bagian 2), paragraf latar belakang baku (bagian 1, visi madrasah), dan kalimat penutup yang menyisipkan
 * nama kegiatan. Teks disimpan mentah dengan penanda `{tahun}`/`{nama_kegiatan}` — diisi lewat
 * App\Support\Pengaturan\TeksBaku. Tahap frontend memakai tiruan (berkas JSON); backend menggantinya dengan Eloquent
 * tanpa mengubah komponen.
 */
interface DasarHukumRepository
{
    /**
     * Pengaturan tahun $tahun (teks mentah). Tahun yang belum diatur: dasar hukum kosong, paragraf & kalimat penutup
     * bawaan, `diatur` false.
     *
     * @return array{dasar_hukum: list<array{id: string, teks: string}>, latar_belakang: string, kalimat_penutup: string, diatur: bool, diperbarui_pada: string|null}
     */
    public function untukTahun(int $tahun): array;

    /**
     * Simpan seluruh pengaturan tahun $tahun. Urutan butir = urutan cetak; butir tanpa id diberi id baru.
     *
     * @param  array{dasar_hukum: list<array{id?: string|null, teks: string}>, latar_belakang: string, kalimat_penutup: string}  $data
     */
    public function simpan(int $tahun, array $data): void;

    /**
     * Paragraf latar belakang baku & kalimat penutup bawaan (dipakai tahun yang belum diatur dan "Pakai teks bawaan").
     *
     * @return array{latar_belakang: string, kalimat_penutup: string}
     */
    public function teksBawaan(): array;
}
