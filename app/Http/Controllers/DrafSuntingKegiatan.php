<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\DrafSunting;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint draf sunting pratinjau akun ini (`GET /kegiatan/{kegiatan}/pratinjau/sunting`, rute `kegiatan.sunting`):
 * DrafSunting::data — bidang yang bisa disunting, teks tersimpan, draf + keadaannya, versi data. Hak baca AksesKegiatan,
 * tanpa cache.
 */
class DrafSuntingKegiatan extends Controller
{
    public function __invoke(KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403, AksesKegiatan::PESAN_TOLAK);

        return response()->json(DrafSunting::data($data, StubData::currentUser()), headers: ['Cache-Control' => 'no-store, private']);
    }
}
