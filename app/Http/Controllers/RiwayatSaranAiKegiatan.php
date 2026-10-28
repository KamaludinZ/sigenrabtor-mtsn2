<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\DataRiwayatSaranAi;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Riwayat saran AI satu kegiatan (`GET /kegiatan/{kegiatan}/saran-ai?bagian=`, rute `kegiatan.saran_ai`): hak baca
 * AksesKegiatan (selain itu 403), kegiatan tidak ada 404; DataRiwayatSaranAi::kegiatan → {kegiatan_id, nama_kegiatan,
 * bagian, ringkasan, per_bagian, permintaan} terbaru dulu. Tanpa cache.
 */
class RiwayatSaranAiKegiatan extends Controller
{
    public function __invoke(Request $request, KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403, AksesKegiatan::PESAN_TOLAK);

        return response()->json(DataRiwayatSaranAi::kegiatan($data, (string) $request->query('bagian', '')), 200, ['Cache-Control' => 'no-store, private']);
    }
}
