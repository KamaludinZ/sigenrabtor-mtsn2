<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\JawabSimpanPengaturanAi;
use App\Support\Pengaturan\KelolaPromptAi;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Simpan gaya bahasa baku semua bagian AI (`PUT /pengaturan/ai/prompt/gaya` {gaya_bahasa, bawaan?, versi?}, rute admin
 * `pengaturan.ai.prompt.gaya`; `bawaan` = kembali ke PromptAi::GAYA_BAHASA): KelolaPromptAi::simpanGaya. Jawaban
 * JawabSimpanPengaturanAi (pengaturan = KelolaPromptAi::data).
 */
class SimpanGayaBahasaAi extends Controller
{
    use JawabSimpanPengaturanAi;

    public function __invoke(Request $request): JsonResponse
    {
        [$versi, $periksa] = $this->versiPermintaan($request);

        return $this->jawabSimpan(fn () => KelolaPromptAi::simpanGaya($request->input('gaya_bahasa'), StubData::currentUser(), $versi, $periksa, $request->boolean('bawaan')), KelolaPromptAi::data(...));
    }
}
