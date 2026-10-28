<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan\DataPengaturanAi;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;

/**
 * Konfigurasi AI yang berlaku bagi pengguna (`GET /ai/konfigurasi`, rute `ai.konfigurasi`, semua pengguna):
 * DataPengaturanAi::berlaku → {penyedia (nama aktif & cadangan), jumlah_versi, bagian (aktif/nonaktif), batas_aman,
 * data_dikirim, kuota, simulasi (admin saja)}. Tanpa cache.
 */
class KonfigurasiAiBerlaku extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(DataPengaturanAi::berlaku(StubData::currentUser()), 200, ['Cache-Control' => 'no-store, private']);
    }
}
