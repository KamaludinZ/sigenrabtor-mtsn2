<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\JawabSimpanPengaturanAi;
use App\Support\Pengaturan\KelolaPenyediaAi;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Simpan konfigurasi satu penyedia AI (`PUT /pengaturan/ai/penyedia/{penyedia}`, rute admin `pengaturan.ai.penyedia.simpan`):
 * Ollama {base_url, model, batas_waktu, peran} → KelolaPenyediaAi::simpanLokal; Claude/OpenAI/Gemini {model, kunci?,
 * hapus_kunci?, base_url?, peran, tetap_simpan?} → simpanCloud (kunci dienkripsi, tidak pernah dikembalikan); `versi`
 * diperiksa bila dikirim. Jawaban JawabSimpanPengaturanAi; awalan kunci tak lazim 422 {pesan, peringatan, perlu_konfirmasi}.
 */
class SimpanPenyediaAi extends Controller
{
    use JawabSimpanPengaturanAi;

    public function __invoke(Request $request, string $penyedia): JsonResponse
    {
        [$versi, $periksa] = $this->versiPermintaan($request);
        $pengguna = StubData::currentUser();

        return $this->jawabSimpan(fn () => $penyedia === 'ollama'
            ? KelolaPenyediaAi::simpanLokal($request->only(['base_url', 'model', 'batas_waktu', 'peran']), $pengguna, $versi, $periksa)
            : KelolaPenyediaAi::simpanCloud($penyedia, $request->only(['model', 'kunci', 'hapus_kunci', 'base_url', 'peran']), $pengguna, $versi, $periksa, $request->boolean('tetap_simpan')),
            KelolaPenyediaAi::data(...));
    }
}
