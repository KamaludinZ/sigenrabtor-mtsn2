<?php

namespace App\Http\Controllers;

use App\Support\Dokumen\PdfBuku;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint pemicu pembuatan PDF buku (`POST /buku/pdf` {leading, sumber}, rute `buku.pdf.mulai`, semua pengguna):
 * PdfBuku::mulai. Dimulai → 202 {pesan, buku}; pembuatan buku yang sama masih berjalan → 409 {pesan, buku}; isian salah
 * atau buku kosong 422 {pesan, galat}; leading di luar cakupan 403. Status dipantau lewat `buku.pdf.status`.
 */
class MulaiPdfBuku extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $hasil = PdfBuku::mulai(StubData::currentUser(), (string) $request->input('leading'), (string) $request->input('sumber'));
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => $e->errors()], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        }

        return $hasil['baru']
            ? response()->json(['pesan' => 'Pembuatan PDF buku dimulai.', 'buku' => PdfBuku::data($hasil['buku'])], 202)
            : response()->json(['pesan' => 'PDF buku ini sedang dibuat — pantau statusnya.', 'buku' => PdfBuku::data($hasil['buku'])], 409);
    }
}
