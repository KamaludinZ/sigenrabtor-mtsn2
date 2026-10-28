<?php

namespace App\Http\Controllers;

use App\Support\Kegiatan\KeputusanSaranAi;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Catat keputusan atas saran AI (`PUT /saran-ai/{permintaan}/keputusan` {versi: nomor 1..|null, teks_dipakai?}, rute
 * `saran_ai.putuskan`): KeputusanSaranAi::putuskan → 200 {pesan, permintaan}. Isian salah 422 {pesan, galat}; bukan
 * peminta/di luar cakupan 403; sudah diputuskan 409; permintaan tidak ada 404.
 */
class PutuskanSaranAi extends Controller
{
    public function __invoke(Request $request, string $permintaan): JsonResponse
    {
        try {
            $hasil = KeputusanSaranAi::putuskan($permintaan, $request->input('versi'), $request->input('teks_dipakai'), StubData::currentUser());
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => $e->errors()], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        } catch (PengaturanBerubah $e) {
            return response()->json(['pesan' => $e->getMessage()], 409);
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], 404);
        }

        return response()->json([
            'pesan' => match ($hasil['keputusan']) {
                'dipakai' => "Versi {$hasil['versi_dipakai']} tercatat dipakai.",
                default => 'Semua versi tercatat ditolak.',
            },
            'permintaan' => $hasil,
        ], 200, ['Cache-Control' => 'no-store, private']);
    }
}
