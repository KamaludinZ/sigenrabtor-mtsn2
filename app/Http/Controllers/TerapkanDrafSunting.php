<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\DrafSunting;
use App\Support\Kegiatan\UbahDataKegiatan;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Endpoint terapkan draf sunting ke data kegiatan (`POST /kegiatan/{kegiatan}/pratinjau/sunting/{bidang}/terapkan`
 * {versi?, versi_draf?}, rute `kegiatan.sunting.terapkan`): DrafSunting::terapkan (SuntingDokumen::simpan, jejak
 * RiwayatSunting, draf dibuang) → 200 {pesan, diubah, bagian, versi}; tanpa draf/aturan isian 422 {pesan, galat}, 403
 * bukan cakupan/terkunci, 409 {pesan, versi} data atau draf berubah di tempat lain, 404 kegiatan. Tanpa cache.
 */
class TerapkanDrafSunting extends Controller
{
    public function __invoke(Request $request, KegiatanRepository $repo, int $kegiatan, string $bidang): JsonResponse
    {
        $tanpaCache = ['Cache-Control' => 'no-store, private'];
        $versi = fn (string $kunci) => $request->filled($kunci) ? (string) $request->input($kunci) : null;
        try {
            $hasil = DrafSunting::terapkan($kegiatan, $bidang, StubData::currentUser(), $versi('versi'), $versi('versi_draf'));
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => $e->errors()], 422, $tanpaCache);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403, $tanpaCache);
        } catch (PengaturanBerubah $e) {
            $data = $repo->temukan($kegiatan);

            return response()->json(['pesan' => $e->getMessage(), 'versi' => $data ? UbahDataKegiatan::versi($data) : null], 409, $tanpaCache);
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], 404, $tanpaCache);
        }

        return response()->json(['pesan' => $hasil['pesan'], 'diubah' => $hasil['diubah'], 'bagian' => $hasil['bagian'], 'versi' => $hasil['versi']], headers: $tanpaCache);
    }
}
