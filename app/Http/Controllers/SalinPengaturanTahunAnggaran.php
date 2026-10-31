<?php

namespace App\Http\Controllers;

use App\Contracts\TahunAnggaranRepository;
use App\Support\Pengaturan\SalinPengaturanTahun;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint salin pengaturan antar tahun (`POST /pengaturan/tahun/{tahun}/salin` {dari?, bagian?: list}, rute admin
 * `pengaturan.tahun.salin`; `dari` bawaan SalinPengaturanTahun::sumber, `bagian` kosong = semua yang bisa): hanya yang di
 * tahun tujuan masih kosong yang diisi. Jawaban {dari, ke, hasil per bagian, disalin, pesan}: 201 bila ada yang disalin,
 * 200 bila tidak; 404 tahun tidak terdaftar, 403 bukan admin/tahun terkunci, 422 {pesan, galat}. Tidak di-cache.
 */
class SalinPengaturanTahunAnggaran extends Controller
{
    public function __invoke(Request $request, int $tahun): JsonResponse
    {
        abort_unless(collect(app(TahunAnggaranRepository::class)->semua())->contains('tahun', $tahun), 404);
        $dari = $request->filled('dari') ? (int) $request->input('dari') : SalinPengaturanTahun::sumber($tahun);
        $bagian = $request->input('bagian');
        if ($dari === null || ($bagian !== null && ! is_array($bagian))) {
            $kunci = $dari === null ? 'dari' : 'bagian';

            return response()->json(['pesan' => $dari === null ? "Belum ada tahun anggaran lain berisi pengaturan untuk disalin ke TA {$tahun}." : 'Bagian berupa daftar.', 'galat' => [$kunci => ['Tidak sah.']]], 422);
        }

        try {
            $hasil = SalinPengaturanTahun::salin($dari, $tahun, $bagian === null ? null : array_values(array_map('strval', $bagian)), StubData::currentUser());
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        } catch (ValidationException $e) {
            return response()->json(['pesan' => $e->validator->errors()->first(), 'galat' => $e->errors()], 422);
        }

        return response()->json($hasil, $hasil['disalin'] > 0 ? 201 : 200, ['Cache-Control' => 'no-store, private']);
    }
}
