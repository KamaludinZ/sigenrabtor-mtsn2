<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\KelolaArsipFinal;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint unggah/ganti arsip final PDF (`POST /kegiatan/{kegiatan}/arsip/{sumber}`, rute `kegiatan.arsip.unggah`;
 * multipart, berkas di isian `berkas`): KelolaArsipFinal::unggah. Jawaban {arsip, ganti, kurang, pesan} (201 bila baru);
 * galat berkas/sumber 422 {pesan, galat}, bukan cakupan akun/status bukan Disetujui/tahun terkunci 403, kegiatan tidak
 * ada 404.
 */
class UnggahArsipFinal extends Controller
{
    public function __invoke(Request $request, KegiatanRepository $repo, int $kegiatan, string $sumber): JsonResponse
    {
        $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');

        try {
            $hasil = KelolaArsipFinal::unggah($kegiatan, $sumber, $request->file('berkas'), StubData::currentUser());
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => $e->errors()], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        }

        return response()->json($hasil, $hasil['ganti'] ? 200 : 201, ['Cache-Control' => 'no-store, private']);
    }
}
