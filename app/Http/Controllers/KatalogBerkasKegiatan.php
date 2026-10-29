<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\Generasi\KatalogBerkas;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;

/**
 * Katalog berkas dokumen satu kegiatan (`GET /kegiatan/{kegiatan}/berkas`, rute `kegiatan.berkas`): format beserta
 * kemampuannya (KatalogBerkas::format) dan berkas yang bisa dibuat (KatalogBerkas::untuk). Hak baca AksesKegiatan; tanpa
 * cache. Kontrak lengkap: docs/api/generasi-dokumen.md.
 */
class KatalogBerkasKegiatan extends Controller
{
    public function __invoke(KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403, AksesKegiatan::PESAN_TOLAK);

        return response()->json([
            'kegiatan_id' => $kegiatan,
            'format' => KatalogBerkas::format(),
            'berkas' => KatalogBerkas::untuk($data),
        ], 200, ['Cache-Control' => 'no-store, private']);
    }
}
