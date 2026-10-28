<?php

namespace App\Http\Controllers;

use App\Support\Kegiatan\Duplikat\LayananDuplikat;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint proses duplikat (`POST /kegiatan/duplikat` {kegiatan: [id…]}, rute `kegiatan.duplikat.proses`):
 * LayananDuplikat::jalankan → {dibuat (+ tautan draf), dilewati, gagal, pesan}; 201 bila ada draf dibuat, selain itu 200.
 * Galat 422 {pesan, galat}, tahun aktif terkunci 403.
 */
class ProsesDuplikatKegiatan extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $hasil = LayananDuplikat::jalankan((array) $request->input('kegiatan', []), StubData::currentUser());
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => $e->errors()], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        }
        $hasil['dibuat'] = array_map(fn (array $d) => $d + ['tautan' => route('kegiatan.edit', ['kegiatan' => $d['hasil_id']])], $hasil['dibuat']);

        return response()->json($hasil, $hasil['dibuat'] === [] ? 200 : 201, ['Cache-Control' => 'no-store, private']);
    }
}
