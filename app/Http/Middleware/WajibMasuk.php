<?php

namespace App\Http\Middleware;

use App\Support\Akun\SesiAkun;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman aplikasi hanya untuk pengguna yang sudah masuk (PRD §8; state autentikasi tiruan SesiAkun — padanan middleware
 * `auth`). Dipasang di grup web; rute `TERBUKA` (masuk, daftar, keluar, sakelar demo) dan permintaan Livewire tidak
 * diperiksa. Belum masuk → halaman: alihkan ke Masuk sambil mengingat tujuan (`redirect()->guest`); data JSON: 401 {pesan}
 * (dibaca klien unduhan sebagai "sesi"). Akun di sesi yang kini nonaktif dikeluarkan (dengan pemberitahuan). Nonaktif seluruhnya bila
 * `SesiAkun::wajibMasuk()` false (tes PHPUnit & E2E).
 */
class WajibMasuk
{
    public const TERBUKA = ['akun.masuk', 'akun.daftar', 'akun.keluar', 'demo.peran'];

    public const PESAN_SESI = 'Sesi Anda sudah berakhir — masuk kembali untuk melanjutkan.';

    public const PESAN_NONAKTIF = 'Akun Anda dinonaktifkan admin — hubungi admin madrasah.';

    public function handle(Request $request, Closure $next): Response
    {
        if (! SesiAkun::wajibMasuk() || $request->routeIs(...self::TERBUKA) || $request->is('livewire*', 'up')) {
            return $next($request);
        }
        if (SesiAkun::sudahMasuk() && SesiAkun::nonaktif()) {
            SesiAkun::keluar();

            return $this->tolak($request, self::PESAN_NONAKTIF);
        }
        if (! SesiAkun::sudahMasuk()) {
            return $this->tolak($request, self::PESAN_SESI, beritahu: false);
        }

        return $next($request);
    }

    private function tolak(Request $request, string $pesan, bool $beritahu = true): Response
    {
        if ($request->expectsJson() || str_contains((string) $request->header('Accept'), 'json')) {
            return response()->json(['pesan' => $pesan], 401, ['Cache-Control' => 'no-store']);
        }
        $alih = redirect()->guest(route('akun.masuk'));

        return $beritahu ? $alih->with('notify', ['message' => $pesan, 'type' => 'info']) : $alih;
    }
}
