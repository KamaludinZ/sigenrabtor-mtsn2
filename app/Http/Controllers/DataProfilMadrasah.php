<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan\KelolaProfilMadrasah;
use Illuminate\Http\JsonResponse;

/** Endpoint baca profil madrasah (JSON, KelolaProfilMadrasah::data; semua pengguna — identitas kop dokumen), tidak di-cache. */
class DataProfilMadrasah extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(KelolaProfilMadrasah::data(), headers: ['Cache-Control' => 'no-store, private']);
    }
}
