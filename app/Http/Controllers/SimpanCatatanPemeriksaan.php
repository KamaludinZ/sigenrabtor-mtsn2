<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Pemeriksaan\CatatanPemeriksaan;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint simpan draf catatan pemeriksaan (`PUT /pemeriksaan/{kegiatan}/catatan`, rute admin
 * `pemeriksaan.catatan.simpan`; isian `catatan` — kosong = draf dihapus — dan `versi` opsional dari bacaan terakhir):
 * CatatanPemeriksaan::simpan. Jawaban {draf, berubah, versi, pesan}; galat isian 422 {pesan, galat}, draf diubah di
 * tempat lain 409 {pesan, draf, versi} (draf terbaru untuk digabungkan), kegiatan tidak ada 404.
 */
class SimpanCatatanPemeriksaan extends Controller
{
    public function __invoke(Request $request, int $kegiatan): JsonResponse
    {
        app(KegiatanRepository::class)->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');

        try {
            $hasil = CatatanPemeriksaan::simpan($kegiatan, $request->input('catatan'), StubData::currentUser(), $request->has('versi') ? (string) $request->input('versi') : null);
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => $e->errors()], 422);
        } catch (PengaturanBerubah $e) {
            $draf = CatatanPemeriksaan::draf($kegiatan);

            return response()->json(['pesan' => $e->getMessage(), 'draf' => $draf, 'versi' => CatatanPemeriksaan::versi($draf)], 409);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        }

        return response()->json($hasil, headers: ['Cache-Control' => 'no-store, private']);
    }
}
