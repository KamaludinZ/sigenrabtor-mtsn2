<?php

namespace App\Http\Controllers;

use App\Contracts\PenggunaRepository;
use App\Support\Akun\DataAkun;
use App\Support\Akun\UbahProfil;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Endpoint profil akun yang sedang masuk (rute `akun.profil.data` `GET /akun/profil` & `akun.profil.ubah` `PATCH
 * /akun/profil`): baca = DataAkun::untuk + `versi` (UbahProfil::versi); ubah {nama?, jabatan?, versi?} lewat
 * UbahProfil::simpan (pembaruan sebagian) → 200 {pesan, ringkasan, akun, versi}; 422 {pesan, galat}; 409 {pesan, versi,
 * akun} terbaru; akun tidak tercatat 404; gagal disimpan 503; tanpa cache. Kontrak: docs/api/akun.md.
 */
class ProfilAkunSaya extends Controller
{
    public function tampil(): JsonResponse
    {
        $akun = $this->akun() ?? abort(404, 'Akun Anda belum tercatat.');

        return response()->json($akun, headers: ['Cache-Control' => 'no-store, private']);
    }

    public function ubah(Request $request): JsonResponse
    {
        $tanpaCache = ['Cache-Control' => 'no-store, private'];
        try {
            $hasil = UbahProfil::simpan(StubData::currentUser(), $request->except('versi'), $request->filled('versi') ? (string) $request->input('versi') : null);
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => array_map(fn (array $p) => $p[0], $e->errors())], 422, $tanpaCache);
        } catch (PengaturanBerubah $e) {
            $akun = $this->akun();

            return response()->json(['pesan' => $e->getMessage(), 'versi' => $akun['versi'] ?? null, 'akun' => $akun], 409, $tanpaCache);
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], $this->akun() === null ? 404 : 503, $tanpaCache);
        }

        return response()->json([
            'pesan' => $hasil['ringkasan'] === null ? 'Tidak ada perubahan profil.' : 'Profil disimpan.',
            'ringkasan' => $hasil['ringkasan'],
            'akun' => $this->akun(),
            'versi' => $hasil['versi'],
        ], headers: $tanpaCache);
    }

    /** @return array<string, mixed>|null */
    private function akun(): ?array
    {
        $pengguna = StubData::currentUser();
        $data = DataAkun::untuk($pengguna);
        $tersimpan = app(PenggunaRepository::class)->temukan((int) $pengguna['id']);

        return $data === null || $tersimpan === null ? null : $data + ['versi' => UbahProfil::versi($tersimpan)];
    }
}
