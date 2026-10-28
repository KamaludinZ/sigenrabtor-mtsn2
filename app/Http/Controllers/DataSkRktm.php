<?php

namespace App\Http\Controllers;

use App\Contracts\TahunAnggaranRepository;
use App\Support\Pengaturan\KelolaSkRktm;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint baca SK RKTM/RKAM satu tahun anggaran (JSON, KelolaSkRktm::data; rute admin; `ta` tahun terdaftar, bawaan
 * tahun aktif — tahun tak terdaftar 404), tidak di-cache.
 */
class DataSkRktm extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tahun = $request->filled('ta') ? filter_var($request->query('ta'), FILTER_VALIDATE_INT) : StubData::tahunAktif()['tahun'];
        abort_unless($tahun !== false && collect(app(TahunAnggaranRepository::class)->semua())->contains('tahun', $tahun), 404);

        return response()->json(KelolaSkRktm::data($tahun), headers: ['Cache-Control' => 'no-store, private']);
    }
}
