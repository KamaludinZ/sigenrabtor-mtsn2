<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Rab\TotalAnggaran;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint baca total anggaran satu kegiatan (JSON, TotalAnggaran::untuk): admin semua kegiatan, akun leading hanya
 * kegiatan leading-nya atau buatannya. Tidak di-cache karena total berubah setiap rincian RAB diubah.
 */
class TotalAnggaranKegiatan extends Controller
{
    public function __invoke(KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404);
        $pengguna = StubData::currentUser();

        abort_unless(
            $pengguna['role'] === 'admin' || in_array($data['leading'] ?? null, $pengguna['leadings'] ?? [], true) || ($data['user_id'] ?? null) === $pengguna['id'],
            403,
        );

        return response()->json(TotalAnggaran::untuk($data), headers: ['Cache-Control' => 'no-store, private']);
    }
}
