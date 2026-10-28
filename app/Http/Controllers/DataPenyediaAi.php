<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan\KelolaPenyediaAi;
use Illuminate\Http\JsonResponse;

/**
 * Konfigurasi penyedia AI (`GET /pengaturan/ai/penyedia`, rute admin `pengaturan.ai.penyedia`): KelolaPenyediaAi::data —
 * versi, penyedia aktif & cadangan, tiap penyedia (alamat, model, batas waktu, ada kunci + 4 karakter akhir, peran,
 * kesiapan) tanpa kunci API. Tanpa cache.
 */
class DataPenyediaAi extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(KelolaPenyediaAi::data(), 200, ['Cache-Control' => 'no-store, private']);
    }
}
