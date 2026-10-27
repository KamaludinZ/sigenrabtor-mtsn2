<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Rab\DataTermin;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;

/** Endpoint baca termin pembayaran satu kegiatan (JSON, DataTermin::untuk); hak baca AksesKegiatan, tidak di-cache. */
class TerminKegiatan extends Controller
{
    public function __invoke(KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404);
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403);

        return response()->json(DataTermin::untuk($data), headers: ['Cache-Control' => 'no-store, private']);
    }
}
