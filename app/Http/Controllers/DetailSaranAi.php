<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Contracts\RiwayatSaranAiRepository;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\DataRiwayatSaranAi;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;

/**
 * Satu permintaan saran AI (`GET /saran-ai/{permintaan}`, rute `saran_ai.detail`): DataRiwayatSaranAi::baris (versi,
 * keputusan, bahan yang dikirim ke AI). Permintaan atau kegiatannya tidak ada 404; kegiatan di luar hak baca akun 403.
 * Tanpa cache.
 */
class DetailSaranAi extends Controller
{
    public function __invoke(RiwayatSaranAiRepository $riwayat, KegiatanRepository $repo, string $permintaan): JsonResponse
    {
        $p = $riwayat->temukan($permintaan) ?? abort(404, 'Saran AI tidak ditemukan.');
        $kegiatan = $repo->temukan($p['kegiatan_id']) ?? abort(404, 'Kegiatan tidak ditemukan.');
        abort_unless(AksesKegiatan::bolehLihat($kegiatan, StubData::currentUser()), 403, AksesKegiatan::PESAN_TOLAK);

        return response()->json(DataRiwayatSaranAi::baris($p + ['nama_kegiatan' => trim((string) ($kegiatan['nama_kegiatan'] ?? '')), 'leading' => $kegiatan['leading'] ?? null]), 200, ['Cache-Control' => 'no-store, private']);
    }
}
