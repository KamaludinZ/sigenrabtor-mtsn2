<?php

namespace App\Http\Controllers;

use App\Support\Kegiatan\Impor\ProsesImporMassal;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Endpoint batalkan impor (`POST /kegiatan/impor-perkin/{impor}/batal`, rute `kegiatan.impor_perkin.batal`):
 * ProsesImporMassal::batalkan → {impor, pesan}; kegiatan yang sudah dibuat tetap ada. 404/403/422 seperti proses.
 */
class BatalImporKegiatan extends Controller
{
    public function __invoke(string $impor): JsonResponse
    {
        try {
            $hasil = ProsesImporMassal::batalkan($impor, StubData::currentUser());
        } catch (RuntimeException $e) {
            abort(404, $e->getMessage());
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => $e->errors()], 422);
        }

        return response()->json(['impor' => $hasil, 'pesan' => $hasil['jumlah_dibuat'] > 0 ? "Impor dibatalkan — {$hasil['jumlah_dibuat']} kegiatan yang sudah dibuat tetap ada." : 'Impor dibatalkan — tidak ada kegiatan yang dibuat.'], headers: ['Cache-Control' => 'no-store, private']);
    }
}
