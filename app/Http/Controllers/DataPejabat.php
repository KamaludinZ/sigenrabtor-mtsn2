<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan\KelolaPejabat;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Endpoint baca pejabat penanda tangan satu tahun (JSON, KelolaPejabat::data; rute admin; `ta` bawaan tahun aktif), tidak di-cache. */
class DataPejabat extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tahun = filter_var($request->query('ta'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]) ?: StubData::tahunAktif()['tahun'];

        return response()->json(KelolaPejabat::data($tahun), headers: ['Cache-Control' => 'no-store, private']);
    }
}
