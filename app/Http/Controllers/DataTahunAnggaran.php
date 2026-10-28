<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan\KelolaTahunAnggaran;
use Illuminate\Http\JsonResponse;

/** Endpoint baca tahun anggaran beserta pemakaiannya (JSON, KelolaTahunAnggaran::daftar; rute admin), tidak di-cache. */
class DataTahunAnggaran extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['tahun' => KelolaTahunAnggaran::daftar()], headers: ['Cache-Control' => 'no-store, private']);
    }
}
