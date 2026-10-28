<?php

namespace App\Http\Controllers;

use App\Support\Dokumen\DrafSunting;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Endpoint buang draf sunting (`DELETE /kegiatan/{kegiatan}/pratinjau/sunting/{bidang}` {versi?}, rute
 * `kegiatan.sunting.hapus`): DrafSunting::hapus → 200 {pesan}; draf tidak ada 404, 409 versi usang, 422 bidang asing,
 * 403 bukan cakupan, 404 kegiatan, 503 gagal tulis. Tanpa cache.
 */
class HapusDrafSunting extends Controller
{
    public function __invoke(Request $request, int $kegiatan, string $bidang): JsonResponse
    {
        $tanpaCache = ['Cache-Control' => 'no-store, private'];
        try {
            $dihapus = DrafSunting::hapus($kegiatan, $bidang, StubData::currentUser(), $request->filled('versi') ? (string) $request->input('versi') : null);
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => $e->errors()], 422, $tanpaCache);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403, $tanpaCache);
        } catch (PengaturanBerubah $e) {
            return response()->json(['pesan' => $e->getMessage()], 409, $tanpaCache);
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], $e->getMessage() === 'Kegiatan tidak ditemukan.' ? 404 : 503, $tanpaCache);
        }

        return $dihapus
            ? response()->json(['pesan' => 'Draf dibuang — teks tersimpan tidak berubah.'], 200, $tanpaCache)
            : response()->json(['pesan' => 'Draf tidak ditemukan.'], 404, $tanpaCache);
    }
}
