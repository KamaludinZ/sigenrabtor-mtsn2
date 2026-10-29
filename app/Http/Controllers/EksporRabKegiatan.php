<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Http\Controllers\Concerns\PermintaanUnduhan;
use App\Support\Dokumen\Generasi\BerkasDokumen;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Rab\EksporRabExcel;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Exception as GalatSpreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Unduh RAB satu kegiatan sebagai .xlsx (`GET /kegiatan/{kegiatan}/rab/excel?sumber=`, rute `kegiatan.rab.excel`;
 * EksporRabExcel — rumus Jumlah/TOTAL, kaki DRAF & nomor halaman; hak baca AksesKegiatan): `?sumber=` satu sumber dana yang
 * dipilih, tanpa sumber = semua sumber (satu lembar per sumber). Header unduhan baku BerkasDokumen::headerUnduhan
 * (X-Nama-Berkas, X-Format-Berkas, X-Draf, no-store). Galat: 404 kegiatan tidak ada / tanpa sumber dana / sumber tak
 * dipilih, 403, 503 gagal disusun — JSON {pesan} untuk permintaan klien (PermintaanUnduhan), selain itu halaman galat.
 * Dipakai tombol Excel sebagai cadangan otomatis bila penyusun peramban gagal.
 */
class EksporRabKegiatan extends Controller
{
    use PermintaanUnduhan;

    public function __invoke(Request $request, KegiatanRepository $repo, int $kegiatan): Response|JsonResponse
    {
        $data = $repo->temukan($kegiatan);
        if ($data === null) {
            return $this->gagal($request, 404, 'Kegiatan tidak ditemukan.');
        }
        if (! AksesKegiatan::bolehLihat($data, StubData::currentUser())) {
            return $this->gagal($request, 403, AksesKegiatan::PESAN_TOLAK);
        }

        $dipilih = array_column($data['sumber_dana'] ?? [], 'sumber');
        $sumber = filled($request->query('sumber')) ? (string) $request->query('sumber') : null;
        if ($dipilih === []) {
            return $this->gagal($request, 404, 'Kegiatan ini belum memilih sumber dana, jadi belum punya RAB.');
        }
        if ($sumber !== null && ! in_array($sumber, $dipilih, true)) {
            return $this->gagal($request, 404, "Sumber dana {$sumber} tidak dipilih untuk kegiatan ini.");
        }

        try {
            ['buku' => $buku, 'nama' => $nama, 'draf' => $draf] = EksporRabExcel::buat($data, $sumber);
            ob_start();
            try {
                (new Xlsx($buku))->save('php://output');
            } finally {
                $isi = (string) ob_get_clean();
            }
        } catch (GalatSpreadsheet) {
            return $this->gagal($request, 503, 'Berkas Excel RAB gagal disusun di server — coba lagi.');
        }

        return response($isi, 200, BerkasDokumen::headerUnduhan($nama, 'xlsx', $draf, strlen($isi)));
    }

    private function gagal(Request $request, int $status, string $pesan): Response|JsonResponse
    {
        if ($this->inginJson($request)) {
            return response()->json(['pesan' => $pesan], $status, ['Cache-Control' => 'no-store, private']);
        }
        abort($status, $pesan);
    }
}
