<?php

namespace App\Support\Akun;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Pemeriksaan formulir Masuk (PRD §8 "Daftar & Masuk", N3 keamanan): satu-satunya aturan halaman Masuk —
 * - `rapikanSurel()` (spasi tepi dibuang, huruf kecil), `galatSurel()` (wajib & format; diperiksa saat blur — keberadaan
 *   akun tidak pernah dibocorkan sebelum dikirim);
 * - `periksa($surel)` = akun & peran demo atau pesan penolakan (tidak terdaftar / permohonan menunggu / nonaktif / bukan
 *   akun contoh mode demo) — sandi baru diperiksa autentikasi Laravel kelak;
 * - pembatas percobaan per surel × alamat IP: `MAKS_PERCOBAAN` gagal → dijeda `JEDA_DETIK` (`tunggu()`, `gagal()` →
 *   sisa percobaan, `berhasil()` mengosongkan), `pesanTunggu()`/`pesanSisa()`.
 */
final class PeriksaMasuk
{
    public const MAKS_PERCOBAAN = 5;

    public const JEDA_DETIK = 60;

    /** Sisa percobaan yang mulai diperingatkan. */
    public const PERINGATAN_SISA = 2;

    public static function rapikanSurel(string $surel): string
    {
        return mb_strtolower(trim($surel));
    }

    public static function galatSurel(string $surel): ?string
    {
        return match (true) {
            $surel === '' => 'Isi surel akun Anda.',
            mb_strlen($surel) > 255 || filter_var($surel, FILTER_VALIDATE_EMAIL) === false => 'Surel tidak sah, mis. nama@madrasah.sch.id.',
            default => null,
        };
    }

    /** @return array{akun: array<string, mixed>|null, peran: string|null, galat: string|null} */
    public static function periksa(string $surel): array
    {
        $akun = DataAkun::cariSurel($surel);
        $peran = $akun !== null ? (DataAkun::PERSONA[(int) $akun['id']] ?? null) : null;

        return ['akun' => $akun, 'peran' => $peran, 'galat' => match (true) {
            $akun === null && PendaftaranAkun::menunggu($surel) !== null => 'Permohonan akun untuk surel ini masih menunggu persetujuan admin.',
            $akun === null => 'Surel ini belum terdaftar — ajukan pendaftaran atau hubungi admin madrasah.',
            ! ($akun['is_active'] ?? true) => 'Akun ini nonaktif — hubungi admin untuk mengaktifkannya kembali.',
            $peran === null => 'Mode demo: hanya akun contoh di bawah yang bisa masuk sampai autentikasi diaktifkan.',
            default => null,
        }];
    }

    /** Detik sampai boleh mencoba lagi (0 = boleh). */
    public static function tunggu(string $surel, ?string $ip): int
    {
        $kunci = self::kunci($surel, $ip);

        return RateLimiter::tooManyAttempts($kunci, self::MAKS_PERCOBAAN) ? max(1, RateLimiter::availableIn($kunci)) : 0;
    }

    /** Catat satu percobaan gagal; kembalikan sisa percobaan sebelum dijeda. */
    public static function gagal(string $surel, ?string $ip): int
    {
        RateLimiter::hit(self::kunci($surel, $ip), self::JEDA_DETIK);

        return RateLimiter::remaining(self::kunci($surel, $ip), self::MAKS_PERCOBAAN);
    }

    public static function berhasil(string $surel, ?string $ip): void
    {
        RateLimiter::clear(self::kunci($surel, $ip));
    }

    public static function pesanTunggu(int $detik): string
    {
        return "Terlalu banyak percobaan masuk — coba lagi dalam {$detik} detik.";
    }

    public static function pesanSisa(int $sisa): ?string
    {
        return $sisa > 0 && $sisa <= self::PERINGATAN_SISA ? "Sisa {$sisa} percobaan sebelum masuk dijeda ".self::JEDA_DETIK.' detik.' : null;
    }

    private static function kunci(string $surel, ?string $ip): string
    {
        return 'masuk:'.sha1(self::rapikanSurel($surel).'|'.($ip ?? ''));
    }
}
