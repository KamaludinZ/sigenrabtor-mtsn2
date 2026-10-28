<?php

namespace App\Contracts;

/**
 * Log kunci & pembukaan data final per kegiatan (PRD N7, tabel activity_logs berisi activity_id): kunci final, buka kunci,
 * unggah/ganti/hapus arsip final — siapa, kapan, keterangan. Hanya bisa ditambah. Tahap frontend memakai tiruan JSON.
 */
interface RiwayatKunciRepository
{
    /**
     * @param  array{id?: int|null, name: string}  $pengguna
     * @param  string  $pada  Waktu ISO 8601 peristiwa (sama dengan entri riwayat_status bila ada).
     * @return array{id: string, kegiatan_id: int, aksi: string, keterangan: string, user_id: int|null, pengguna: string, tahun: int|null, pada: string}
     */
    public function catat(int $kegiatanId, string $aksi, string $keterangan, array $pengguna, string $pada, ?int $tahun): array;

    /**
     * Log satu kegiatan, terbaru dulu.
     *
     * @return list<array{id: string, kegiatan_id: int, aksi: string, keterangan: string, user_id: int|null, pengguna: string, tahun: int|null, pada: string}>
     */
    public function untukKegiatan(int $kegiatanId): array;
}
