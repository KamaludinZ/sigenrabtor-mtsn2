<?php

namespace App\Http\Middleware;

use App\Support\Akun\SesiAkun;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman khusus tamu (Masuk, Daftar akun — padanan middleware `guest`): selama `SesiAkun::wajibMasuk()`, pengguna yang
 * sudah masuk (akunnya masih aktif) dialihkan otomatis ke tujuan yang diingat atau Dashboard. Tanpa wajib masuk (mode
 * demo, tes) halaman Masuk tetap terbuka dan menampilkan kartu `sudah-masuk`.
 */
class HanyaTamu
{
    public function handle(Request $request, Closure $next): Response
    {
        if (SesiAkun::wajibMasuk() && SesiAkun::sudahMasuk() && ! SesiAkun::nonaktif() && ! $request->hasHeader('X-Livewire')) {
            return redirect()->intended(route('dashboard'));
        }

        return $next($request);
    }
}
