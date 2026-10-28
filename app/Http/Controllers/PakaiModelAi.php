<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\JawabSimpanPengaturanAi;
use App\Support\Pengaturan\KelolaModelAi;
use App\Support\Pengaturan\KelolaPenyediaAi;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pakai satu model untuk penyedia AI (`PUT /pengaturan/ai/model/{penyedia}` {model, versi?}, rute admin
 * `pengaturan.ai.model.simpan`): KelolaModelAi::pakai (model harus ada di daftar penyedia bila daftarnya tidak kosong).
 * Jawaban JawabSimpanPengaturanAi (pengaturan = KelolaPenyediaAi::data).
 */
class PakaiModelAi extends Controller
{
    use JawabSimpanPengaturanAi;

    public function __invoke(Request $request, string $penyedia): JsonResponse
    {
        [$versi, $periksa] = $this->versiPermintaan($request);

        return $this->jawabSimpan(fn () => KelolaModelAi::pakai($penyedia, $request->input('model'), StubData::currentUser(), $versi, $periksa), KelolaPenyediaAi::data(...));
    }
}
