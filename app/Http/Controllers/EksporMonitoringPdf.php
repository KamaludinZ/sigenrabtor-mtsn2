<?php

namespace App\Http\Controllers;

use App\Contracts\PengubahPdf;
use App\Support\Dokumen\GagalUbahPdf;
use App\Support\Monitoring\EksporExcel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Unduh Monitoring Anggaran sebagai PDF (khusus admin): buku yang sama dengan ekspor Excel (pengaturan cetak A4 lanskap)
 * diubah lewat PengubahPdf, sehingga angka PDF selalu sama dengan Excel & layar. Gagal → kembali ke halaman dengan
 * notifikasi galat (saringan tetap).
 */
class EksporMonitoringPdf extends Controller
{
    public function __invoke(Request $request, PengubahPdf $pengubah): Response|RedirectResponse
    {
        [$buku, $saringan] = EksporExcel::dariPermintaan($request->query());
        $sementara = storage_path('framework/cache/monitoring-'.Str::random(12).'.xlsx');

        try {
            (new Xlsx($buku))->save($sementara);
            $pdf = $pengubah->ubah($sementara);
        } catch (GagalUbahPdf $e) {
            return redirect()->route('monitoring.index', $request->query())
                ->with('notify', ['message' => $e->getMessage(), 'type' => 'error']);
        } finally {
            @unlink($sementara);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.EksporExcel::namaBerkas($saringan, 'pdf').'"',
        ]);
    }
}
