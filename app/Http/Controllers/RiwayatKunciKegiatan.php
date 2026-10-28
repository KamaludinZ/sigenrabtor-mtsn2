<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\RiwayatKunci;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint riwayat kunci & pembukaan satu kegiatan (`GET /kegiatan/{kegiatan}/riwayat-kunci`, rute
 * `kegiatan.riwayat_kunci`): RiwayatKunci::data — kunci final, buka kunci (beserta alasan), unggah/ganti/hapus arsip
 * final; siapa & kapan, terbaru dulu. Hak baca AksesKegiatan, tidak di-cache.
 */
class RiwayatKunciKegiatan extends Controller
{
    public function __invoke(KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404);
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403, AksesKegiatan::PESAN_TOLAK);

        return response()->json(RiwayatKunci::data($data), headers: ['Cache-Control' => 'no-store, private']);
    }
}
