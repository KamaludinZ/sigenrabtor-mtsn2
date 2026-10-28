<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\PembuatDokumen;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Endpoint pembuatan dokumen otomatis (`POST /kegiatan/{kegiatan}/dokumen/susun`, rute `kegiatan.dokumen.susun`; isian
 * `jenis` DokumenKegiatan::JENIS dan `sumber` opsional — kosong = sumber dana pertama): PembuatDokumen::susun — dokumen
 * disusun dari data terbaru lalu disimpan (disusun ulang = ditimpa). Jawaban {dokumen (bagian KAK/TOR, RAB, cover, cap
 * DRAF, nama berkas, keadaan), baru, berubah, pesan} — 201 pertama kali, 200 disusun ulang; jenis/sumber tidak sah 422
 * {pesan, galat}, di luar cakupan 403, kegiatan tidak ada 404, gagal disimpan 503.
 */
class SusunDokumenKegiatan extends Controller
{
    public function __invoke(Request $request, KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');

        try {
            $hasil = PembuatDokumen::susun($kegiatan, (string) $request->input('jenis', ''), $request->filled('sumber') ? (string) $request->input('sumber') : null, StubData::currentUser());
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => $e->errors()], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], 503);
        }

        return response()->json($hasil, $hasil['baru'] ? 201 : 200, ['Cache-Control' => 'no-store, private']);
    }
}
