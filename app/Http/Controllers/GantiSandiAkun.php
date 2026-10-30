<?php

namespace App\Http\Controllers;

use App\Support\Akun\Autentikasi;
use App\Support\Akun\GantiSandi;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Endpoint ganti sandi akun yang masuk (`PUT /akun/sandi`, rute `akun.sandi.ganti`; {sandi_lama, sandi_baru, sandi_ulang}):
 * GantiSandi::simpan → id sesi diganti, cookie "ingat saya" perangkat ini diterbitkan ulang (perangkat lain gugur);
 * 200 {pesan, diganti_pada}; 422 {pesan, galat}; sandi saat ini salah berulang 429 {pesan, galat, tunggu} + Retry-After;
 * 503 gagal; tanpa cache.
 */
class GantiSandiAkun extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tanpaCache = ['Cache-Control' => 'no-store, private'];
        $pengguna = StubData::currentUser();
        try {
            $hasil = GantiSandi::simpan($pengguna, $request->only(['sandi_lama', 'sandi_baru', 'sandi_ulang']));
        } catch (ValidationException $e) {
            $isi = ['pesan' => collect($e->errors())->flatten()->first(), 'galat' => array_map(fn (array $p) => $p[0], $e->errors())];
            $tunggu = GantiSandi::tunggu((int) $pengguna['id']);

            return $tunggu > 0
                ? response()->json($isi + ['tunggu' => $tunggu], 429, $tanpaCache + ['Retry-After' => (string) $tunggu])
                : response()->json($isi, 422, $tanpaCache);
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], 503, $tanpaCache);
        }
        $request->session()->regenerate();
        if ($request->hasCookie(Autentikasi::COOKIE_INGAT)) {
            Autentikasi::perbaruiIngat((int) $pengguna['id']);
        }

        return response()->json(['pesan' => 'Sandi diganti. Gunakan sandi baru saat masuk berikutnya.', 'diganti_pada' => $hasil['diganti_pada']], headers: $tanpaCache);
    }
}
