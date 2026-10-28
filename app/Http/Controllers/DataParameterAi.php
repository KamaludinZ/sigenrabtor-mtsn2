<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan\KelolaModelAi;
use Illuminate\Http\JsonResponse;

/**
 * Parameter jawaban AI (`GET /pengaturan/ai/parameter`, rute admin `pengaturan.ai.parameter`): KelolaModelAi::dataParameter
 * → {versi, parameter, bawaan, preset_suhu, maks_token, jumlah_versi}. Tanpa cache.
 */
class DataParameterAi extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(KelolaModelAi::dataParameter(), 200, ['Cache-Control' => 'no-store, private']);
    }
}
