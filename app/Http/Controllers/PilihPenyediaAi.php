<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\JawabSimpanPengaturanAi;
use App\Support\Pengaturan\KelolaPenyediaAi;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pilih penyedia AI aktif & cadangan (`PUT /pengaturan/ai/pilihan` {aktif, cadangan, versi?}, rute admin
 * `pengaturan.ai.pilihan`; kosong/null = tanpa): KelolaPenyediaAi::pilih (keduanya harus siap, cadangan butuh aktif &
 * berbeda). Jawaban JawabSimpanPengaturanAi.
 */
class PilihPenyediaAi extends Controller
{
    use JawabSimpanPengaturanAi;

    public function __invoke(Request $request): JsonResponse
    {
        [$versi, $periksa] = $this->versiPermintaan($request);
        $teks = fn (string $kunci) => is_string($request->input($kunci)) ? $request->input($kunci) : null;

        return $this->jawabSimpan(fn () => KelolaPenyediaAi::pilih($teks('aktif'), $teks('cadangan'), StubData::currentUser(), $versi, $periksa), KelolaPenyediaAi::data(...));
    }
}
