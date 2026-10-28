<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan\KelolaBatasAi;
use Illuminate\Http\JsonResponse;

/**
 * Batas kuota AI harian (`GET /pengaturan/ai/batas`, rute admin `pengaturan.ai.batas`): KelolaBatasAi::data → {versi,
 * batas_umum, rentang, preset, kapasitas, akun (pemakaian hari ini per akun aktif)}. Tanpa cache.
 */
class DataBatasAi extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(KelolaBatasAi::data(), 200, ['Cache-Control' => 'no-store, private']);
    }
}
