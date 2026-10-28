<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\JawabSimpanPengaturanAi;
use App\Support\Pengaturan\KelolaModelAi;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Simpan parameter jawaban AI (`PUT /pengaturan/ai/parameter` {suhu, maks_token, jumlah_versi, versi?}, rute admin
 * `pengaturan.ai.parameter.simpan`): KelolaModelAi::simpanParameter (ParameterAi::rapikan). Jawaban
 * JawabSimpanPengaturanAi (pengaturan = KelolaModelAi::dataParameter).
 */
class SimpanParameterAi extends Controller
{
    use JawabSimpanPengaturanAi;

    public function __invoke(Request $request): JsonResponse
    {
        [$versi, $periksa] = $this->versiPermintaan($request);

        return $this->jawabSimpan(fn () => KelolaModelAi::simpanParameter($request->only(['suhu', 'maks_token', 'jumlah_versi']), StubData::currentUser(), $versi, $periksa), KelolaModelAi::dataParameter(...));
    }
}
