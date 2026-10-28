<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\DataPratinjau;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Endpoint data pratinjau dokumen (`GET /kegiatan/{kegiatan}/pratinjau/data?sumber=&dokumen=`, rute
 * `kegiatan.pratinjau.data`): DataPratinjau::untuk — cover, sembilan bagian KAK/TOR (blok isi, status, bidang sunting),
 * RAB, cap DRAF, daftar isi, penanda kosong, kelengkapan, kaidah, jejak sunting, versi. `sumber` kosong = sumber dana
 * pertama (tidak dipilih → 404), `dokumen` = lembar cover,kak,rab (kosong = semua). Hak baca AksesKegiatan, tanpa cache.
 */
class DataPratinjauKegiatan extends Controller
{
    public function __invoke(Request $request, KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403, AksesKegiatan::PESAN_TOLAK);

        try {
            $hasil = DataPratinjau::untuk($data, $request->filled('sumber') ? (string) $request->query('sumber') : null, StubData::currentUser(), $request->query('dokumen'));
        } catch (InvalidArgumentException $e) {
            abort(404, $e->getMessage());
        }

        return response()->json($hasil, headers: ['Cache-Control' => 'no-store, private']);
    }
}
