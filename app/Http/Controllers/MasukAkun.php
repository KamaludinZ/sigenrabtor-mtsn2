<?php

namespace App\Http\Controllers;

use App\Support\Akun\Autentikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint masuk (`POST /masuk`, rute `akun.masuk.kirim`; JSON {surel, sandi, ingat?}): Autentikasi::periksa lalu
 * Autentikasi::masuk (sesi diregenerasi, cookie "ingat saya" bila `ingat`). 200 {pesan, akun (Autentikasi::dataSesi),
 * tujuan (halaman yang diingat atau Dashboard)}; galat 422 {pesan, galat, peringatan?}; terlalu banyak percobaan 429
 * {pesan, galat, tunggu} + Retry-After; tanpa cache.
 */
class MasukAkun extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tanpaCache = ['Cache-Control' => 'no-store, private'];
        $hasil = Autentikasi::periksa((string) $request->input('surel', ''), (string) $request->input('sandi', ''), $request->ip());
        if ($hasil['akun'] === null) {
            $isi = ['pesan' => (string) collect($hasil['galat'])->first(), 'galat' => $hasil['galat'], 'peringatan' => $hasil['peringatan']];

            return $hasil['tunggu'] > 0
                ? response()->json($isi + ['tunggu' => $hasil['tunggu']], 429, $tanpaCache + ['Retry-After' => (string) $hasil['tunggu']])
                : response()->json($isi, 422, $tanpaCache);
        }

        Autentikasi::masuk($hasil['akun'], $request->boolean('ingat'), $request);

        return response()->json([
            'pesan' => 'Selamat datang kembali, '.rtrim((string) $hasil['akun']['name'], '.').'.',
            'akun' => Autentikasi::dataSesi()['akun'],
            'tujuan' => $request->session()->pull('url.intended', route('dashboard')),
        ], headers: $tanpaCache);
    }
}
