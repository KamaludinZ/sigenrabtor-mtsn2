<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\KunciFinal;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint baca kunci final satu kegiatan (`GET /kegiatan/{kegiatan}/kunci`, rute `kegiatan.kunci`): KunciFinal::data —
 * terkunci/tidak, jejak kunci (siapa & kapan), bisa/tidaknya dikunci (beserta arsip yang kurang) atau dibuka oleh akun
 * ini, dan `versi`. Hak baca AksesKegiatan, tidak di-cache.
 */
class StatusKunciFinal extends Controller
{
    public function __invoke(KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404);
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403, AksesKegiatan::PESAN_TOLAK);

        return response()->json(KunciFinal::data($data, StubData::currentUser()), headers: ['Cache-Control' => 'no-store, private']);
    }
}
