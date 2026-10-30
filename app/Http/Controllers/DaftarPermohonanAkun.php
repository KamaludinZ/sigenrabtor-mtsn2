<?php

namespace App\Http\Controllers;

use App\Support\Akun\KeputusanPendaftaran;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;

/** Endpoint admin daftar permohonan akun (JSON, KeputusanPendaftaran::daftar: menunggu terlama dulu + yang sudah diputuskan); tidak di-cache. */
class DaftarPermohonanAkun extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(KeputusanPendaftaran::daftar(StubData::currentUser()), headers: ['Cache-Control' => 'no-store, private']);
    }
}
