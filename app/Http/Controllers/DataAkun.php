<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan\KelolaAkun;
use Illuminate\Http\JsonResponse;

/** Endpoint baca akun (JSON, KelolaAkun::data: akun + kegiatan buatan & riwayat masuk terakhir, ringkasan, versi; rute admin), tidak di-cache. */
class DataAkun extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(KelolaAkun::data(), headers: ['Cache-Control' => 'no-store, private']);
    }
}
