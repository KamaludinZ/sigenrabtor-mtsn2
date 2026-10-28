<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\UbahDataKegiatan;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint baca isian data kegiatan (`GET /kegiatan/{kegiatan}/data`, rute `kegiatan.data`): UbahDataKegiatan::data —
 * isian yang bisa diubah, status, bisa/tidaknya diubah beserta alasannya, dan `versi` untuk `kegiatan.ubah`. Hak baca
 * AksesKegiatan, tidak di-cache.
 */
class DataIsianKegiatan extends Controller
{
    public function __invoke(KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404);
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403);

        return response()->json(UbahDataKegiatan::data($data), headers: ['Cache-Control' => 'no-store, private']);
    }
}
