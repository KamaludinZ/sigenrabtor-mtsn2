<?php

namespace App\Http\Controllers;

use App\Support\Pagu\PaguDanRab;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint baca pagu & RAB per Data Pagu satu tahun anggaran (JSON, PaguDanRab::untuk): admin semua data pagu, akun
 * leading data pagu leading-nya & tanpa leading (DataPagu::untuk). `ta` bawaan tahun aktif; tidak di-cache.
 */
class PaguDanRabTahunan extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tahun = filter_var($request->query('ta'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]) ?: null;

        return response()->json(PaguDanRab::untuk(StubData::currentUser(), $tahun), headers: ['Cache-Control' => 'no-store, private']);
    }
}
