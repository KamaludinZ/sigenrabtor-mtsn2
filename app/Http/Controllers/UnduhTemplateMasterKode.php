<?php

namespace App\Http\Controllers;

use App\Contracts\MasterKodeRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Support\Master\Impor\BerkasMaster;
use App\Support\Master\TemplateMasterKode;
use App\Support\Stub\StubData;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduh template Excel master kode (`GET /master/template?ta=&cakupan=&isi=1`, rute admin `master.template`):
 * TemplateMasterKode untuk tahun terdaftar (`ta`, bawaan tahun aktif; tak terdaftar → 404), cakupan `semua` atau satu
 * jenis (tak dikenal → 404), `isi=1` = berisi master tahun itu, `dari` = berisi master tahun lain sebagai titik awal
 * (tahun tanpa master → 404).
 */
class UnduhTemplateMasterKode extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $tahun = $request->filled('ta') ? (int) $request->query('ta') : StubData::tahunAktif()['tahun'];
        abort_unless(collect(app(TahunAnggaranRepository::class)->semua())->contains('tahun', $tahun), 404);
        $cakupan = (string) $request->query('cakupan', BerkasMaster::SEMUA);
        abort_unless(array_key_exists($cakupan, BerkasMaster::cakupan()), 404);
        $isi = $request->boolean('isi');
        $dari = $request->filled('dari') ? (int) $request->query('dari') : null;
        abort_unless($dari === null || in_array($dari, app(MasterKodeRepository::class)->tahunTersedia(), true), 404);

        return response()->streamDownload(
            fn () => (new Xlsx(TemplateMasterKode::buat($tahun, $cakupan, $isi, $dari)))->save('php://output'),
            TemplateMasterKode::namaBerkas($tahun, $cakupan, $isi, $dari),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'no-store'],
        );
    }
}
