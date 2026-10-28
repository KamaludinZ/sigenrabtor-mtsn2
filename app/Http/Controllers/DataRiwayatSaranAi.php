<?php

namespace App\Http\Controllers;

use App\Support\Kegiatan\DataRiwayatSaranAi as Data;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Riwayat saran AI dalam cakupan akun (`GET /riwayat-saran-ai/data?kegiatan=&bagian=&status=&cari=&hal=`, rute
 * `saran_ai.data`, semua pengguna; saringan sama dengan halaman Riwayat Saran AI): DataRiwayatSaranAi::daftar →
 * {saringan, ringkasan, halaman, pilihan_kegiatan, permintaan}. Tanpa cache.
 */
class DataRiwayatSaranAi extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(Data::daftar(StubData::currentUser(), $request->query()), 200, ['Cache-Control' => 'no-store, private']);
    }
}
