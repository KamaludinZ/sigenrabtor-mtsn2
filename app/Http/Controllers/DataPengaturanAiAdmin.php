<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan\DataPengaturanAi;
use Illuminate\Http\JsonResponse;

/**
 * Seluruh konfigurasi AI untuk admin (`GET /pengaturan/ai/data`, rute admin `pengaturan.ai.data`): DataPengaturanAi::untukAdmin
 * → {versi, penyedia, parameter, prompt, batas, simulasi, data_dikirim, terakhir_disimpan, tautan}; kunci API tidak pernah
 * ikut. Tanpa cache.
 */
class DataPengaturanAiAdmin extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(DataPengaturanAi::untukAdmin(), 200, ['Cache-Control' => 'no-store, private']);
    }
}
