<?php

namespace App\Contracts;

/**
 * Penyimpanan data kegiatan. Tahap frontend memakai implementasi tiruan (file JSON);
 * backend menggantinya dengan Eloquent tanpa mengubah komponen yang memakainya.
 */
interface KegiatanRepository
{
    /**
     * Simpan data kegiatan; $id null berarti kegiatan baru. Mengembalikan id kegiatan.
     *
     * @param  array<string, mixed>  $data
     */
    public function simpan(array $data, ?int $id = null): int;

    /**
     * @return array<string, mixed>|null Data kegiatan + kunci id, status, diperbarui_pada.
     */
    public function temukan(int $id): ?array;

    /**
     * Ringkasan kegiatan terbaru lebih dulu.
     *
     * @return list<array{id: int, nama_kegiatan: string, status: string, diperbarui_pada: string}>
     */
    public function terbaru(int $batas = 10): array;
}
