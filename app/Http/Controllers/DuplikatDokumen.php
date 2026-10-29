<?php

namespace App\Http\Controllers;

use App\Support\Dokumen\AksiRiwayatDokumen;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Duplikasi dokumen Riwayat Dokumen menjadi kegiatan baru beserta draf dokumennya (`POST /dokumen/{dokumen}/duplikat`
 * {nama?}, rute `dokumen.duplikat`): AksiRiwayatDokumen::duplikat → 201 {id, nama, mode, catatan, draf, tautan,
 * tautan_dokumen, pesan}. 422 {pesan, galat: {nama|duplikat}} (nama kosong/ganda, kegiatan tidak tersimpan/sudah ada),
 * 403 {pesan} OtorisasiDokumen (cakupan, leading tidak tertaut/nonaktif, tahun terkunci, akun nonaktif), 404 {pesan}.
 * Nama hanya dipakai untuk salinan di tahun aktif yang sama (kegiatan tahun lalu memakai namanya sendiri). Tanpa cache.
 */
class DuplikatDokumen extends Controller
{
    public function __invoke(Request $request, string $dokumen): JsonResponse
    {
        $tanpaCache = ['Cache-Control' => 'no-store, private'];
        try {
            $nama = $request->input('nama');

            return response()->json(AksiRiwayatDokumen::duplikat($dokumen, is_string($nama) ? $nama : null, StubData::currentUser()), 201, $tanpaCache);
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => array_map(fn (array $p) => $p[0], $e->errors())], 422, $tanpaCache);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403, $tanpaCache);
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], 404, $tanpaCache);
        }
    }
}
