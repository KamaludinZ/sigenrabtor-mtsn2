<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Unduhan berkas yang diambil klien (`ambil()` di resources/js/unduhan.js: `X-Requested-With` + `Accept: <mime berkas>,
 * application/json`) harus dijawab JSON saat gagal, padahal `expectsJson()` hanya melihat jenis pertama yang diterima.
 * Navigasi biasa (tautan, tab baru) tetap dialihkan dengan notifikasi.
 */
trait PermintaanUnduhan
{
    protected function inginJson(Request $request): bool
    {
        return $request->expectsJson() || ($request->ajax() && Str::contains(strtolower((string) $request->header('Accept', '')), ['/json', '+json']));
    }
}
