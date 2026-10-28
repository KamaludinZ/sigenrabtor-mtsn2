<?php

namespace App\Http\Controllers;

use App\Support\Kegiatan\Duplikat\LayananDuplikat;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint pratinjau duplikat & validasi kode (`POST /kegiatan/duplikat/rencana` {kegiatan: [id…]}, rute
 * `kegiatan.duplikat.rencana`): LayananDuplikat::rencana — per kegiatan siap/dilewati/gagal, kode sumber vs draf, dan
 * galat kode terhadap master tahun aktif; tidak menyimpan apa pun. Galat 422 {pesan, galat}, tahun aktif terkunci 403.
 */
class RencanaDuplikatKegiatan extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $hasil = LayananDuplikat::rencana((array) $request->input('kegiatan', []), StubData::currentUser());
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => $e->errors()], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        }

        return response()->json($hasil, headers: ['Cache-Control' => 'no-store, private']);
    }
}
