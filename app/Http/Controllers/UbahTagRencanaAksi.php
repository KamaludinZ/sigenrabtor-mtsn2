<?php

namespace App\Http\Controllers;

use App\Support\Master\KelolaRencanaAksi;
use App\Support\Master\RencanaAksi;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Tag kode satu rencana aksi (rute admin `master.rencana_aksi.tag`, `PUT /master/{tahun}/rencana-aksi/{kode}/tag`
 * {kode_sk?, kode_iksk?, kode_rktm?, kode_leading?, kode_edm? (null = otomatis), budaya_mutu?, versi?}) lewat
 * KelolaRencanaAksi::ubahTag → 200 {pesan, berubah, rencana_aksi (+ tag efektif), versi}; 422 {pesan, galat}, 403, 404,
 * 409 {pesan, versi}.
 */
class UbahTagRencanaAksi extends Controller
{
    public function __invoke(Request $request, int $tahun, string $kode): JsonResponse
    {
        $isian = $request->only(['kode_sk', 'kode_iksk', 'kode_rktm', 'kode_leading', 'kode_edm', 'budaya_mutu']);
        $isian['edm_otomatis'] = ! $request->has('kode_edm') || $request->input('kode_edm') === null;

        try {
            $hasil = KelolaRencanaAksi::ubahTag($tahun, $kode, $isian, StubData::currentUser(), $request->filled('versi') ? (string) $request->input('versi') : null);
        } catch (ValidationException $e) {
            return response()->json(['pesan' => $e->validator->errors()->first(), 'galat' => $e->errors()], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        } catch (PengaturanBerubah $e) {
            return response()->json(['pesan' => $e->getMessage(), 'versi' => KelolaRencanaAksi::versi($tahun)], 409);
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], 404);
        }

        return response()->json([
            'pesan' => $hasil['pesan'], 'berubah' => $hasil['berubah'], 'versi' => $hasil['versi'],
            'rencana_aksi' => $hasil['rencana_aksi'] + ['tag' => RencanaAksi::tag($hasil['rencana_aksi'], $tahun)],
        ], headers: ['Cache-Control' => 'no-store']);
    }
}
