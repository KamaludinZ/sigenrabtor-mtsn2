<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\SimpanDokumenLengkap;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Endpoint simpan dokumen lengkap dalam format seragam (`POST /kegiatan/{kegiatan}/dokumen/lengkap`, rute
 * `kegiatan.dokumen.lengkap`; isian `sumber` opsional — kosong = sumber dana pertama — dan `nomor_dokumen` opsional):
 * SimpanDokumenLengkap::simpan. Jawaban {dokumen (+ nomor & format), baru, berubah, nomor {nomor, keadaan, label}, pesan} —
 * 201 pertama kali, 200 disusun ulang; sumber/nomor tidak sah 422 {pesan, galat}, di luar cakupan 403, kegiatan tidak ada
 * 404, gagal disimpan 503; tanpa cache. Kontrak: docs/api/format-seragam.md.
 */
class SimpanDokumenLengkapKegiatan extends Controller
{
    public function __invoke(Request $request, KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        $tanpaCache = ['Cache-Control' => 'no-store, private'];

        try {
            $hasil = SimpanDokumenLengkap::simpan($kegiatan, $request->filled('sumber') ? (string) $request->input('sumber') : null, StubData::currentUser(), $request->input('nomor_dokumen'));
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => array_map(fn (array $p) => $p[0], $e->errors())], 422, $tanpaCache);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403, $tanpaCache);
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], 503, $tanpaCache);
        }

        return response()->json($hasil, $hasil['baru'] ? 201 : 200, $tanpaCache);
    }
}
