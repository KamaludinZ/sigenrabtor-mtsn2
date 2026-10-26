<?php

namespace App\Http\Controllers;

use App\Support\Pagu\Impor\TemplateImporPagu;
use App\Support\Stub\StubData;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Unduh template Excel impor Data Pagu tahun anggaran aktif. */
class UnduhTemplateDataPagu extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $tahun = StubData::tahunAktif()['tahun'];

        return response()->streamDownload(
            fn () => (new Xlsx(TemplateImporPagu::buat($tahun)))->save('php://output'),
            TemplateImporPagu::namaBerkas($tahun),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
