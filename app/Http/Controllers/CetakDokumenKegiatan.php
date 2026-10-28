<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\CetakDokumen;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Stub\StubData;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman cetak dokumen satu kegiatan (`GET /kegiatan/{kegiatan}/cetak`, rute `kegiatan.cetak`; hak baca AksesKegiatan):
 * lembar `?dokumen=` (CetakDokumen::pilihan, kosong = cover + KAK/TOR + RAB) sumber dana `?sumber=` ('' = sumber pertama,
 * sumber yang tidak dipilih → 404) tanpa bingkai aplikasi; `?otomatis=1` membuka dialog cetak saat dimuat.
 */
class CetakDokumenKegiatan extends Controller
{
    public function __invoke(Request $request, KegiatanRepository $repo, int $kegiatan): View
    {
        $data = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403, AksesKegiatan::PESAN_TOLAK);

        $sumber = filled($request->query('sumber')) ? (string) $request->query('sumber') : null;
        abort_if($sumber !== null && ! in_array($sumber, array_column($data['sumber_dana'] ?? [], 'sumber'), true), 404, 'Sumber dana tidak dipilih untuk kegiatan ini.');

        $lembar = CetakDokumen::pilihan($request->query('dokumen'));

        return view('dokumen.cetak', [
            'kegiatanId' => $kegiatan,
            'dataKegiatan' => $data,
            'otomatis' => $request->boolean('otomatis'),
        ] + CetakDokumen::untuk($data, $sumber, $lembar));
    }
}
