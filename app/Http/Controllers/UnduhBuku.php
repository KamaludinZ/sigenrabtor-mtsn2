<?php

namespace App\Http\Controllers;

use App\Contracts\BukuDokumenRepository;
use App\Support\Dokumen\BukuDokumen;
use App\Support\Stub\StubData;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Unduh PDF buku dokumen yang sudah dibuat; akun leading hanya buku leading-nya. */
class UnduhBuku extends Controller
{
    public function __invoke(BukuDokumenRepository $repo, string $buku): StreamedResponse
    {
        $data = $repo->temukan($buku) ?? abort(404);
        $pengguna = StubData::currentUser();
        abort_unless($pengguna['role'] === 'admin' || in_array($data['leading'], $pengguna['leadings'], true), 403);
        abort_unless(filled($data['file_pdf_path']) && Storage::disk('local')->exists($data['file_pdf_path']), 404);

        return Storage::disk('local')->download($data['file_pdf_path'], BukuDokumen::namaBerkas($data['leading'], $data['sumber']), ['Content-Type' => 'application/pdf']);
    }
}
