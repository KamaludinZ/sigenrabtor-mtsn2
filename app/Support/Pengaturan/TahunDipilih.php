<?php

namespace App\Support\Pengaturan;

use App\Contracts\TahunAnggaranRepository;
use App\Support\Stub\StubData;
use Illuminate\Http\Request;

/**
 * Filter tahun anggaran untuk data yang tersimpan per tahun (PRD F6: tahun aktif menentukan tahun di seluruh dokumen,
 * dasar hukum, dan penanda tangan; data tahun lain disembunyikan, tidak dihapus) — satu aturan untuk parameter `ta`
 * halaman pengaturan per tahun (Dasar hukum, tab Pejabat) dan endpoint datanya:
 * - kosong/tidak ada = tahun aktif;
 * - tahun terdaftar di TahunAnggaranRepository = tahun itu (terkunci = hanya dilihat, `terkunci()`);
 * - selain itu (bukan angka, belum dibuat) tidak sah: halaman kembali ke tahun aktif (`parameter()` → ''), endpoint 404
 *   (`dariRequest()`);
 * - akun selain admin selalu tahun aktif — tahun lain hanya untuk admin.
 */
final class TahunDipilih
{
    /**
     * Tahun dari nilai `ta`; null bila tidak sah atau bukan untuk $pengguna.
     *
     * @param  array{role: string}|null  $pengguna  null = tanpa batas peran.
     */
    public static function dari(mixed $ta, ?array $pengguna = null): ?int
    {
        $aktif = StubData::tahunAktif()['tahun'];
        if ($ta === null || trim((string) $ta) === '') {
            return $aktif;
        }
        $tahun = filter_var(trim((string) $ta), FILTER_VALIDATE_INT);
        if ($tahun === false || ! in_array($tahun, array_column(app(TahunAnggaranRepository::class)->semua(), 'tahun'), true)) {
            return null;
        }

        return $pengguna === null || $pengguna['role'] === 'admin' || $tahun === $aktif ? $tahun : null;
    }

    /** Nilai `ta` yang rapi untuk URL halaman: '' untuk tahun aktif maupun nilai yang tidak sah. */
    public static function parameter(mixed $ta, ?array $pengguna = null): string
    {
        $tahun = self::dari($ta, $pengguna);

        return $tahun === null || $tahun === StubData::tahunAktif()['tahun'] ? '' : (string) $tahun;
    }

    /** Tahun dari `?ta=` permintaan untuk pengguna yang sedang masuk; tidak sah → 404. */
    public static function dariRequest(Request $request): int
    {
        $tahun = self::dari($request->query('ta'), StubData::currentUser());
        abort_if($tahun === null, 404);

        return $tahun;
    }

    /** Tahun anggaran terkunci: datanya hanya dilihat. */
    public static function terkunci(int $tahun): bool
    {
        return (bool) (collect(app(TahunAnggaranRepository::class)->semua())->firstWhere('tahun', $tahun)['is_locked'] ?? false);
    }
}
