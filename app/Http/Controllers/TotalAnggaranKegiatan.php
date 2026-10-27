<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Rab\TotalAnggaran;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint baca total anggaran satu kegiatan (JSON, TotalAnggaran::untuk); hak baca AksesKegiatan (admin semua, akun
 * leading hanya kegiatan leading-nya atau buatannya). Tidak di-cache karena total berubah setiap rincian RAB diubah.
 */
class TotalAnggaranKegiatan extends Controller
{
    public function __invoke(KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404);
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403);

        return response()->json(TotalAnggaran::untuk($data), headers: ['Cache-Control' => 'no-store, private']);
    }
}
