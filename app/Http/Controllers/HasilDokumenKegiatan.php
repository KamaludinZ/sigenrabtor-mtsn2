<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\JenisDokumen;
use App\Support\Dokumen\PembuatDokumen;
use App\Support\Dokumen\SusunanKak;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint baca hasil pembuatan dokumen (`GET /kegiatan/{kegiatan}/dokumen/hasil?jenis=&sumber=`, rute
 * `kegiatan.dokumen.hasil`): PembuatDokumen::data — hasil tersimpan jenis × sumber (null bila belum pernah disusun) beserta
 * keadaannya terhadap data sekarang, dan daftar semua hasil kegiatan itu. Jenis kosong = JenisDokumen::BAWAAN (kegiatan
 * tanpa sumber dana → TANPA_SUMBER); jenis/sumber tidak sah 422, di luar cakupan 403, kegiatan tidak ada 404.
 */
class HasilDokumenKegiatan extends Controller
{
    public function __invoke(Request $request, KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        $pengguna = StubData::currentUser();
        abort_unless(AksesKegiatan::bolehLihat($data, $pengguna), 403, AksesKegiatan::PESAN_TOLAK);

        $sumber = $request->filled('sumber') ? (string) $request->query('sumber') : null;
        $jenis = $request->filled('jenis') ? (string) $request->query('jenis') : JenisDokumen::BAWAAN;
        if (! $request->filled('jenis') && JenisDokumen::perluSumber($jenis) && SusunanKak::dari($data, $sumber)->sumber === null) {
            $jenis = JenisDokumen::TANPA_SUMBER;
        }

        try {
            $hasil = PembuatDokumen::data($kegiatan, $jenis, $sumber, $pengguna);
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => $e->errors()], 422);
        }

        return response()->json($hasil, headers: ['Cache-Control' => 'no-store, private']);
    }
}
