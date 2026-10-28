<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\ValidasiKelengkapan;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Endpoint validasi kelengkapan bagian dokumen (`GET /kegiatan/{kegiatan}/kelengkapan?sumber=&jenis=&bagian=`, rute
 * `kegiatan.kelengkapan`): ValidasiKelengkapan::periksa (jenis kosong = lengkap) atau `bagian()` satu kunci
 * cover|kak-{n}|rab. Hak baca AksesKegiatan; jenis/sumber/bagian asing → 404; tanpa cache.
 */
class KelengkapanDokumenKegiatan extends Controller
{
    public function __invoke(Request $request, KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403, AksesKegiatan::PESAN_TOLAK);
        $sumber = $request->filled('sumber') ? (string) $request->query('sumber') : null;

        try {
            $hasil = $request->filled('bagian')
                ? ValidasiKelengkapan::bagian($data, $sumber, (string) $request->query('bagian'), StubData::currentUser())
                : ValidasiKelengkapan::periksa($data, $sumber, StubData::currentUser(), $request->filled('jenis') ? (string) $request->query('jenis') : 'lengkap');
        } catch (InvalidArgumentException $e) {
            abort(404, $e->getMessage());
        }

        return response()->json($hasil, headers: ['Cache-Control' => 'no-store, private']);
    }
}
