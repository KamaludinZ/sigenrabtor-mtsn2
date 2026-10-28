<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Pengaturan\KelolaLatarBelakang;
use App\Support\Pengaturan\TahunDipilih;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint baca latar belakang baku satu tahun anggaran (JSON, KelolaLatarBelakang::data; rute admin; `ta` lewat
 * TahunDipilih — bawaan tahun aktif, tahun tak terdaftar 404; `kegiatan` = id kegiatan untuk contoh kalimat penutup tercetak), tidak di-cache.
 */
class DataLatarBelakang extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tahun = TahunDipilih::dariRequest($request);

        $kegiatan = null;
        if ($request->filled('kegiatan')) {
            $id = filter_var($request->query('kegiatan'), FILTER_VALIDATE_INT);
            $kegiatan = $id !== false ? app(KegiatanRepository::class)->temukan($id) : null;
            abort_if($kegiatan === null, 404);
        }

        return response()->json(KelolaLatarBelakang::data($tahun, $kegiatan), headers: ['Cache-Control' => 'no-store, private']);
    }
}
