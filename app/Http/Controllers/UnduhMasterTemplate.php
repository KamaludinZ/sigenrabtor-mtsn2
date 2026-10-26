<?php

namespace App\Http\Controllers;

use App\Contracts\TemplateDokumenRepository;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Unduh kembali master .docx yang tersimpan (untuk diperbaiki di Word lalu diunggah ulang). */
class UnduhMasterTemplate extends Controller
{
    public function __invoke(TemplateDokumenRepository $repo, string $template): StreamedResponse
    {
        $data = $repo->temukan($template) ?? abort(404);
        abort_unless(Storage::disk('local')->exists($data['file_path']), 404);

        return Storage::disk('local')->download($data['file_path'], $data['nama_berkas']);
    }
}
