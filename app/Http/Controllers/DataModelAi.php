<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan\KelolaModelAi;
use Illuminate\Http\JsonResponse;

/**
 * Daftar model satu penyedia AI (`GET /pengaturan/ai/model/{penyedia}`, rute admin `pengaturan.ai.model`):
 * KelolaModelAi::daftar → {penyedia, nama, model (terpakai), daftar (DaftarModelAi; kosong = ketik nama model), galat}.
 * Tanpa cache.
 */
class DataModelAi extends Controller
{
    public function __invoke(string $penyedia): JsonResponse
    {
        return response()->json(KelolaModelAi::daftar($penyedia), 200, ['Cache-Control' => 'no-store, private']);
    }
}
