<?php

namespace App\Http\Controllers;

use App\Support\Dokumen\PdfBuku;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint riwayat buku dokumen tahun aktif (`GET /buku/riwayat?leading=&sumber=`, rute `buku.riwayat`): PdfBuku::riwayat
 * dalam cakupan akun, terbaru dulu, beserta status PDF & tautan unduh. Tanpa cache.
 */
class RiwayatBukuDokumen extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $riwayat = PdfBuku::riwayat(StubData::currentUser(), (string) $request->query('leading', ''), (string) $request->query('sumber', ''));

        return response()->json(['jumlah' => count($riwayat), 'riwayat' => $riwayat], headers: ['Cache-Control' => 'no-store, private']);
    }
}
