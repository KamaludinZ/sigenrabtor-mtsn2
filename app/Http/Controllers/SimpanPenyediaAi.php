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
 * Simpan konfigurasi satu penyedia AI (`PUT /pengaturan/ai/penyedia/{penyedia}`, rute admin `pengaturan.ai.penyedia.simpan`):
 * Ollama {base_url, model, batas_waktu, peran} → KelolaPenyediaAi::simpanLokal; Claude/OpenAI/Gemini {model, kunci?,
 * hapus_kunci?, base_url?, peran, tetap_simpan?} → simpanCloud (kunci dienkripsi, tidak pernah dikembalikan). `versi`
 * (diperbarui_pada saat dimuat; null = belum pernah disimpan) bila dikirim diperiksa. 200 {pesan, berubah, pengaturan};
 * awalan kunci tak lazim 422 {pesan, peringatan, perlu_konfirmasi}; isian salah 422 {pesan, galat}; berubah di tempat lain
 * 409 {pesan, versi}; gagal simpan 503 {pesan}.
 */
class SimpanPenyediaAi extends Controller
{
    public function __invoke(Request $request, string $penyedia): JsonResponse
    {
        $pengguna = StubData::currentUser();
        $periksaVersi = $request->exists('versi');
        $versi = $request->filled('versi') ? (string) $request->input('versi') : null;

        try {
            $hasil = $penyedia === 'ollama'
                ? KelolaPenyediaAi::simpanLokal($request->only(['base_url', 'model', 'batas_waktu', 'peran']), $pengguna, $versi, $periksaVersi)
                : KelolaPenyediaAi::simpanCloud($penyedia, $request->only(['model', 'kunci', 'hapus_kunci', 'base_url', 'peran']), $pengguna, $versi, $periksaVersi, $request->boolean('tetap_simpan'));
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => $e->errors()], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        } catch (PengaturanBerubah $e) {
            return response()->json(['pesan' => $e->getMessage(), 'versi' => KelolaPenyediaAi::data()['versi']], 409);
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], 503);
        }

        if (($hasil['tersimpan'] ?? true) === false) {
            return response()->json(['pesan' => $hasil['pesan'], 'peringatan' => $hasil['peringatan'], 'perlu_konfirmasi' => true], 422);
        }

        return response()->json(['pesan' => $hasil['pesan'], 'berubah' => $hasil['berubah'], 'pengaturan' => KelolaPenyediaAi::data()], 200, ['Cache-Control' => 'no-store, private']);
    }
}
