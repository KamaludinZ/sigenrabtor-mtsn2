<?php

namespace App\Contracts;

/**
 * Akun pengguna (PRD tabel users & user_leading, F29): dua peran — `admin` dan `leading`; akun leading tertaut ke satu
 * atau lebih kode leading. Akun nonaktif tidak bisa masuk tetapi tetap tercatat sebagai pembuat kegiatannya.
 * Tahap frontend memakai tiruan (berkas JSON, tanpa sandi); backend menggantinya dengan Laravel Auth + Eloquent.
 */
interface PenggunaRepository
{
    /**
     * Semua akun (aktif & nonaktif), urut id.
     *
     * @return list<array{id: int, name: string, email: string, role: string, nip: string|null, jabatan: string|null, leadings: list<string>, is_active: bool, terakhir_masuk: string|null, dibuat_pada: string|null}>
     */
    public function semua(): array;

    /**
     * @return array{id: int, name: string, email: string, role: string, nip: string|null, jabatan: string|null, leadings: list<string>, is_active: bool, terakhir_masuk: string|null, dibuat_pada: string|null}|null
     */
    public function temukan(int $id): ?array;

    /**
     * Tambah ($id null) atau ubah akun; kembalikan id-nya. Akun admin tidak menyimpan tautan leading.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \RuntimeException Penyimpanan gagal.
     */
    public function simpan(array $data, ?int $id = null): int;
}
