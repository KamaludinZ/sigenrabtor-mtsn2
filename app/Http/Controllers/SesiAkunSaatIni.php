<?php

namespace App\Http\Controllers;

use App\Support\Akun\Autentikasi;
use Illuminate\Http\JsonResponse;

/** Endpoint state sesi akun (`GET /akun/sesi`, rute `akun.sesi`, terbuka): Autentikasi::dataSesi — sudah masuk, akun, wajib masuk, mode demo, tautan; tanpa cache. */
class SesiAkunSaatIni extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(Autentikasi::dataSesi(), headers: ['Cache-Control' => 'no-store, private']);
    }
}
