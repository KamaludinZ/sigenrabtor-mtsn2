<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\JawabSimpanPengaturanAi;
use App\Support\Pengaturan\KelolaPromptAi;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Simpan template prompt satu bagian AI (`PUT /pengaturan/ai/prompt/{bagian}` {instruksi, gaya_bahasa (null = gaya
 * baku), aktif, bawaan?, versi?}, rute admin `pengaturan.ai.prompt.simpan`; `bawaan` = kembali ke PromptAi::BAWAAN):
 * KelolaPromptAi::simpanBagian. Jawaban JawabSimpanPengaturanAi (pengaturan = KelolaPromptAi::data).
 */
class SimpanPromptAi extends Controller
{
    use JawabSimpanPengaturanAi;

    public function __invoke(Request $request, string $bagian): JsonResponse
    {
        [$versi, $periksa] = $this->versiPermintaan($request);

        return $this->jawabSimpan(fn () => KelolaPromptAi::simpanBagian($bagian, $request->only(['instruksi', 'gaya_bahasa', 'aktif']), StubData::currentUser(), $versi, $periksa, $request->boolean('bawaan')), KelolaPromptAi::data(...));
    }
}
