<?php

namespace App\Http\Controllers;

use App\Support\Dokumen\DaftarTemplate;
use App\Support\Pengaturan\TahunDipilih;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint daftar template tersedia untuk Format Seragam (JSON, DaftarTemplate::untuk; rute admin): master KAK/TOR & cover
 * tahun `ta` (TahunDipilih — bawaan tahun aktif, tak terdaftar 404) beserta pemakaiannya; `jenis`/`leading` → `berlaku`;
 * saringan tak dikenal 422 {pesan, galat}; tidak di-cache.
 */
class DaftarTemplateTersedia extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tahun = TahunDipilih::dariRequest($request);
        try {
            $data = DaftarTemplate::untuk($tahun, $request->only(['jenis', 'leading']));
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => array_map(fn (array $p) => $p[0], $e->errors())], 422, ['Cache-Control' => 'no-store, private']);
        }

        return response()->json($data, headers: ['Cache-Control' => 'no-store, private']);
    }
}
