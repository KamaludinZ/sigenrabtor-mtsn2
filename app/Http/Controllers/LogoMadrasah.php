<?php

namespace App\Http\Controllers;

use App\Contracts\ProfilMadrasahRepository;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Logo kop madrasah (disk privat) untuk kop dokumen & pratinjau; URL memuat `v` waktu ubah agar tembolok diperbarui. */
class LogoMadrasah extends Controller
{
    public function __invoke(ProfilMadrasahRepository $repo): StreamedResponse
    {
        $path = $repo->ambil()['logo_path'] ?? abort(404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=86400']);
    }
}
