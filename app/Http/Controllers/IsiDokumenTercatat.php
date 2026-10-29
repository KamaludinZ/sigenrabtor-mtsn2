<?php

namespace App\Http\Controllers;

use App\Support\Dokumen\IsiRiwayatDokumen;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Isi satu catatan Riwayat Dokumen (`GET /dokumen/{dokumen}/isi`, rute `dokumen.isi`, semua pengguna):
 * IsiRiwayatDokumen::untuk — sumber isi (tersimpan/arsip/terbaru/tidak_ada), catatan, perbedaan, berkas arsip, dan isi
 * {sidik, cover, bagian, rab}. 404 {pesan} tidak ada, 403 {pesan} di luar cakupan/akun nonaktif. Tanpa cache.
 */
class IsiDokumenTercatat extends Controller
{
    public function __invoke(string $dokumen): JsonResponse
    {
        $tanpaCache = ['Cache-Control' => 'no-store, private'];
        try {
            return response()->json(IsiRiwayatDokumen::untuk($dokumen, StubData::currentUser()), 200, $tanpaCache);
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], 404, $tanpaCache);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403, $tanpaCache);
        }
    }
}
