<?php

namespace App\Http\Middleware;

use App\Support\Stub\StubData;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman khusus admin (PRD peran Admin: pengaturan, master, pemeriksaan). Tahap data tiruan memeriksa peran dari
 * StubData::currentUser() (sakelar "lihat sebagai" di menu pengguna); backend menggantinya dengan auth + peran nyata.
 */
class HanyaAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(StubData::currentUser()['role'] === 'admin', 403, 'Halaman ini khusus admin.');

        return $next($request);
    }
}
