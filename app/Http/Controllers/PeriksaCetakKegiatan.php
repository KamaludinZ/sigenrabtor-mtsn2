<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\PemeriksaanCetak;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Endpoint pemeriksaan kelengkapan sebelum cetak (`GET /kegiatan/{kegiatan}/periksa-cetak?jenis=&sumber=`, rute
 * `kegiatan.periksa_cetak`; jenis bawaan `lengkap`, sumber bawaan sumber dana pertama): PemeriksaanCetak::periksa. Jenis
 * tidak dikenal atau sumber dana tidak dipilih → 404. Hak baca AksesKegiatan, tidak di-cache.
 */
class PeriksaCetakKegiatan extends Controller
{
    public function __invoke(Request $request, KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404);
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403, AksesKegiatan::PESAN_TOLAK);

        try {
            $hasil = PemeriksaanCetak::periksa($data, (string) $request->query('jenis', 'lengkap'), filled($request->query('sumber')) ? (string) $request->query('sumber') : null);
        } catch (InvalidArgumentException $e) {
            abort(404, $e->getMessage());
        }

        return response()->json($hasil, headers: ['Cache-Control' => 'no-store, private']);
    }
}
