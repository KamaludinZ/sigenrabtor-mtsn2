<?php

namespace App\Http\Controllers;

use App\Support\Dokumen\DataRiwayatDokumen;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Daftar Riwayat Dokumen berpenyaring (`GET /dokumen/data?cari=&jenis=&format=&leading=&sumber=&status=&dari=&sampai=
 * &tampilan=&urut=&hal=&per=`, rute `dokumen.data`, semua pengguna — cakupan akun): DataRiwayatDokumen::untuk. Tanpa cache.
 */
class DaftarRiwayatDokumen extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(
            DataRiwayatDokumen::untuk(StubData::currentUser(), $request->only(DataRiwayatDokumen::PARAMETER)),
            200,
            ['Cache-Control' => 'no-store, private'],
        );
    }
}
