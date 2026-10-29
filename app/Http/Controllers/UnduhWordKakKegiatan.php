<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Unduh KAK/TOR sebagai Word (.docx) yang disusun server (`GET /kegiatan/{kegiatan}/kak/word?sumber=&jenis=`, rute
 * `kegiatan.kak.word`): pintasan kontrak `kegiatan.berkas.unduh` dengan format `docx` dan jenis bawaan KAK+TOR (boleh
 * KAK/TOR) — WordKakTor lewat GeneratorBerkasServer; galat & header sama (docs/api/generasi-dokumen.md). Dipakai tombol
 * Word sebagai cadangan bila penyusunan di peramban gagal.
 */
class UnduhWordKakKegiatan extends Controller
{
    public function __invoke(Request $request, KegiatanRepository $repo, int $kegiatan): Response|JsonResponse|RedirectResponse
    {
        $request->query->set('format', 'docx');
        if (blank($request->query('jenis'))) {
            $request->query->set('jenis', 'KAK+TOR');
        }

        return app(UnduhBerkasKegiatan::class)($request, $repo, $kegiatan);
    }
}
