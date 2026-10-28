<?php

namespace App\Http\Controllers;

use App\Support\Kegiatan\Impor\UnggahPerkin;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint unggah & parsing cascade Perkin (`POST /kegiatan/impor-perkin`, multipart `berkas`, rute
 * `kegiatan.impor_perkin.unggah`): UnggahPerkin::unggah → 201 {impor, lembar, kolom_hilang, baris, cascade}. Berkas salah
 * atau tidak terbaca 422 {pesan, galat.berkas}, tahun aktif terkunci 403. Belum ada kegiatan yang dibuat.
 */
class UnggahPerkinKegiatan extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $hasil = UnggahPerkin::unggah($request->file('berkas'), StubData::currentUser());
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => $e->errors()], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        }

        return response()->json($hasil, 201, ['Cache-Control' => 'no-store, private']);
    }
}
