<?php

namespace App\Http\Controllers;

use App\Contracts\TemplateDokumenRepository;
use App\Support\Dokumen\DaftarTemplateMaster;
use App\Support\Dokumen\DetailTemplate;
use App\Support\Pengaturan\KelolaTemplateMaster;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint detail satu template master (JSON; rute admin `pengaturan.template.master.detail`,
 * `GET /pengaturan/template/master/{template}`): baris daftar (pemilik, catatan versi, aktif, default, pengunggah,
 * pemakaian), isi master DetailTemplate (variabel terdaftar/tidak, paragraf, berkas), `jumlah` kemunculan tiap variabel,
 * dan `dampak_nonaktif` (KelolaTemplateMaster::dampak). Bukan master / tidak ada 404; tidak di-cache.
 */
class DetailTemplateMaster extends Controller
{
    public function __invoke(string $template): JsonResponse
    {
        $tanpaCache = ['Cache-Control' => 'no-store, private'];
        $baris = DaftarTemplateMaster::baris($template);
        if ($baris === null) {
            return response()->json(['pesan' => 'Template master tidak ditemukan.'], 404, $tanpaCache);
        }

        return response()->json([
            'template' => $baris,
            'isi' => DetailTemplate::untuk($template),
            'jumlah' => app(TemplateDokumenRepository::class)->temukan($template)['jumlah'] ?? [],
            'dampak_nonaktif' => $baris['aktif'] ? KelolaTemplateMaster::dampak($template) : null,
        ], headers: $tanpaCache);
    }
}
