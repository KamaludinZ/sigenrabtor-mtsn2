<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan\KelolaPejabat;
use App\Support\Pengaturan\TahunDipilih;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Endpoint baca pejabat penanda tangan satu tahun (JSON, KelolaPejabat::data; rute admin; `ta` lewat TahunDipilih — bawaan tahun aktif, tahun tak terdaftar 404), tidak di-cache. */
class DataPejabat extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tahun = TahunDipilih::dariRequest($request);

        return response()->json(KelolaPejabat::data($tahun), headers: ['Cache-Control' => 'no-store, private']);
    }
}
