<?php

namespace App\Contracts;

/**
 * Permohonan pendaftaran akun (PRD §8 "Daftar & Masuk": akun dibuat/diaktifkan admin) — calon pengguna mengisi formulir
 * pendaftaran, admin menyetujui (akun dibuat) atau menolaknya. Bentuk satu permohonan: {id, nama, surel, jabatan|null,
 * nip|null, leadings: list kode, catatan|null, sandi_hash (bcrypt, tidak pernah sandi asli), status menunggu|disetujui|
 * ditolak, diajukan_pada, diputuskan_pada|null, diputuskan_oleh|null, alasan|null}. Aturannya hanya lewat
 * App\Support\Akun\PendaftaranAkun.
 */
interface PermohonanAkunRepository
{
    /** @return list<array<string, mixed>> Terbaru dulu. */
    public function semua(): array;

    /** @return array<string, mixed>|null */
    public function temukan(string $id): ?array;

    /**
     * Simpan permohonan baru (id, status `menunggu`, dan waktu diisi repositori).
     *
     * @param  array<string, mixed>  $permohonan
     * @return array<string, mixed>
     *
     * @throws \RuntimeException Gagal ditulis.
     */
    public function ajukan(array $permohonan): array;
}
