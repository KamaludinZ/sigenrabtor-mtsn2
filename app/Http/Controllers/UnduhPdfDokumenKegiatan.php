<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Contracts\PembuatPdfDokumen;
use App\Http\Controllers\Concerns\PermintaanUnduhan;
use App\Support\Dokumen\CatatRiwayatDokumen;
use App\Support\Dokumen\CetakDokumen;
use App\Support\Dokumen\GagalUbahPdf;
use App\Support\Dokumen\SusunanKak;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Unduh PDF set dokumen langsung (`GET /kegiatan/{kegiatan}/cetak/pdf?sumber=&dokumen=`, rute `kegiatan.cetak.pdf`):
 * PembuatPdfDokumen untuk lembar CetakDokumen::pilihan, nama berkas baku CetakDokumen::namaBerkas (header
 * `Content-Disposition` + `X-Nama-Berkas`; dicatat ke Riwayat Dokumen lewat CatatRiwayatDokumen::dariLembar, header
 * X-Riwayat-Dokumen). Hak baca AksesKegiatan; sumber tidak dipilih → 404. Gagal disusun → 503 JSON
 * {pesan, tautan_cetak} untuk permintaan klien (Alpine `unduhPdf`), selain itu kembali ke Cetak & Unduh dengan notifikasi.
 */
class UnduhPdfDokumenKegiatan extends Controller
{
    use PermintaanUnduhan;

    public function __invoke(Request $request, KegiatanRepository $repo, int $kegiatan): Response|JsonResponse|RedirectResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403, AksesKegiatan::PESAN_TOLAK);
        $sumber = filled($request->query('sumber')) ? (string) $request->query('sumber') : null;
        abort_if($sumber !== null && ! in_array($sumber, array_column($data['sumber_dana'] ?? [], 'sumber'), true), 404, 'Sumber dana tidak dipilih untuk kegiatan ini.');

        $lembar = CetakDokumen::pilihan($request->query('dokumen'));
        $sumber = SusunanKak::dari($data, $sumber)->sumber;
        $nama = CetakDokumen::untuk($data, $sumber, $lembar)['nama_berkas'];
        try {
            $isi = app(PembuatPdfDokumen::class)->buat($data, $sumber, $lembar);
        } catch (GagalUbahPdf $e) {
            $tautan = CetakDokumen::tautan($kegiatan, $sumber, $lembar, otomatis: true);

            return $this->inginJson($request)
                ? response()->json(['pesan' => $e->getMessage(), 'tautan_cetak' => $tautan], 503, ['Cache-Control' => 'no-store, private'])
                : redirect()->route('cetak.index', ['pilih' => $kegiatan.'|'.($sumber ?? '')])->with('notify', ['message' => $e->getMessage(), 'type' => 'error']);
        }

        $catatan = CatatRiwayatDokumen::dariLembar($data, $sumber, $lembar, 'pdf', strlen($isi), StubData::currentUser());

        return response($isi, 200, array_filter(['X-Riwayat-Dokumen' => $catatan['id'] ?? null]) + [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nama.'"',
            'Content-Length' => (string) strlen($isi),
            'X-Nama-Berkas' => $nama,
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
