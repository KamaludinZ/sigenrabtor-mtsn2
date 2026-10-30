<?php

namespace App\Http\Middleware;

use App\Support\Akun\HakAkses;
use App\Support\Stub\StubData;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman khusus admin (PRD peran Admin: pengaturan, master, pemeriksaan). Tahap data tiruan memeriksa peran dari
 * StubData::currentUser() lewat HakAkses (`area_admin`: peran admin & akun aktif); juga middleware persisten Livewire.
 */
class HanyaAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (($alasan = HakAkses::alasan(StubData::currentUser(), 'area_admin')) !== null) {
            abort(403, $alasan);
        }

        return $next($request);
    }
}
