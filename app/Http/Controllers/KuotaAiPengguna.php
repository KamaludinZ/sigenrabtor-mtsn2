<?php

namespace App\Http\Controllers;

use App\Support\Kegiatan\BatasAi;
use App\Support\Kegiatan\StatusKuotaAi;
use App\Support\Stub\StubData;
use App\Support\Stub\StubPengaturan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cek kuota AI harian akun (`GET /ai/kuota?akun=`, rute `ai.kuota`, semua pengguna): StatusKuotaAi::untuk (batas berlaku,
 * terpakai, sisa, persen, tingkat + label, khusus, pulih, rincian per fitur) + `boleh` (masih ada jatah) dan
 * `pesan_habis` (BatasAi::pesanHabis). Tanpa `akun` = akun sendiri; akun lain hanya untuk admin (selain itu 403), akun
 * tidak ada 404. Tanpa cache.
 */
class KuotaAiPengguna extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $saya = StubData::currentUser();
        $pengguna = $saya;
        if ($request->filled('akun') && (int) $request->query('akun') !== (int) $saya['id']) {
            abort_unless($saya['role'] === 'admin', 403, 'Hanya admin yang bisa melihat kuota akun lain.');
            $pengguna = collect(StubPengaturan::pengguna())->firstWhere('id', (int) $request->query('akun')) ?? abort(404, 'Akun tidak ditemukan.');
        }

        $status = StatusKuotaAi::untuk($pengguna);

        return response()->json([
            'akun' => ['id' => (int) $pengguna['id'], 'nama' => $pengguna['name']],
            'tanggal' => now()->toDateString(),
            ...$status,
            'label_tingkat' => StatusKuotaAi::TINGKAT[$status['tingkat']][0] ?? $status['tingkat'],
            'boleh' => $status['sisa'] > 0,
            'pesan_habis' => $status['sisa'] > 0 ? null : BatasAi::pesanHabis($pengguna),
        ], 200, ['Cache-Control' => 'no-store, private']);
    }
}
