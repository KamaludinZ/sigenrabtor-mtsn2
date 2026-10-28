<?php

namespace App\Http\Controllers\Concerns;

use App\Support\Pengaturan\PengaturanBerubah;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Jawaban baku endpoint simpan Pengaturan AI (KelolaPenyediaAi/KelolaModelAi/KelolaPromptAi): 200 {pesan, berubah,
 * pengaturan}; isian salah 422 {pesan, galat}; bukan admin 403; berubah di tempat lain 409 {pesan, versi}; gagal
 * simpan 503 {pesan}. `versi` permintaan (diperbarui_pada saat dimuat; null = belum pernah disimpan) diperiksa hanya
 * bila dikirim.
 */
trait JawabSimpanPengaturanAi
{
    /** @return array{0: string|null, 1: bool} [versi, periksa versi] */
    protected function versiPermintaan(Request $request): array
    {
        return [$request->filled('versi') ? (string) $request->input('versi') : null, $request->exists('versi')];
    }

    /**
     * @param  Closure(): array{pesan: string, berubah: bool}  $simpan
     * @param  Closure(): array<string, mixed>  $data  Pengaturan terbaru (juga `versi` saat bentrok).
     */
    protected function jawabSimpan(Closure $simpan, Closure $data): JsonResponse
    {
        try {
            $hasil = $simpan();
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => $e->errors()], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        } catch (PengaturanBerubah $e) {
            return response()->json(['pesan' => $e->getMessage(), 'versi' => $data()['versi'] ?? null], 409);
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], 503);
        }

        if (($hasil['tersimpan'] ?? true) === false) {
            return response()->json(['pesan' => $hasil['pesan'], 'peringatan' => $hasil['peringatan'] ?? [], 'perlu_konfirmasi' => true], 422);
        }

        return response()->json(['pesan' => $hasil['pesan'], 'berubah' => $hasil['berubah'], 'pengaturan' => $data()], 200, ['Cache-Control' => 'no-store, private']);
    }
}
