<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\DataKodeKegiatan;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint master kode satu kegiatan (`GET /kegiatan/{kegiatan}/kode-master`, rute `kegiatan.kode_master`):
 * DataKodeKegiatan::untuk — kode kegiatan beserta uraian master tahun kegiatannya & keabsahannya. Hak baca
 * AksesKegiatan, tidak di-cache.
 */
class KodeMasterKegiatan extends Controller
{
    public function __invoke(KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404);
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403, AksesKegiatan::PESAN_TOLAK);

        return response()->json(DataKodeKegiatan::untuk($data), headers: ['Cache-Control' => 'no-store, private']);
    }
}
