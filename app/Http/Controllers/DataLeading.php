<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan\KelolaLeading;
use Illuminate\Http\JsonResponse;

/** Endpoint baca leading (JSON, KelolaLeading::data: leading + akun tertaut, pejabat & kegiatan tahun aktif, versi; rute admin), tidak di-cache. */
class DataLeading extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(KelolaLeading::data(), headers: ['Cache-Control' => 'no-store, private']);
    }
}
