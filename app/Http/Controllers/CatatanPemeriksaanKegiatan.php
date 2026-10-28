<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Pemeriksaan\CatatanPemeriksaan;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint baca catatan pemeriksaan satu kegiatan (`GET /pemeriksaan/{kegiatan}/catatan`, rute admin
 * `pemeriksaan.catatan`): CatatanPemeriksaan::untuk — draf yang sedang ditulis + versinya, catatan keputusan yang sudah
 * dikirim, keputusan yang tersedia, dan batas panjang. Kegiatan tidak ada → 404.
 */
class CatatanPemeriksaanKegiatan extends Controller
{
    public function __invoke(int $kegiatan): JsonResponse
    {
        $data = app(KegiatanRepository::class)->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');

        return response()->json(CatatanPemeriksaan::untuk($data, StubData::currentUser()), headers: ['Cache-Control' => 'no-store, private']);
    }
}
