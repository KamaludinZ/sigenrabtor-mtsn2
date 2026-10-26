<?php

namespace App\Http\Controllers;

use App\Contracts\TemplateCoverRepository;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Gambar desain cover tersimpan (disk privat) untuk daftar & pratinjau Template Dokumen. */
class GambarCover extends Controller
{
    public function __invoke(TemplateCoverRepository $repo, string $cover): StreamedResponse
    {
        $data = $repo->temukan($cover) ?? abort(404);
        abort_unless(Storage::disk('local')->exists($data['gambar_path']), 404);

        return Storage::disk('local')->response($data['gambar_path'], null, ['Cache-Control' => 'private, max-age=3600']);
    }
}
