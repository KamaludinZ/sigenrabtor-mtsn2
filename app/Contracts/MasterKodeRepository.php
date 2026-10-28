<?php

namespace App\Contracts;

/**
 * Master kode per tahun anggaran (PRD F7 & Fitur 14): SK, IKSK, RKTM, EDM, dan 5 Budaya Mutu.
 *
 * Isi master berubah setiap tahun dan berasal dari unggahan Excel admin; setiap metode menerima tahun anggaran.
 * Tahap frontend memakai berkas tiruan per tahun; backend menggantinya dengan tabel kode_* per tahun_anggaran_id.
 */
interface MasterKodeRepository
{
    /**
     * Tahun anggaran yang sudah memiliki master kode.
     *
     * @return list<int>
     */
    public function tahunTersedia(): array;

    /**
     * @return list<array{kode: string, uraian: string}>
     */
    public function kodeSk(int $tahun): array;

    /**
     * @return list<array{kode: string, kode_sk: string, uraian: string}>
     */
    public function kodeIksk(int $tahun): array;

    /**
     * @return list<array{kode: string, snp: string, kegiatan: string, sub_kegiatan: string}>
     */
    public function kodeRktm(int $tahun): array;

    /**
     * @return list<array{kode: string, komponen: string, uraian: string}>
     */
    public function kodeEdm(int $tahun): array;

    /**
     * Nama komponen EDM per huruf, mis. ['A' => 'Kedisiplinan'].
     *
     * @return array<string, string>
     */
    public function komponenEdm(int $tahun): array;

    /**
     * @return list<array{nomor: int, nama: string}>
     */
    public function budayaMutu(int $tahun): array;

    /**
     * Ganti master tahun $tahun untuk bagian yang ada di $master — kunci `kode_sk`, `kode_iksk`, `kode_rktm`, `kode_edm`
     * (baris berbentuk sama dengan metode bacanya; urutan = urutan berkas), `komponen_edm` (huruf → nama), `budaya_mutu` —
     * seluruhnya atau tidak sama sekali. Bagian lain tahun itu tetap.
     *
     * @param  array<string, array<mixed>>  $master
     *
     * @throws \RuntimeException Penyimpanan gagal; master lama utuh.
     */
    public function ganti(int $tahun, array $master): void;

    /**
     * Catat satu unggahan Excel yang disimpan menjadi master (calon tabel master_kode_unggahan).
     *
     * @param  array{tahun: int, cakupan: string, nama_berkas: string, ringkasan: array<string, array<string, int>>, user_id: int|null, pengguna: string}  $unggahan
     * @return string Id unggahan.
     */
    public function catatUnggahan(array $unggahan): string;

    /**
     * Unggahan master tahun $tahun, terbaru dulu.
     *
     * @return list<array{id: string, tahun: int, cakupan: string, nama_berkas: string, ringkasan: array<string, array<string, int>>, user_id: int|null, pengguna: string, waktu: string}>
     */
    public function unggahan(int $tahun): array;
}
