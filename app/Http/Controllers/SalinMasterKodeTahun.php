<?php

namespace App\Http\Controllers;

use App\Support\Master\SalinMasterKode;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint salin master kode antar tahun (`POST /master/{tahun}/salin`, rute admin `master.salin`; `dari` = tahun
 * sumber, bawaan SalinMasterKode::sumber): jenis yang di tahun tujuan masih kosong diisi salinan. Jawaban JSON
 * {tahun, dari, disalin, dilewati}; 403 izin, 422 sumber tidak sah.
 */
class SalinMasterKodeTahun extends Controller
{
    public function __invoke(Request $request, int $tahun): JsonResponse
    {
        $dari = $request->filled('dari') ? (int) $request->input('dari') : SalinMasterKode::sumber($tahun);

        try {
            $hasil = SalinMasterKode::salin((int) $dari, $tahun, StubData::currentUser());
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        } catch (ValidationException $e) {
            return response()->json(['pesan' => $e->validator->errors()->first()], 422);
        }

        return response()->json(['tahun' => $tahun, 'dari' => (int) $dari] + $hasil, $hasil['disalin'] === [] ? 200 : 201, ['Cache-Control' => 'no-store, private']);
    }
}
