<?php

namespace App\Http\Controllers;

use App\Contracts\DrafSuntingRepository;
use App\Support\Dokumen\DrafSunting;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Endpoint simpan/perbarui draf sunting (`PUT /kegiatan/{kegiatan}/pratinjau/sunting/{bidang}` {teks, versi?}, rute
 * `kegiatan.sunting.simpan`): DrafSunting::simpan → 201 draf baru / 200 diperbarui atau sama dengan tersimpan (draf null);
 * 422 {pesan, galat}, 403 bukan cakupan/terkunci, 409 {pesan, draf, versi} draf berubah di tempat lain, 404 kegiatan,
 * 503 gagal tulis. Tanpa cache.
 */
class SimpanDrafSunting extends Controller
{
    public function __invoke(Request $request, int $kegiatan, string $bidang): JsonResponse
    {
        $tanpaCache = ['Cache-Control' => 'no-store, private'];
        $pengguna = StubData::currentUser();
        try {
            $hasil = DrafSunting::simpan($kegiatan, $bidang, is_string($request->input('teks')) ? $request->input('teks') : '', $pengguna, $request->filled('versi') ? (string) $request->input('versi') : null);
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => $e->errors()], 422, $tanpaCache);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403, $tanpaCache);
        } catch (PengaturanBerubah $e) {
            $draf = app(DrafSuntingRepository::class)->temukan($kegiatan, (int) $pengguna['id'], $bidang);

            return response()->json(['pesan' => $e->getMessage(), 'draf' => $draf, 'versi' => DrafSunting::versi($draf)], 409, $tanpaCache);
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], $e->getMessage() === 'Kegiatan tidak ditemukan.' ? 404 : 503, $tanpaCache);
        }

        return response()->json($hasil, $hasil['baru'] ? 201 : 200, $tanpaCache);
    }
}
