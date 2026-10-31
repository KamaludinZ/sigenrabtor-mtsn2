<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan\UjiKoneksiAi;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

/**
 * Uji koneksi satu penyedia AI dengan pengaturan tersimpan (`POST /pengaturan/ai/penyedia/{penyedia}/uji`, rute admin
 * `pengaturan.ai.uji`): UjiKoneksiAi::uji → 200 {hasil} (berhasil atau gagal berjenis KegagalanAi, termasuk konfigurasi
 * belum lengkap), 429 {pesan} + Retry-After bila terlalu sering, 403 bukan admin. Tanpa cache.
 */
class UjiKoneksiPenyediaAi extends Controller
{
    public function __invoke(string $penyedia): JsonResponse
    {
        $pengguna = StubData::currentUser();
        $kepala = ['Cache-Control' => 'no-store, private'];
        if (($tunggu = UjiKoneksiAi::tunggu($pengguna)) > 0) {
            return response()->json(['pesan' => "Uji koneksi terlalu sering — coba lagi dalam {$tunggu} detik."], 429, $kepala + ['Retry-After' => (string) $tunggu]);
        }

        try {
            return response()->json(['hasil' => UjiKoneksiAi::uji($penyedia, $pengguna)], 200, $kepala);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403, $kepala);
        }
    }
}
