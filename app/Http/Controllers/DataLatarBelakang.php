<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Support\Pengaturan\KelolaLatarBelakang;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint baca latar belakang baku satu tahun anggaran (JSON, KelolaLatarBelakang::data; rute admin; `ta` tahun
 * terdaftar, bawaan tahun aktif; `kegiatan` = id kegiatan untuk contoh kalimat penutup tercetak), tidak di-cache.
 */
class DataLatarBelakang extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tahun = $request->filled('ta') ? filter_var($request->query('ta'), FILTER_VALIDATE_INT) : StubData::tahunAktif()['tahun'];
        abort_unless($tahun !== false && collect(app(TahunAnggaranRepository::class)->semua())->contains('tahun', $tahun), 404);

        $kegiatan = null;
        if ($request->filled('kegiatan')) {
            $id = filter_var($request->query('kegiatan'), FILTER_VALIDATE_INT);
            $kegiatan = $id !== false ? app(KegiatanRepository::class)->temukan($id) : null;
            abort_if($kegiatan === null, 404);
        }

        return response()->json(KelolaLatarBelakang::data($tahun, $kegiatan), headers: ['Cache-Control' => 'no-store, private']);
    }
}
