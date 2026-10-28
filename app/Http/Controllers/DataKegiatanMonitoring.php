<?php

namespace App\Http\Controllers;

use App\Support\Monitoring\DaftarKegiatanMonitoring;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint baca daftar kegiatan beserta total RAB (JSON, DaftarKegiatanMonitoring; khusus admin, saringan, `urut`,
 * `arah`, `hal` sama dengan /monitoring), tidak di-cache.
 */
class DataKegiatanMonitoring extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(DaftarKegiatanMonitoring::untuk($request->query()), headers: ['Cache-Control' => 'no-store, private']);
    }
}
