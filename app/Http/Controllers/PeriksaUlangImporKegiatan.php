<?php

namespace App\Http\Controllers;

use App\Support\Kegiatan\Impor\ValidasiImporMassal;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Endpoint periksa ulang baris impor (`POST /kegiatan/impor-perkin/{impor}/periksa`, rute `kegiatan.impor_perkin.periksa`):
 * ValidasiImporMassal::periksaUlang → {impor (rekap terbaru), berubah [{nomor_baris, status, pesan}], siap}. Impor tidak
 * ada 404, bukan pengimpor/admin 403, impor tidak berjalan 422.
 */
class PeriksaUlangImporKegiatan extends Controller
{
    public function __invoke(string $impor): JsonResponse
    {
        try {
            $hasil = ValidasiImporMassal::periksaUlang($impor, StubData::currentUser());
        } catch (RuntimeException $e) {
            abort(404, $e->getMessage());
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => $e->errors()], 422);
        }

        return response()->json($hasil, headers: ['Cache-Control' => 'no-store, private']);
    }
}
