<?php

namespace App\Http\Controllers;

use App\Support\Dokumen\PdfBuku;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Endpoint status pembuatan PDF buku (`GET /buku/pdf/{buku}`, rute `buku.pdf.status`): PdfBuku::pantau (selama berjalan
 * pekerjaannya dimajukan) → PdfBuku::data — tahap, persen, langkah, pesan gagal, dan tautan unduh begitu selesai.
 * Buku tidak ada 404, leading di luar cakupan 403. Tanpa cache.
 */
class StatusPdfBuku extends Controller
{
    public function __invoke(string $buku): JsonResponse
    {
        try {
            $data = PdfBuku::pantau($buku, StubData::currentUser());
        } catch (RuntimeException $e) {
            abort(404, $e->getMessage());
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        }

        return response()->json(PdfBuku::data($data), headers: ['Cache-Control' => 'no-store, private']);
    }
}
