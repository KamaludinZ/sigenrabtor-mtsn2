<?php

namespace App\Http\Controllers;

use App\Support\Kegiatan\Impor\TemplateImporKegiatan;
use App\Support\Stub\StubData;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduh template Excel impor kegiatan untuk tahun anggaran aktif (pilihan Leading dari master tahun itu).
 */
class UnduhTemplateImporKegiatan extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $buku = TemplateImporKegiatan::buat($tahun);

        return response()->streamDownload(
            fn () => (new Xlsx($buku))->save('php://output'),
            TemplateImporKegiatan::namaBerkas($tahun),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
