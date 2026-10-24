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
}
