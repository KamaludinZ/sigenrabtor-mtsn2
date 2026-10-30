<?php

namespace App\Http\Controllers;

use App\Support\Akun\KelolaPeran;
use App\Support\Pengaturan\KelolaLeading;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Endpoint admin ubah peran akun (`PUT /pengaturan/akun/{akun}/peran`, rute `pengaturan.akun.peran`; {peran, leadings?,
 * versi?}): KelolaPeran::ubah → 200 {akun, ringkasan, versi, pesan}; 422 {pesan, galat role|leadings}; 403; 404 akun tidak
 * ada; 409 {pesan, versi} versi usang; 503 gagal disimpan; tanpa cache.
 */
class UbahPeranAkun extends Controller
{
    public function __invoke(Request $request, int $akun): JsonResponse
    {
        $tanpaCache = ['Cache-Control' => 'no-store, private'];
        try {
            $hasil = KelolaPeran::ubah($akun, $request->input('peran'), $request->has('leadings') ? array_values(array_map('strval', (array) $request->input('leadings'))) : null, StubData::currentUser(), $request->filled('versi') ? (string) $request->input('versi') : null);
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => array_map(fn (array $p) => $p[0], $e->errors())], 422, $tanpaCache);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403, $tanpaCache);
        } catch (PengaturanBerubah $e) {
            return response()->json(['pesan' => $e->getMessage(), 'versi' => KelolaLeading::sidik()], 409, $tanpaCache);
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], $e->getMessage() === KelolaPeran::PESAN_TIDAK_ADA ? 404 : 503, $tanpaCache);
        }

        return response()->json($hasil, headers: $tanpaCache);
    }
}
