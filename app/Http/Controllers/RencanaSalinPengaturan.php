<?php

namespace App\Http\Controllers;

use App\Contracts\TahunAnggaranRepository;
use App\Support\Pengaturan\SalinPengaturanTahun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint rencana salin pengaturan antar tahun (`GET /pengaturan/tahun/{tahun}/salin?dari=`, rute admin
 * `pengaturan.tahun.salin.rencana`; `dari` bawaan SalinPengaturanTahun::sumber): keadaan tiap bagian tanpa menyalin.
 * 404 tahun tidak terdaftar, 422 tidak ada tahun sumber / sumber tidak sah. Tidak di-cache.
 */
class RencanaSalinPengaturan extends Controller
{
    public function __invoke(Request $request, int $tahun): JsonResponse
    {
        abort_unless(collect(app(TahunAnggaranRepository::class)->semua())->contains('tahun', $tahun), 404);
        $dari = $request->filled('dari') ? (int) $request->input('dari') : SalinPengaturanTahun::sumber($tahun);
        if ($dari === null) {
            return response()->json(['pesan' => "Belum ada tahun anggaran lain berisi pengaturan untuk disalin ke TA {$tahun}.", 'galat' => ['dari' => ['Tidak ada tahun sumber.']]], 422);
        }

        try {
            $rencana = SalinPengaturanTahun::rencana($dari, $tahun);
        } catch (ValidationException $e) {
            return response()->json(['pesan' => $e->validator->errors()->first(), 'galat' => $e->errors()], 422);
        }

        return response()->json($rencana + ['sumber_disarankan' => SalinPengaturanTahun::sumber($tahun)], headers: ['Cache-Control' => 'no-store, private']);
    }
}
