<?php

namespace App\Http\Controllers;

use App\Support\Akun\KeputusanPendaftaran;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Endpoint admin keputusan permohonan akun (`POST /pengaturan/akun/permohonan/{permohonan}/{setujui|tolak}`, rute
 * `pengaturan.akun.permohonan.putuskan`): setujui {leadings?: [kode…]} → akun leading dibuat; tolak {alasan}. 200
 * {permohonan, akun?, pesan}; 422 {pesan, galat}; 403; 404 permohonan tidak ada; 409 sudah diputuskan; 503 gagal disimpan.
 */
class PutuskanPermohonanAkun extends Controller
{
    public function __invoke(Request $request, string $permohonan, string $keputusan): JsonResponse
    {
        $tanpaCache = ['Cache-Control' => 'no-store, private'];
        $pengguna = StubData::currentUser();
        try {
            $hasil = $keputusan === 'setujui'
                ? KeputusanPendaftaran::setujui($permohonan, $pengguna, $request->has('leadings') ? array_values(array_map('strval', (array) $request->input('leadings'))) : null)
                : KeputusanPendaftaran::tolak($permohonan, $request->input('alasan'), $pengguna);
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => array_map(fn (array $p) => $p[0], $e->errors())], 422, $tanpaCache);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403, $tanpaCache);
        } catch (PengaturanBerubah $e) {
            return response()->json(['pesan' => $e->getMessage()], 409, $tanpaCache);
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], $e->getMessage() === KeputusanPendaftaran::PESAN_TIDAK_ADA ? 404 : 503, $tanpaCache);
        }

        return response()->json($hasil, headers: $tanpaCache);
    }
}
