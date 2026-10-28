<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\KelolaArsipFinal;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint hapus arsip final satu sumber dana (`DELETE /kegiatan/{kegiatan}/arsip/{sumber}`, rute `kegiatan.arsip.hapus`):
 * KelolaArsipFinal::hapus. Jawaban {kurang, pesan}; belum ada arsip/sumber tidak dipilih 422, tidak boleh 403, kegiatan
 * tidak ada 404.
 */
class HapusArsipFinal extends Controller
{
    public function __invoke(KegiatanRepository $repo, int $kegiatan, string $sumber): JsonResponse
    {
        $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');

        try {
            $hasil = KelolaArsipFinal::hapus($kegiatan, $sumber, StubData::currentUser());
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => $e->errors()], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        }

        return response()->json($hasil, headers: ['Cache-Control' => 'no-store, private']);
    }
}
