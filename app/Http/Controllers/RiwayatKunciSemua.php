<?php

namespace App\Http\Controllers;

use App\Support\Kegiatan\RiwayatKunci;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint admin riwayat kunci & pembukaan semua kegiatan (`GET /pemeriksaan/riwayat-kunci`, rute
 * `pemeriksaan.riwayat_kunci`; `?tahun=` bawaan tahun aktif, `?aksi=` salah satu RiwayatKunci::AKSI, `?hal=` mulai 1,
 * RiwayatKunci::PER_HALAMAN per halaman): {tahun, aksi, jumlah, halaman, per_halaman, riwayat (+ kegiatan)}. Tanpa cache.
 */
class RiwayatKunciSemua extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tahun = filter_var($request->query('tahun'), FILTER_VALIDATE_INT) ?: StubData::tahunAktif()['tahun'];
        $aksi = isset(RiwayatKunci::AKSI[$a = (string) $request->query('aksi')]) ? $a : '';
        $halaman = max(1, (int) $request->query('hal', 1));
        $semua = RiwayatKunci::semua($tahun, $aksi);

        return response()->json([
            'tahun' => $tahun,
            'aksi' => $aksi,
            'jumlah' => count($semua),
            'halaman' => $halaman,
            'per_halaman' => RiwayatKunci::PER_HALAMAN,
            'riwayat' => array_slice($semua, ($halaman - 1) * RiwayatKunci::PER_HALAMAN, RiwayatKunci::PER_HALAMAN),
        ], headers: ['Cache-Control' => 'no-store, private']);
    }
}
