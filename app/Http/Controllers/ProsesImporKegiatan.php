<?php

namespace App\Http\Controllers;

use App\Livewire\Impor\ImporBertahap;
use App\Support\Kegiatan\Impor\ProsesImporMassal;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Endpoint batch insert draf dari impor (`POST /kegiatan/impor-perkin/{impor}/proses`, `batas` opsional, rute
 * `kegiatan.impor_perkin.proses`): ProsesImporMassal::proses → {impor, diproses, sisa, selesai}; dipanggil berulang
 * sampai `selesai`. Impor tidak ada 404, bukan pengimpor/admin atau tahun terkunci 403, impor tidak berjalan 422.
 */
class ProsesImporKegiatan extends Controller
{
    public function __invoke(Request $request, string $impor): JsonResponse
    {
        try {
            $hasil = ProsesImporMassal::proses($impor, StubData::currentUser(), (int) $request->input('batas', ImporBertahap::UKURAN_KELOMPOK));
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
