<?php

namespace App\Http\Controllers;

use App\Support\Dokumen\DaftarTemplateMaster;
use App\Support\Pengaturan\TahunDipilih;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint daftar template master (JSON, DaftarTemplateMaster::data; rute admin `pengaturan.template.master.data`,
 * `GET /pengaturan/template/master/data?ta=&cari=&leading=&jenis=&status=`): ringkasan, saringan rapi, kelompok per
 * pemilik, leading tanpa template khusus. Tahun tak terdaftar 404; tidak di-cache. Kontrak: docs/api/template-master.md.
 */
class DataTemplateMaster extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(DaftarTemplateMaster::data(TahunDipilih::dariRequest($request), $request->only(DaftarTemplateMaster::SARINGAN)), headers: ['Cache-Control' => 'no-store, private']);
    }
}
