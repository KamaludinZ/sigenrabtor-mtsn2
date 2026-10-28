<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\AlurStatus;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint alur status satu kegiatan (`GET /kegiatan/{kegiatan}/status`, rute `kegiatan.status`): AlurStatus::data —
 * status & deskripsi, langkah alur, aksi untuk peran akun ini beserta halangannya, riwayat, status dokumen tersimpan
 * (StatusDokumen), dan `versi` untuk dikirim balik bersama `kegiatan.status.ubah`. Hak baca AksesKegiatan, tidak di-cache.
 */
class AlurStatusKegiatan extends Controller
{
    public function __invoke(KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403, AksesKegiatan::PESAN_TOLAK);

        return response()->json(AlurStatus::data($data, StubData::currentUser()), headers: ['Cache-Control' => 'no-store, private']);
    }
}
