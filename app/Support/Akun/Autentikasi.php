<?php

namespace App\Support\Akun;

use App\Contracts\PenggunaRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Autentikasi masuk (PRD §8 "Daftar & Masuk", N3) — satu-satunya pemeriksa surel + sandi, dipakai halaman Masuk
 * (`Akun\Masuk`) dan endpoint JSON `akun.masuk.kirim`; state sesi tetap lewat SesiAkun:
 * - `periksa($surel, $sandi, $ip)` → {akun|null, galat (surel|sandi → pesan), peringatan (sisa percobaan), tunggu (detik
 *   jeda)}: format & keberadaan akun lewat PeriksaMasuk, sandi dicocokkan dengan hash tersimpan
 *   (`PenggunaRepository::sandi`, `Hash::check`); akun tanpa sandi hanya boleh masuk selama `modeDemo()` (selain itu
 *   `PESAN_TANPA_SANDI`); setiap kegagalan dihitung pembatas PeriksaMasuk, berhasil mengosongkannya;
 * - `masuk($akun, $ingat, $request)` = SesiAkun::masuk + catat masuk + cookie "ingat saya" `COOKIE_INGAT` (`HARI_INGAT`
 *   hari, berisi id akun & sidik sandi — sandi diganti = cookie tidak berlaku) atau cookie dihapus;
 * - `pulihkan($request)` = sesi dipulihkan dari cookie ingat (akun masih aktif & sidik sandi sama), dipakai WajibMasuk;
 * - `keluar()` = SesiAkun::keluar + cookie ingat dihapus; `dataSesi()` = state sesi untuk endpoint `akun.sesi`.
 */
final class Autentikasi
{
    public const COOKIE_INGAT = 'sirabtor_ingat';

    public const HARI_INGAT = 30;

    public const PESAN_SANDI = 'Sandi salah — periksa huruf besar/kecil, atau minta admin mengatur ulang sandi Anda.';

    public const PESAN_TANPA_SANDI = 'Akun ini belum punya sandi — minta admin mengatur sandi akun Anda.';

    public static function modeDemo(): bool
    {
        return (bool) config('sigenrabtor.akun.mode_demo', true);
    }

    /** @return array{akun: array<string, mixed>|null, galat: array<string, string>, peringatan: string|null, tunggu: int} */
    public static function periksa(string $surel, string $sandi, ?string $ip): array
    {
        $surel = PeriksaMasuk::rapikanSurel($surel);
        $galat = array_filter([
            'surel' => PeriksaMasuk::galatSurel($surel),
            'sandi' => match (true) {
                $sandi === '' => 'Isi sandi akun Anda.',
                mb_strlen($sandi) > 255 => 'Sandi terlalu panjang.',
                default => null,
            },
        ]);
        if ($galat !== []) {
            return self::gagal($galat);
        }
        if (($detik = PeriksaMasuk::tunggu($surel, $ip)) > 0) {
            return self::gagal(['surel' => PeriksaMasuk::pesanTunggu($detik)], tunggu: $detik);
        }

        $hasil = PeriksaMasuk::periksa($surel);
        $galat = $hasil['galat'] !== null ? ['surel' => $hasil['galat']] : self::galatSandi((int) $hasil['akun']['id'], $sandi);
        if ($galat !== []) {
            $sisa = PeriksaMasuk::gagal($surel, $ip);

            return self::gagal($galat, PeriksaMasuk::pesanSisa($sisa), $sisa === 0 ? PeriksaMasuk::tunggu($surel, $ip) : 0);
        }
        PeriksaMasuk::berhasil($surel, $ip);

        return ['akun' => $hasil['akun'], 'galat' => [], 'peringatan' => null, 'tunggu' => 0];
    }

    /** @param  array<string, mixed>  $akun */
    public static function masuk(array $akun, bool $ingat, Request $request): void
    {
        SesiAkun::masuk(['id' => (int) $akun['id'], 'role' => (string) $akun['role']]);
        try {
            app(PenggunaRepository::class)->catatMasuk((int) $akun['id'], $request->ip(), $request->userAgent());
        } catch (RuntimeException $e) {
            report($e);
        }
        Cookie::queue($ingat
            ? Cookie::make(self::COOKIE_INGAT, json_encode(['id' => (int) $akun['id'], 'sidik' => self::sidikSandi((int) $akun['id'])]), self::HARI_INGAT * 24 * 60, httpOnly: true, sameSite: 'lax')
            : Cookie::forget(self::COOKIE_INGAT));
    }

    /** Pulihkan sesi dari cookie "ingat saya"; false bila tidak ada/tidak berlaku (cookie dihapus). */
    public static function pulihkan(Request $request): bool
    {
        $isi = json_decode((string) $request->cookie(self::COOKIE_INGAT, ''), true);
        if (! is_array($isi) || ! isset($isi['id'], $isi['sidik'])) {
            return false;
        }
        $akun = app(PenggunaRepository::class)->temukan((int) $isi['id']);
        if ($akun === null || ! ($akun['is_active'] ?? true) || ! hash_equals(self::sidikSandi((int) $akun['id']), (string) $isi['sidik'])) {
            Cookie::queue(Cookie::forget(self::COOKIE_INGAT));

            return false;
        }
        SesiAkun::masuk(['id' => (int) $akun['id'], 'role' => (string) $akun['role']]);

        return true;
    }

    public static function keluar(): void
    {
        SesiAkun::keluar();
        Cookie::queue(Cookie::forget(self::COOKIE_INGAT));
    }

    /** @return array<string, mixed> */
    public static function dataSesi(): array
    {
        $masuk = SesiAkun::sudahMasuk();
        $p = $masuk ? SesiAkun::pengguna() : null;

        return [
            'masuk' => $masuk,
            'akun' => $p === null ? null : ['id' => $p['id'], 'nama' => $p['name'], 'surel' => $p['email'], 'peran' => $p['role'], 'label_peran' => PeranAkun::LABEL[$p['role']] ?? $p['role'], 'jabatan' => $p['jabatan'], 'leading' => $p['leadings'], 'masuk_pada' => $p['masuk_pada']],
            'wajib_masuk' => SesiAkun::wajibMasuk(),
            'mode_demo' => self::modeDemo(),
            'tautan' => ['masuk' => route('akun.masuk'), 'keluar' => route('akun.keluar'), 'daftar' => route('akun.daftar')],
        ];
    }

    /** @return array<string, string> */
    private static function galatSandi(int $id, string $sandi): array
    {
        $tersimpan = app(PenggunaRepository::class)->sandi($id);
        if ($tersimpan === null) {
            return self::modeDemo() ? [] : ['sandi' => self::PESAN_TANPA_SANDI];
        }

        return Hash::check($sandi, $tersimpan['hash']) ? [] : ['sandi' => self::PESAN_SANDI];
    }

    /** Sidik pendek sandi tersimpan (berubah bila sandi diganti). */
    private static function sidikSandi(int $id): string
    {
        return substr(hash('sha256', (app(PenggunaRepository::class)->sandi($id)['hash'] ?? 'tanpa-sandi').'|'.$id.'|'.config('app.key')), 0, 32);
    }

    /**
     * @param  array<string, string>  $galat
     * @return array{akun: null, galat: array<string, string>, peringatan: string|null, tunggu: int}
     */
    private static function gagal(array $galat, ?string $peringatan = null, int $tunggu = 0): array
    {
        return ['akun' => null, 'galat' => $galat, 'peringatan' => $peringatan, 'tunggu' => $tunggu];
    }
}
