<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan\KelolaPromptAi;
use Illuminate\Http\JsonResponse;

/**
 * Template prompt AI (`GET /pengaturan/ai/prompt`, rute admin `pengaturan.ai.prompt`): KelolaPromptAi::data → {versi,
 * gaya_bahasa, batas_aman, penanda, panjang_instruksi, bagian}. Tanpa cache.
 */
class DataPromptAi extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(KelolaPromptAi::data(), 200, ['Cache-Control' => 'no-store, private']);
    }
}
