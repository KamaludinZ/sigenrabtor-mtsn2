<?php

namespace App\Contracts;

/**
 * Penyimpanan RAB per kegiatan per sumber dana (PRD tabel anggaran_kegiatan): rincian belanja (budget_items)
 * dan termin pembayaran opsional. Pagu tetap milik data kegiatan (sumber_dana).
 * Tahap frontend memakai implementasi tiruan; backend menggantinya dengan Eloquent.
 */
interface RabRepository
{
    /**
     * @return list<array{id: string, kode: string, uraian: string, kategori: string, volume: float|int, satuan: string, harga_satuan: int}>
     */
    public function rincian(int $kegiatanId, string $sumber): array;

    /**
     * Tambah satu item belanja di akhir daftar; mengembalikan id item.
     *
     * @param  array{kode: string, uraian: string, kategori: string, volume: float|int, satuan: string, harga_satuan: int}  $item
     */
    public function tambahItem(int $kegiatanId, string $sumber, array $item): string;

    /**
     * Ganti isi item (id tetap). Mengembalikan false bila item tidak ditemukan.
     *
     * @param  array{kode: string, uraian: string, kategori: string, volume: float|int, satuan: string, harga_satuan: int}  $item
     */
    public function ubahItem(int $kegiatanId, string $sumber, string $id, array $item): bool;

    /**
     * Hapus item; mengembalikan posisi & isinya (untuk "urungkan") atau null bila tidak ditemukan.
     *
     * @return array{index: int, item: array<string, mixed>}|null
     */
    public function hapusItem(int $kegiatanId, string $sumber, string $id): ?array;

    /**
     * Sisipkan kembali item (lengkap dengan id) pada posisi tertentu.
     *
     * @param  array<string, mixed>  $item
     */
    public function sisipkanItem(int $kegiatanId, string $sumber, int $index, array $item): void;

    /**
     * Termin pembayaran (opsional) berurutan; jumlah nominalnya harus sama dengan total RAB sumber dana itu.
     *
     * @return list<array{tanggal: string|null, nominal: int}>
     */
    public function termin(int $kegiatanId, string $sumber): array;

    /**
     * Ganti seluruh termin pembayaran satu sumber dana (daftar kosong = tanpa termin).
     *
     * @param  list<array{tanggal: string|null, nominal: int}>  $termin
     */
    public function simpanTermin(int $kegiatanId, string $sumber, array $termin): void;

    /**
     * Sumber dana yang punya data RAB tersimpan (rincian atau termin), termasuk sumber yang sudah tidak dipilih kegiatan.
     *
     * @return list<string>
     */
    public function sumberTersimpan(int $kegiatanId): array;
}
