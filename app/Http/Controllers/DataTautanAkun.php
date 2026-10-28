<?php

namespace App\Http\Controllers;

use App\Contracts\LeadingRepository;
use App\Contracts\PenggunaRepository;
use App\Support\Pengaturan\TautanAkunLeading;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint baca tautan satu akun ke leading (`GET /pengaturan/akun/{akun}/leading`, rute admin
 * `pengaturan.akun.leading`): akun, tautannya (TautanAkunLeading::untukAkun), dan leading aktif yang masih bisa
 * ditautkan (kosong untuk akun admin). Akun tidak dikenal → 404. Tidak di-cache.
 */
class DataTautanAkun extends Controller
{
    public function __invoke(int $akun): JsonResponse
    {
        $data = app(PenggunaRepository::class)->temukan($akun) ?? abort(404);

        return response()->json([
            'akun' => ['id' => $data['id'], 'name' => $data['name'], 'role' => $data['role'], 'is_active' => $data['is_active']],
            'tautan' => TautanAkunLeading::untukAkun($akun),
            'bisa_ditautkan' => $data['role'] !== 'leading' ? [] : array_values(array_map(
                fn (array $l) => ['kode' => $l['kode'], 'nama_unit' => $l['nama_unit']],
                array_filter(app(LeadingRepository::class)->semua(), fn (array $l) => $l['is_active'] && ! in_array($l['kode'], $data['leadings'], true)),
            )),
        ], headers: ['Cache-Control' => 'no-store, private']);
    }
}
