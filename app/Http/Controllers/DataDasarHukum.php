<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan\KelolaDasarHukum;
use App\Support\Pengaturan\TahunDipilih;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint baca daftar dasar hukum satu tahun anggaran (JSON, KelolaDasarHukum::data; rute admin; `ta` lewat TahunDipilih —
 * bawaan tahun aktif, tahun tak terdaftar 404), tidak di-cache.
 */
class DataDasarHukum extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tahun = TahunDipilih::dariRequest($request);

        return response()->json(KelolaDasarHukum::data($tahun), headers: ['Cache-Control' => 'no-store, private']);
    }
}
