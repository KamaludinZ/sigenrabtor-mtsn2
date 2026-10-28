<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\StatusCetak;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint baca keputusan cetak satu kegiatan (JSON, StatusCetak): per sumber dana yang dipilih — cap DRAF & alasannya
 * beserta saran, tanda air, nama berkas, kaki halaman. `?sumber=` membatasi ke satu sumber dana (404 bila tidak dipilih).
 * Hak baca AksesKegiatan; tidak di-cache.
 */
class StatusCetakKegiatan extends Controller
{
    public function __invoke(Request $request, KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404);
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403);

        $semua = StatusCetak::semua($data);
        if (filled($request->query('sumber'))) {
            $semua = array_values(array_filter($semua, fn (StatusCetak $s) => $s->sumber === $request->query('sumber'))) ?: abort(404);
        }

        return response()->json([
            'kegiatan_id' => (int) $data['id'],
            'nama_kegiatan' => (string) ($data['nama_kegiatan'] ?? ''),
            'draf' => collect($semua)->contains(fn (StatusCetak $s) => $s->draf()),
            'sumber' => array_map(fn (StatusCetak $s) => $s->untukJson(), $semua),
        ], headers: ['Cache-Control' => 'no-store, private']);
    }
}
