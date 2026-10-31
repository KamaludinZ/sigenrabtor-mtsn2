<?php

namespace App\Http\Controllers;

use App\Contracts\TemplateDokumenRepository;
use App\Support\Dokumen\DaftarTemplateMaster;
use App\Support\Pengaturan\KelolaTemplateMaster;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Endpoint ubah template master (rute admin `pengaturan.template.master.ubah`, `PATCH /pengaturan/template/master/{template}`
 * {nama?, catatan?, aktif?, default?: true}): pembaruan sebagian lewat KelolaTemplateMaster — nama & catatan (`ubah`; kunci
 * yang tidak dikirim tetap), status (`ubahStatus`), lalu jadikan default (`jadikanDefault`). 200 {pesan, perubahan, template};
 * 422 {pesan, galat}; 403 tahun terkunci; 404.
 */
class UbahTemplateMaster extends Controller
{
    public function __invoke(Request $request, string $template): JsonResponse
    {
        $master = app(TemplateDokumenRepository::class)->temukan($template);
        if ($master === null) {
            return response()->json(['pesan' => KelolaTemplateMaster::PESAN_TIDAK_ADA], 404);
        }
        $pengguna = StubData::currentUser();
        $perubahan = [];

        try {
            if ($request->hasAny(['nama', 'catatan'])) {
                $hasil = KelolaTemplateMaster::ubah($template, [
                    'nama' => $request->has('nama') ? $request->input('nama') : $master['nama'],
                    'catatan' => $request->has('catatan') ? $request->input('catatan') : ($master['catatan'] ?? null),
                ], $pengguna);
                if ($hasil['ringkasan'] !== null) {
                    $perubahan[] = $hasil['ringkasan'];
                }
            }
            if ($request->has('aktif') && KelolaTemplateMaster::ubahStatus($template, $request->boolean('aktif'), $pengguna)['berubah']) {
                $perubahan[] = $request->boolean('aktif') ? 'Diaktifkan kembali' : 'Dinonaktifkan';
            }
            if ($request->boolean('default') && KelolaTemplateMaster::jadikanDefault($template, $pengguna)['berubah']) {
                $perubahan[] = 'Menjadi default';
            }
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => array_map(fn (array $p) => $p[0], $e->errors())], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], 404);
        }

        return response()->json([
            'pesan' => $perubahan === [] ? 'Template tidak berubah.' : 'Template diperbarui.',
            'perubahan' => $perubahan,
            'template' => DaftarTemplateMaster::baris($template),
        ], headers: ['Cache-Control' => 'no-store, private']);
    }
}
