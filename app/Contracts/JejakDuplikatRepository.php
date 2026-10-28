<?php

namespace App\Contracts;

/**
 * Jejak duplikat kegiatan tahun lalu (tabel `duplikat_kegiatan`): kegiatan sumber & tahunnya, draf hasil di tahun aktif,
 * catatan perubahan (DuplikatKegiatan::rencana), pembuat, waktu. Hanya ditambah. Tahap frontend memakai tiruan JSON.
 */
interface JejakDuplikatRepository
{
    /**
     * @param  array{sumber_id: int, sumber_tahun: int, hasil_id: int, tahun: int, catatan: list<array{jenis: string, teks: string}>, user_id: int|null, pengguna: string}  $jejak
     * @return array<string, mixed> Jejak tersimpan (dengan `id` & `dibuat_pada`).
     */
    public function catat(array $jejak): array;

    /** @return array<string, mixed>|null Jejak draf hasil $hasilId. */
    public function untukHasil(int $hasilId): ?array;

    /**
     * Jejak duplikat ke tahun $tahun, terbaru dulu.
     *
     * @return list<array<string, mixed>>
     */
    public function semua(int $tahun): array;
}
