<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\DetailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint detail satu template Format Seragam (JSON, DetailTemplate::untuk; rute admin
 * `pengaturan.format.template.detail`, `GET /pengaturan/format/template/{template}?kegiatan=&sumber=`): master KAK/TOR/RAB
 * atau cover; `kegiatan` (id contoh) menambahkan pratinjau terisi. Template/kegiatan tak ada 404, sumber tidak dipilih 422
 * {pesan, galat}; tidak di-cache. Kontrak: docs/api/format-seragam.md.
 */
class DetailTemplateFormat extends Controller
{
    public function __invoke(Request $request, KegiatanRepository $repo, string $template): JsonResponse
    {
        $tanpaCache = ['Cache-Control' => 'no-store, private'];
        $kegiatan = $request->filled('kegiatan') ? ($repo->temukan((int) $request->query('kegiatan')) ?? abort(404, 'Kegiatan contoh tidak ditemukan.')) : null;
        try {
            $detail = DetailTemplate::untuk($template, $kegiatan, $request->filled('sumber') ? (string) $request->query('sumber') : null);
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => array_map(fn (array $p) => $p[0], $e->errors())], 422, $tanpaCache);
        }

        return $detail === null
            ? response()->json(['pesan' => 'Template tidak ditemukan.'], 404, $tanpaCache)
            : response()->json($detail, headers: $tanpaCache);
    }
}
