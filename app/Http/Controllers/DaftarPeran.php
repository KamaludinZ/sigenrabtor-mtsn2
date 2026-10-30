<?php

namespace App\Http\Controllers;

use App\Support\Akun\KelolaPeran;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;

/** Endpoint admin daftar peran beserta hak & akunnya (`GET /pengaturan/peran`, rute `pengaturan.peran`; KelolaPeran::daftar); tanpa cache. */
class DaftarPeran extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(KelolaPeran::daftar(StubData::currentUser()), headers: ['Cache-Control' => 'no-store, private']);
    }
}
