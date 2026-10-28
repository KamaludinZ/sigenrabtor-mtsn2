<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\JawabSimpanPengaturanAi;
use App\Support\Pengaturan\KelolaBatasAi;
use App\Support\Stub\StubData;
use App\Support\Stub\StubPengaturan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Batas khusus satu akun (`PUT /pengaturan/ai/batas/akun/{akun}` {batas, versi?} — kosong/sama dengan batas umum =
 * kembali ke batas umum; `DELETE` = kembali ke batas umum; rute admin `pengaturan.ai.batas.akun` /
 * `pengaturan.ai.batas.akun.hapus`): KelolaBatasAi::simpanKhusus. Akun tidak ada 404; jawaban lain
 * JawabSimpanPengaturanAi (pengaturan = KelolaBatasAi::data).
 */
class SimpanBatasKhususAi extends Controller
{
    use JawabSimpanPengaturanAi;

    public function __invoke(Request $request, int $akun): JsonResponse
    {
        abort_unless(collect(StubPengaturan::pengguna())->firstWhere('id', $akun) !== null, 404, 'Akun tidak ditemukan.');
        [$versi, $periksa] = $this->versiPermintaan($request);
        $batas = $request->isMethod('delete') ? null : $request->input('batas');

        return $this->jawabSimpan(fn () => KelolaBatasAi::simpanKhusus($akun, $batas, StubData::currentUser(), $versi, $periksa), KelolaBatasAi::data(...));
    }
}
