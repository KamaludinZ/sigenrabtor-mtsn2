<?php

namespace App\Http\Controllers;

use App\Support\Dokumen\KegiatanBuku;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint kegiatan buku dokumen tahun aktif berdasarkan saringan (`GET /buku/kegiatan?leading=&sumber=&status=&draf=
 * &cari=`, rute `buku.kegiatan`, semua pengguna): KegiatanBuku::untuk. Leading di luar cakupan akun → 403. Tanpa cache.
 */
class KegiatanBukuDokumen extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $hasil = KegiatanBuku::untuk(StubData::currentUser(), $request->only(KegiatanBuku::SARINGAN));
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        }

        return response()->json($hasil, headers: ['Cache-Control' => 'no-store, private']);
    }
}
