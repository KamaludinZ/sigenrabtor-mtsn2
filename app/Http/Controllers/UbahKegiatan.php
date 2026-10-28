<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\UbahDataKegiatan;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint ubah data kegiatan (`PATCH /kegiatan/{kegiatan}`, rute `kegiatan.ubah`; isian = kunci UbahDataKegiatan::ISIAN
 * yang diubah saja, `versi` opsional dari `kegiatan.data`): UbahDataKegiatan::ubah. Jawaban {id, status, diubah, bagian,
 * isian, versi, pesan}; galat isian 422 {pesan, galat}, kegiatan berubah di tempat lain 409 {pesan, versi, isian}
 * (data terbaru), bukan cakupan akun/terkunci 403, kegiatan tidak ada 404.
 */
class UbahKegiatan extends Controller
{
    public function __invoke(Request $request, KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403, AksesKegiatan::PESAN_TOLAK);

        try {
            $hasil = UbahDataKegiatan::ubah($kegiatan, $request->except(['versi', '_token', '_method']), StubData::currentUser(), $request->has('versi') ? (string) $request->input('versi') : null);
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => $e->errors()], 422);
        } catch (PengaturanBerubah $e) {
            $terbaru = $repo->temukan($kegiatan);

            return response()->json(['pesan' => $e->getMessage(), 'versi' => UbahDataKegiatan::versi($terbaru), 'isian' => UbahDataKegiatan::isian($terbaru)], 409);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        }

        return response()->json([
            'id' => $kegiatan,
            'status' => $hasil['kegiatan']['status'] ?? 'draf',
            'diubah' => $hasil['diubah'],
            'bagian' => $hasil['bagian'],
            'isian' => UbahDataKegiatan::isian($hasil['kegiatan']),
            'versi' => $hasil['versi'],
            'pesan' => $hasil['pesan'],
        ], headers: ['Cache-Control' => 'no-store, private']);
    }
}
