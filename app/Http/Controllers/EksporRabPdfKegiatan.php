<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\GagalUbahPdf;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Rab\EksporRabPdf;
use App\Support\Stub\StubData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Unduh RAB satu kegiatan sebagai PDF (EksporRabPdf; hak baca AksesKegiatan; `?sumber=` seperti ekspor Excel). Gagal
 * diubah → kembali ke halaman RAB dengan notifikasi galat.
 */
class EksporRabPdfKegiatan extends Controller
{
    public function __invoke(Request $request, KegiatanRepository $repo, int $kegiatan): Response|RedirectResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404);
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403);

        $dipilih = array_column($data['sumber_dana'] ?? [], 'sumber');
        $sumber = filled($request->query('sumber')) ? (string) $request->query('sumber') : null;
        abort_if($dipilih === [] || ($sumber !== null && ! in_array($sumber, $dipilih, true)), 404);

        try {
            ['isi' => $isi, 'nama' => $nama] = EksporRabPdf::buat($data, $sumber);
        } catch (GagalUbahPdf $e) {
            return redirect()->route('kegiatan.rab', array_filter(['kegiatan' => $kegiatan, 'sumber' => $sumber]))
                ->with('notify', ['message' => $e->getMessage(), 'type' => 'error']);
        }

        return response($isi, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nama.'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
