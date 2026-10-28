<?php

namespace App\Http\Controllers;

use App\Support\Kegiatan\Impor\UnggahPerkin;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Endpoint baca impor & baris draf pratinjaunya (`GET /kegiatan/impor-perkin/{impor}`, rute `kegiatan.impor_perkin.data`):
 * UnggahPerkin::data — pengimpor atau admin; tidak ada 404, lain 403. Tanpa cache.
 */
class DataImporKegiatan extends Controller
{
    public function __invoke(string $impor): JsonResponse
    {
        try {
            $data = UnggahPerkin::data($impor, StubData::currentUser());
        } catch (RuntimeException $e) {
            abort(404, $e->getMessage());
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        }

        return response()->json($data, headers: ['Cache-Control' => 'no-store, private']);
    }
}
