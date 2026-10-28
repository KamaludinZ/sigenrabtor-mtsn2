<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan\KelolaSkRktm;
use App\Support\Pengaturan\TahunDipilih;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint baca SK RKTM/RKAM satu tahun anggaran (JSON, KelolaSkRktm::data; rute admin; `ta` lewat TahunDipilih —
 * bawaan tahun aktif, tahun tak terdaftar 404), tidak di-cache.
 */
class DataSkRktm extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tahun = TahunDipilih::dariRequest($request);

        return response()->json(KelolaSkRktm::data($tahun), headers: ['Cache-Control' => 'no-store, private']);
    }
}
