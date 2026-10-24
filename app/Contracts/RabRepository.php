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
}
