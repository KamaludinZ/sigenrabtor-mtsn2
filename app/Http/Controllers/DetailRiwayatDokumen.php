<?php

namespace App\Http\Controllers;

use App\Support\Dokumen\AksiRiwayatDokumen;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Rincian satu catatan Riwayat Dokumen (`GET /dokumen/{dokumen}`, rute `dokumen.detail`, semua pengguna): AksiRiwayatDokumen::
 * lihat — catatan, versi lain, keadaan kegiatan sekarang, cara membuka ulang, `izin` & `alasan` tiap aksi
 * (OtorisasiDokumen) — ditambah rencana duplikat ringkas {bisa, mode, alasan, tautan}. 404 {pesan} tidak ada, 403 {pesan}
 * di luar cakupan/akun nonaktif. Tanpa cache.
 */
class DetailRiwayatDokumen extends Controller
{
    public function __invoke(string $dokumen): JsonResponse
    {
        $pengguna = StubData::currentUser();
        $tanpaCache = ['Cache-Control' => 'no-store, private'];
        try {
            $rincian = AksiRiwayatDokumen::lihat($dokumen, $pengguna);
            $rencana = AksiRiwayatDokumen::rencanaDuplikat($dokumen, $pengguna);
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], 404, $tanpaCache);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403, $tanpaCache);
        }

        return response()->json($rincian + ['duplikat' => array_intersect_key($rencana, array_flip(['bisa', 'mode', 'alasan', 'tautan', 'nama', 'nama_terkunci', 'tahun']))], 200, $tanpaCache);
    }
}
