<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan\DataTahunAktif;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;

/** Endpoint tahun anggaran aktif (`GET /tahun-aktif`, rute `tahun_aktif`, semua pengguna): DataTahunAktif::untuk, tanpa cache. */
class TahunAnggaranAktif extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(DataTahunAktif::untuk(StubData::currentUser()), headers: ['Cache-Control' => 'no-store, private']);
    }
}
