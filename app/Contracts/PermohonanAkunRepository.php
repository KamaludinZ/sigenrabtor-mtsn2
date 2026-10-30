<?php

namespace App\Contracts;

/**
 * Permohonan pendaftaran akun (PRD §8 "Daftar & Masuk": akun dibuat/diaktifkan admin) — calon pengguna mengisi formulir
 * pendaftaran, admin menyetujui (akun dibuat) atau menolaknya. Bentuk satu permohonan: {id, nama, surel, jabatan|null,
 * nip|null, leadings: list kode, catatan|null, sandi_hash (bcrypt, tidak pernah sandi asli), status menunggu|disetujui|
 * ditolak, diajukan_pada, diputuskan_pada|null, diputuskan_oleh|null (id akun pemutus), alasan|null, akun_id|null (akun
 * yang dibuat saat disetujui)}. Aturannya hanya lewat
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

    /**
     * Putuskan permohonan yang masih menunggu: `disetujui` (+ akun yang dibuat) atau `ditolak` (+ alasan); waktu keputusan
     * diisi repositori. Null bila permohonan tidak ada atau sudah diputuskan.
     *
     * @return array<string, mixed>|null
     *
     * @throws \RuntimeException Gagal ditulis.
     */
    public function putuskan(string $id, string $status, ?int $olehId, ?string $alasan = null, ?int $akunId = null): ?array;
}
