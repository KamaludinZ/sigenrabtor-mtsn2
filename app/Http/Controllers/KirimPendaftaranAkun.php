<?php

namespace App\Http\Controllers;

use App\Support\Akun\PendaftaranAkun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Endpoint pendaftaran akun (`POST /daftar`, rute `akun.daftar.kirim`; terbuka tanpa masuk, dibatasi `throttle`):
 * PendaftaranAkun::ajukan dengan isian PendaftaranAkun::ISIAN → 201 {permohonan (tanpa hash sandi), pesan}; galat aturan
 * 422 {pesan, galat}; gagal disimpan 503 {pesan}; tanpa cache. Akun baru aktif setelah admin menyetujui permohonannya.
 */
class KirimPendaftaranAkun extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tanpaCache = ['Cache-Control' => 'no-store, private'];
        try {
            $permohonan = PendaftaranAkun::ajukan($request->only(PendaftaranAkun::ISIAN));
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => array_map(fn (array $p) => $p[0], $e->errors())], 422, $tanpaCache);
        } catch (RuntimeException $e) {
            report($e);

            return response()->json(['pesan' => $e->getMessage()], 503, $tanpaCache);
        }

        return response()->json([
            'permohonan' => $permohonan,
            'pesan' => 'Permohonan akun terkirim — admin madrasah akan memeriksa lalu mengaktifkannya.',
        ], 201, $tanpaCache);
    }
}
