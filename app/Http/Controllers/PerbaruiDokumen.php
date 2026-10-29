<?php

namespace App\Http\Controllers;

use App\Support\Dokumen\PerbaruiRiwayatDokumen;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Pembaruan dokumen Riwayat Dokumen (`dokumen.perbarui`; catatan lama tidak pernah berubah isinya):
 * - `PUT /dokumen/{dokumen}` {asal?, nomor_dokumen?, versi?} = PerbaruiRiwayatDokumen::terbitkanUlang → 201 versi baru dari
 *   data terbaru;
 * - `PATCH /dokumen/{dokumen}` {nomor_dokumen} = PerbaruiRiwayatDokumen::beriNomor → 200 (sekali, pada catatan belum bernomor).
 * Galat: 422 {pesan, galat}, 403 {pesan}, 404 {pesan}, 409 {pesan} (versi berganti / sudah bernomor). Tanpa cache.
 */
class PerbaruiDokumen extends Controller
{
    public function __invoke(Request $request, string $dokumen): JsonResponse
    {
        $tanpaCache = ['Cache-Control' => 'no-store, private'];
        $pengguna = StubData::currentUser();
        try {
            return $request->isMethod('PATCH')
                ? response()->json(PerbaruiRiwayatDokumen::beriNomor($dokumen, $request->input('nomor_dokumen'), $pengguna), 200, $tanpaCache)
                : response()->json(PerbaruiRiwayatDokumen::terbitkanUlang($dokumen, $request->only(['asal', 'nomor_dokumen', 'versi']), $pengguna), 201, $tanpaCache);
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => array_map(fn (array $p) => $p[0], $e->errors())], 422, $tanpaCache);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403, $tanpaCache);
        } catch (PengaturanBerubah $e) {
            return response()->json(['pesan' => $e->getMessage()], 409, $tanpaCache);
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], 404, $tanpaCache);
        }
    }
}
