<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\DaftarVariabelTemplate;
use App\Support\Pengaturan\TahunDipilih;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint daftar variabel terdaftar (JSON, DaftarVariabelTemplate::untuk; rute admin `pengaturan.template.variabel`,
 * `GET /pengaturan/template/variabel?ta=&kegiatan=&sumber=`): variabel per kelompok + pemakaian di master tahun itu, dan
 * contoh nilai untuk kegiatan contoh (opsional). Tahun/kegiatan tak dikenal 404, sumber tidak dipilih 422; tidak di-cache.
 * Kontrak: docs/api/template-master.md.
 */
class DaftarVariabelTemplateMaster extends Controller
{
    public function __invoke(Request $request, KegiatanRepository $repo): JsonResponse
    {
        $tanpaCache = ['Cache-Control' => 'no-store, private'];
        $kegiatan = $request->filled('kegiatan') ? ($repo->temukan((int) $request->query('kegiatan')) ?? abort(404, 'Kegiatan contoh tidak ditemukan.')) : null;

        try {
            $data = DaftarVariabelTemplate::untuk(TahunDipilih::dariRequest($request), $kegiatan, $request->filled('sumber') ? (string) $request->query('sumber') : null);
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => array_map(fn (array $p) => $p[0], $e->errors())], 422, $tanpaCache);
        }

        return response()->json($data, headers: $tanpaCache);
    }
}
