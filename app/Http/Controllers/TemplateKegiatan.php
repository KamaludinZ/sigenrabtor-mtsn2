<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\DaftarTemplate;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;

/** Endpoint template yang berlaku untuk satu kegiatan (JSON, DaftarTemplate::untukKegiatan); hak baca AksesKegiatan, tidak di-cache. */
class TemplateKegiatan extends Controller
{
    public function __invoke(KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404);
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403, AksesKegiatan::PESAN_TOLAK);

        return response()->json(DaftarTemplate::untukKegiatan($data), headers: ['Cache-Control' => 'no-store, private']);
    }
}
