<?php

namespace App\Contracts;

/**
 * Penyimpanan rincian RAB (budget_items) per kegiatan per sumber dana.
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
}
