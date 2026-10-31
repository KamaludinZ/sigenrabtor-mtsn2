<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\PilihanTemplateKegiatan;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\UbahDataKegiatan;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint pilihan template & format satu kegiatan (rute `kegiatan.template.pilihan` GET & `kegiatan.template.pilih` PUT,
 * `/kegiatan/{kegiatan}/template/pilihan`; hak AksesKegiatan): GET = PilihanTemplateKegiatan::data; PUT {template?,
 * format?, versi?} = PilihanTemplateKegiatan::simpan → 200 {pesan, diubah, …data}; 422 {pesan, galat template|format};
 * 403 bukan cakupan/terkunci; 409 {pesan, versi} versi usang; 404. Tidak di-cache. Kontrak: docs/api/template-master.md.
 */
class PilihanTemplateKegiatanApi extends Controller
{
    public function __invoke(Request $request, KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $tanpaCache = ['Cache-Control' => 'no-store, private'];
        $data = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403, AksesKegiatan::PESAN_TOLAK);
        if ($request->isMethod('GET')) {
            return response()->json(PilihanTemplateKegiatan::data($data), headers: $tanpaCache);
        }

        try {
            $hasil = PilihanTemplateKegiatan::simpan($kegiatan, $request->only(['template', 'format']), StubData::currentUser(), $request->filled('versi') ? (string) $request->input('versi') : null);
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => array_map(fn (array $p) => $p[0], $e->errors())], 422, $tanpaCache);
        } catch (PengaturanBerubah $e) {
            return response()->json(['pesan' => $e->getMessage(), 'versi' => UbahDataKegiatan::versi($repo->temukan($kegiatan))], 409, $tanpaCache);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403, $tanpaCache);
        }

        return response()->json(['pesan' => $hasil['pesan'], 'diubah' => $hasil['diubah']] + $hasil['data'], headers: $tanpaCache);
    }
}
