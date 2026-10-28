<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\DrafDokumen;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint baca draf dokumen satu kegiatan (`GET /kegiatan/{kegiatan}/draf`, rute `kegiatan.draf`): DrafDokumen::data —
 * semua draf (terbaru dulu) beserta versi, tautan lanjutkan, dan keadaannya terhadap data sekarang (sesuai/berubah +
 * bagian yang berubah), serta apakah draf boleh disimpan/dihapus. Kegiatan tidak ada 404, di luar cakupan akun 403.
 */
class DrafDokumenKegiatan extends Controller
{
    public function __invoke(KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403, AksesKegiatan::PESAN_TOLAK);

        return response()->json(DrafDokumen::data($data), headers: ['Cache-Control' => 'no-store, private']);
    }
}
