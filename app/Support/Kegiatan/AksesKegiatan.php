<?php

namespace App\Support\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Support\Pengaturan\TautanAkunLeading;
use App\Support\Stub\StubData;

/**
 * Pemfilter data kegiatan per peran (PRD §3: admin melihat semua kegiatan; akun leading hanya kegiatan leading-nya) —
 * satu aturan untuk endpoint JSON kegiatan, halaman ruang kerja kegiatan (Data Kegiatan, Kode, RAB & termin & impor RAB,
 * Matrik & Jadwal, Kalimat KAK, Pratinjau), unduh arsip final, dan tabel Data Kegiatan: admin semua; akun leading kegiatan
 * yang leading-nya tertaut ke akun itu (TautanAkunLeading::bolehAkses) atau kegiatan buatannya sendiri (`user_id`).
 */
final class AksesKegiatan
{
    public const PESAN_TOLAK = 'Kegiatan ini milik leading lain — hanya akun leading tersebut dan admin yang bisa membukanya.';

    /**
     * @param  array<string, mixed>  $kegiatan
     * @param  array{id: int, role: string, leadings?: list<string>}  $pengguna
     */
    public static function bolehLihat(array $kegiatan, array $pengguna): bool
    {
        return TautanAkunLeading::bolehAkses($pengguna, $kegiatan['leading'] ?? null)
            || (int) ($kegiatan['user_id'] ?? 0) === (int) $pengguna['id'];
    }

    /**
     * Kegiatan yang boleh dilihat $pengguna (urutan tetap).
     *
     * @param  list<array<string, mixed>>  $kegiatan
     * @param  array{id: int, role: string, leadings?: list<string>}|null  $pengguna  null = pengguna yang sedang masuk.
     * @return list<array<string, mixed>>
     */
    public static function saring(array $kegiatan, ?array $pengguna = null): array
    {
        $pengguna ??= StubData::currentUser();

        return $pengguna['role'] === 'admin' ? array_values($kegiatan) : array_values(array_filter($kegiatan, fn (array $k) => self::bolehLihat($k, $pengguna)));
    }

    /**
     * Kegiatan $id untuk halaman: 404 bila tidak ada, 403 bila bukan cakupan pengguna yang sedang masuk.
     *
     * @return array<string, mixed>
     */
    public static function pastikan(int $id): array
    {
        $kegiatan = app(KegiatanRepository::class)->temukan($id) ?? abort(404, 'Kegiatan tidak ditemukan.');
        abort_unless(self::bolehLihat($kegiatan, StubData::currentUser()), 403, self::PESAN_TOLAK);

        return $kegiatan;
    }
}
