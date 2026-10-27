<?php

namespace App\Support\Kegiatan;

/**
 * Siapa boleh membaca data satu kegiatan lewat endpoint JSON (total anggaran, RAB lengkap): admin semua kegiatan,
 * akun leading hanya kegiatan leading-nya atau kegiatan buatannya.
 */
final class AksesKegiatan
{
    /**
     * @param  array<string, mixed>  $kegiatan
     * @param  array{id: int, role: string, leadings?: list<string>}  $pengguna
     */
    public static function bolehLihat(array $kegiatan, array $pengguna): bool
    {
        return $pengguna['role'] === 'admin'
            || in_array($kegiatan['leading'] ?? null, $pengguna['leadings'] ?? [], true)
            || ($kegiatan['user_id'] ?? null) === $pengguna['id'];
    }
}
