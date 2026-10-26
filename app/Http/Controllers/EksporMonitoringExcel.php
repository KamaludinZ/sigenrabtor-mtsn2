<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Monitoring\EksporExcel;
use App\Support\Monitoring\RingkasanAnggaran;
use App\Support\Monitoring\SaringanMonitoring;
use App\Support\Stub\StubPengaturan;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduh Monitoring Anggaran sebagai .xlsx (khusus admin): parameter URL sama dengan halaman /monitoring (saringan lewat
 * SaringanMonitoring, urutan rincian `urut`/`arah`, urutan per leading `urut_leading`/`arah_leading`).
 */
class EksporMonitoringExcel extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $saringan = SaringanMonitoring::dari($request->query());
        $ringkasan = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), $saringan->untukRingkasan());
        $teks = fn (string $kunci, string $bawaan = '') => is_string($request->query($kunci)) ? $request->query($kunci) : $bawaan;

        $buku = EksporExcel::buat($ringkasan, $saringan, StubPengaturan::pengguna(), $teks('urut', 'total'), $teks('arah') ?: null, $teks('urut_leading', 'kode'), $teks('arah_leading') ?: null);

        return response()->streamDownload(
            fn () => (new Xlsx($buku))->save('php://output'),
            EksporExcel::namaBerkas($saringan),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
