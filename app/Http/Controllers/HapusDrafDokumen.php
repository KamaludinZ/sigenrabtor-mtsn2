<?php

namespace App\Http\Controllers;

use App\Contracts\DrafDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\DrafDokumen;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Endpoint hapus draf dokumen (`DELETE /kegiatan/{kegiatan}/draf`, rute `kegiatan.draf.hapus`; isian `jenis`, `sumber`
 * opsional, dan `versi` opsional = versi draf dari bacaan terakhir): DrafDokumen::hapus. Jawaban {pesan}; draf tidak ada
 * 404, draf disimpan ulang/dihapus di tempat lain sejak `versi` 409 {pesan, draf (terbaru atau null), versi}, di luar
 * cakupan/terkunci 403, kegiatan tidak ada 404, penyimpanan gagal ditulis 503. Isi dokumen & data kegiatan tidak berubah.
 */
class HapusDrafDokumen extends Controller
{
    public function __invoke(Request $request, KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        $jenis = (string) $request->input('jenis', '');
        $sumber = $request->filled('sumber') ? (string) $request->input('sumber') : null;

        try {
            $ada = DrafDokumen::hapus($kegiatan, $jenis, $sumber, StubData::currentUser(), $request->has('versi') ? (string) $request->input('versi') : null);
        } catch (PengaturanBerubah $e) {
            $draf = app(DrafDokumenRepository::class)->temukan($kegiatan, $jenis, $sumber);

            return response()->json(['pesan' => $e->getMessage(), 'draf' => $draf ? DrafDokumen::untukJson($data, $draf) : null, 'versi' => DrafDokumen::versi($draf)], 409);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], 503);
        }

        $nama = DrafDokumen::nama($jenis, $sumber);

        return response()->json(['pesan' => $ada ? "Draf {$nama} dihapus." : "Draf {$nama} tidak ada."], $ada ? 200 : 404, ['Cache-Control' => 'no-store, private']);
    }
}
