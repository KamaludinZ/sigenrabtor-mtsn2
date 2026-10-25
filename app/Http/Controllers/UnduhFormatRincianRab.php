<?php

namespace App\Http\Controllers;

use App\Support\Rab\Impor\TemplateImporRab;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduh format tabel rincian RAB (.xlsx) untuk impor rincian per sumber dana.
 */
class UnduhFormatRincianRab extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $buku = TemplateImporRab::buat();

        return response()->streamDownload(
            fn () => (new Xlsx($buku))->save('php://output'),
            TemplateImporRab::namaBerkas(),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
