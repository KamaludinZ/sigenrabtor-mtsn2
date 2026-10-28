<?php

namespace App\Http\Controllers;

use App\Support\Pemeriksaan\DaftarPemeriksaan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint daftar kegiatan untuk admin (JSON, DaftarPemeriksaan::untuk; `GET /pemeriksaan/data?status=&cari=&leading=&hal=`,
 * rute admin `pemeriksaan.data`), tidak di-cache.
 */
class DataPemeriksaan extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(DaftarPemeriksaan::untuk($request->query()), headers: ['Cache-Control' => 'no-store, private']);
    }
}
