<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Rab\EksporRabExcel;
use App\Support\Stub\StubData;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduh RAB satu kegiatan sebagai .xlsx (EksporRabExcel; hak baca AksesKegiatan): `?sumber=` satu sumber dana yang
 * dipilih, tanpa sumber = semua sumber (satu lembar per sumber); kegiatan tanpa sumber dana / sumber tak dipilih → 404.
 */
class EksporRabKegiatan extends Controller
{
    public function __invoke(Request $request, KegiatanRepository $repo, int $kegiatan): StreamedResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404);
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403);

        $dipilih = array_column($data['sumber_dana'] ?? [], 'sumber');
        $sumber = filled($request->query('sumber')) ? (string) $request->query('sumber') : null;
        abort_if($dipilih === [] || ($sumber !== null && ! in_array($sumber, $dipilih, true)), 404);

        ['buku' => $buku, 'nama' => $nama] = EksporRabExcel::buat($data, $sumber);

        return response()->streamDownload(
            fn () => (new Xlsx($buku))->save('php://output'),
            $nama,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'no-store, private'],
        );
    }
}
