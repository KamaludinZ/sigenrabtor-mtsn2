<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\JawabSimpanPengaturanAi;
use App\Support\Pengaturan\KelolaBatasAi;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Simpan batas umum permintaan AI per akun per hari (`PUT /pengaturan/ai/batas` {batas, versi?}, rute admin
 * `pengaturan.ai.batas.simpan`): KelolaBatasAi::simpanUmum (BatasAi::RENTANG). Jawaban JawabSimpanPengaturanAi
 * (pengaturan = KelolaBatasAi::data).
 */
class SimpanBatasAi extends Controller
{
    use JawabSimpanPengaturanAi;

    public function __invoke(Request $request): JsonResponse
    {
        [$versi, $periksa] = $this->versiPermintaan($request);

        return $this->jawabSimpan(fn () => KelolaBatasAi::simpanUmum($request->input('batas'), StubData::currentUser(), $versi, $periksa), KelolaBatasAi::data(...));
    }
}
