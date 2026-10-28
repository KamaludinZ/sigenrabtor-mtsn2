<?php

namespace App\Http\Controllers;

use App\Support\Kegiatan\Duplikat\DaftarSumberDuplikat;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint daftar kegiatan tahun lalu untuk diduplikat (`GET /kegiatan/duplikat/sumber?dari=&leading=&cari=&bisa=`, rute
 * `kegiatan.duplikat.sumber`, semua pengguna — cakupan akun): DaftarSumberDuplikat::untuk. Tanpa cache.
 */
class SumberDuplikatKegiatan extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(DaftarSumberDuplikat::untuk(StubData::currentUser(), $request->only(['dari', 'leading', 'cari', 'bisa'])), headers: ['Cache-Control' => 'no-store, private']);
    }
}
