<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Pemeriksaan\KeputusanPemeriksaan;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint ubah status dokumen oleh admin (`POST /pemeriksaan/{kegiatan}/keputusan`, rute admin `pemeriksaan.putuskan`;
 * isian `aksi` setujui|kembalikan|buka_kunci, `catatan`, `versi` opsional dari tampilan): KeputusanPemeriksaan::putuskan.
 * Jawaban {id, status, label_status, entri, pesan, versi}; galat isian 422 {pesan, galat}, versi usang 409, kegiatan
 * tidak ada 404.
 */
class PutuskanPemeriksaan extends Controller
{
    public function __invoke(Request $request, int $kegiatan): JsonResponse
    {
        app(KegiatanRepository::class)->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');

        try {
            $hasil = KeputusanPemeriksaan::putuskan($kegiatan, (string) $request->input('aksi'), $request->input('catatan'), StubData::currentUser(), $request->filled('versi') ? (string) $request->input('versi') : null);
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => $e->errors()], 422);
        } catch (PengaturanBerubah $e) {
            return response()->json(['pesan' => $e->getMessage()], 409);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        }

        return response()->json([
            'id' => $kegiatan,
            'status' => $hasil['kegiatan']['status'],
            'label_status' => StatusKegiatan::from($hasil['kegiatan']['status'])->label(),
            'entri' => $hasil['entri'],
            'pesan' => $hasil['pesan'],
            'versi' => $hasil['versi'],
        ], headers: ['Cache-Control' => 'no-store, private']);
    }
}
