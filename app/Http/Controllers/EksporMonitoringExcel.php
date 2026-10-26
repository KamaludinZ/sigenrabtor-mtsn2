<?php

namespace App\Http\Controllers;

use App\Support\Monitoring\EksporExcel;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduh Monitoring Anggaran sebagai .xlsx (khusus admin): parameter URL sama dengan halaman /monitoring
 * (EksporExcel::dariPermintaan()).
 */
class EksporMonitoringExcel extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        [$buku, $saringan] = EksporExcel::dariPermintaan($request->query());

        return response()->streamDownload(
            fn () => (new Xlsx($buku))->save('php://output'),
            EksporExcel::namaBerkas($saringan),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
