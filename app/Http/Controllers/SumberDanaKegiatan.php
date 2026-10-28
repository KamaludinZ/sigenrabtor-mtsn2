<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Rab\DataSumberDana;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;

/** Endpoint baca sumber dana & pagu satu kegiatan (JSON, DataSumberDana::untuk); hak baca AksesKegiatan, tidak di-cache. */
class SumberDanaKegiatan extends Controller
{
    public function __invoke(KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404);
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403);

        return response()->json(DataSumberDana::untuk($data), headers: ['Cache-Control' => 'no-store, private']);
    }
}
