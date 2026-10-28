<?php

namespace App\Http\Controllers;

use App\Support\FormatRupiah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint format rupiah & terbilang (`GET /format/rupiah?nilai=`, rute `format.rupiah`, semua pengguna):
 * FormatRupiah::untuk. Masukan tidak sah dijawab 422 dengan bentuk yang sama (`sah` false, `galat`).
 */
class FormatRupiahTerbilang extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $nilai = $request->query('nilai');
        $hasil = FormatRupiah::untuk(is_array($nilai) ? null : $nilai);

        return response()->json($hasil, $hasil['sah'] ? 200 : 422);
    }
}
