<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan\KelolaPenyediaAi;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Pilih penyedia AI aktif & cadangan (`PUT /pengaturan/ai/pilihan` {aktif, cadangan, versi?}, rute admin
 * `pengaturan.ai.pilihan`; kosong/null = tanpa): KelolaPenyediaAi::pilih (keduanya harus siap, cadangan butuh aktif &
 * berbeda). 200 {pesan, berubah, pengaturan}; isian salah 422 {pesan, galat}; berubah di tempat lain 409 {pesan, versi};
 * gagal simpan 503 {pesan}.
 */
class PilihPenyediaAi extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $teks = fn (string $kunci) => is_string($request->input($kunci)) ? $request->input($kunci) : null;

        try {
            $hasil = KelolaPenyediaAi::pilih($teks('aktif'), $teks('cadangan'), StubData::currentUser(), $request->filled('versi') ? (string) $request->input('versi') : null, $request->exists('versi'));
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => $e->errors()], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        } catch (PengaturanBerubah $e) {
            return response()->json(['pesan' => $e->getMessage(), 'versi' => KelolaPenyediaAi::data()['versi']], 409);
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], 503);
        }

        return response()->json(['pesan' => $hasil['pesan'], 'berubah' => $hasil['berubah'], 'pengaturan' => KelolaPenyediaAi::data()], 200, ['Cache-Control' => 'no-store, private']);
    }
}
