<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\KelolaArsipFinal;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint baca arsip final satu kegiatan (`GET /kegiatan/{kegiatan}/arsip`, rute `kegiatan.arsip.data`):
 * KelolaArsipFinal::data — arsip per sumber dana wajib (nama, ukuran, pengunggah, waktu, tautan unduh), boleh/tidaknya
 * diunggah beserta alasannya, dan sumber dana yang masih kurang. Hak baca AksesKegiatan, tidak di-cache.
 */
class DataArsipFinal extends Controller
{
    public function __invoke(KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404);
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403, AksesKegiatan::PESAN_TOLAK);

        return response()->json(KelolaArsipFinal::data($data, StubData::currentUser()), headers: ['Cache-Control' => 'no-store, private']);
    }
}
