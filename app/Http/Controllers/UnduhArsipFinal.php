<?php

namespace App\Http\Controllers;

use App\Contracts\ArsipFinalRepository;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Arsip final PDF satu kegiatan × sumber dana (disk privat), dibuka di tab baru dengan nama berkas aslinya. */
class UnduhArsipFinal extends Controller
{
    public function __invoke(ArsipFinalRepository $repo, int $kegiatan, string $sumber): StreamedResponse
    {
        $arsip = $repo->untukKegiatan($kegiatan)[$sumber] ?? abort(404);
        abort_unless(Storage::disk('local')->exists($arsip['path']), 404);

        return Storage::disk('local')->response($arsip['path'], $arsip['nama_berkas'], ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, max-age=86400']);
    }
}
