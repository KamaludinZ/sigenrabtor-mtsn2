<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\DrafDokumen;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Endpoint simpan draf dokumen (`PUT /kegiatan/{kegiatan}/draf`, rute `kegiatan.draf.simpan`; isian `jenis`
 * DokumenKegiatan::JENIS dan `sumber` opsional — kosong = sumber dana pertama): DrafDokumen::simpan, jalur yang sama dengan
 * tombol Simpan draf tab Susun KAK/TOR. Jawaban {draf (bentuk `kegiatan.draf`), baru, pesan} — 201 draf baru, 200 draf
 * diperbarui; jenis/sumber tidak sah 422 {pesan, galat}, di luar cakupan/terkunci 403, kegiatan tidak ada 404,
 * penyimpanan gagal ditulis 503.
 */
class SimpanDrafDokumen extends Controller
{
    public function __invoke(Request $request, KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');

        try {
            $hasil = DrafDokumen::simpan($kegiatan, (string) $request->input('jenis', ''), $request->filled('sumber') ? (string) $request->input('sumber') : null, StubData::currentUser());
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => $e->errors()], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], 503);
        }

        return response()->json(['draf' => DrafDokumen::untukJson($repo->temukan($kegiatan), $hasil['draf'])] + $hasil, $hasil['baru'] ? 201 : 200, ['Cache-Control' => 'no-store, private']);
    }
}
