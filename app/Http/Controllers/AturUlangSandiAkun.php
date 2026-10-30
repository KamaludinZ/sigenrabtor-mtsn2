<?php

namespace App\Http\Controllers;

use App\Support\Akun\AturUlangSandi;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Endpoint admin atur ulang sandi akun (`POST /pengaturan/akun/{akun}/sandi`, rute `pengaturan.akun.sandi`):
 * AturUlangSandi::aturUlang → 200 {pesan, sandi_sementara (hanya sekali), akun}; akun sendiri 422; bukan admin 403; akun
 * tidak ada 404; tanpa cache.
 */
class AturUlangSandiAkun extends Controller
{
    public function __invoke(int $akun): JsonResponse
    {
        $tanpaCache = ['Cache-Control' => 'no-store, private'];
        try {
            $hasil = AturUlangSandi::aturUlang($akun, StubData::currentUser());
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => array_map(fn (array $p) => $p[0], $e->errors())], 422, $tanpaCache);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403, $tanpaCache);
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], $e->getMessage() === AturUlangSandi::PESAN_TIDAK_ADA ? 404 : 503, $tanpaCache);
        }

        return response()->json([
            'pesan' => "Sandi sementara untuk {$hasil['akun']['nama']} dibuat — sampaikan langsung ke pemilik akun dan minta segera menggantinya.",
            'sandi_sementara' => $hasil['sandi'],
            'akun' => $hasil['akun'],
        ], headers: $tanpaCache);
    }
}
