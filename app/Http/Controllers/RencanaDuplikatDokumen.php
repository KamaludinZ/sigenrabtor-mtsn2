<?php

namespace App\Http\Controllers;

use App\Support\Dokumen\AksiRiwayatDokumen;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Rencana duplikasi dokumen Riwayat Dokumen sebagai dasar kegiatan baru (`GET /dokumen/{dokumen}/duplikat`, rute
 * `dokumen.duplikat.rencana`): AksiRiwayatDokumen::rencanaDuplikat — bisa/alasan/tautan, mode tahun_lalu|tahun_ini, nama
 * bawaan & terkunci, yang diperbarui otomatis, catatan, yang ikut disalin, salinan sebelumnya. 404/403 {pesan}. Tanpa cache.
 */
class RencanaDuplikatDokumen extends Controller
{
    public function __invoke(string $dokumen): JsonResponse
    {
        $tanpaCache = ['Cache-Control' => 'no-store, private'];
        try {
            return response()->json(AksiRiwayatDokumen::rencanaDuplikat($dokumen, StubData::currentUser()), 200, $tanpaCache);
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], 404, $tanpaCache);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403, $tanpaCache);
        }
    }
}
