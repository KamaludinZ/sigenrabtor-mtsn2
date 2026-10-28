<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\AlurStatus;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint perpindahan status (`POST /kegiatan/{kegiatan}/status` {aksi, catatan?, versi?}, rute `kegiatan.status.ubah`):
 * AlurStatus::ubah — ajukan, tarik, setujui, kembalikan, finalkan, buka kunci. 200 {id, status, label_status, entri,
 * dokumen (hasil disusun ulang/draf diperbarui), versi, pesan}; aksi tak tersedia/syarat belum terpenuhi/catatan 422
 * {pesan, galat} (galat `keputusan` dari pemeriksaan admin dilaporkan sebagai `aksi`), bukan cakupan/peran/tahun
 * terkunci 403, versi usang 409 {pesan, versi}, kegiatan tidak ada 404. Tanpa cache.
 */
class UbahStatusKegiatan extends Controller
{
    public function __invoke(Request $request, KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403, AksesKegiatan::PESAN_TOLAK);
        $tanpaCache = ['Cache-Control' => 'no-store, private'];

        try {
            $hasil = AlurStatus::ubah(
                $kegiatan,
                (string) $request->input('aksi', ''),
                is_string($request->input('catatan')) ? $request->input('catatan') : null,
                StubData::currentUser(),
                $request->filled('versi') ? (string) $request->input('versi') : null,
            );
        } catch (ValidationException $e) {
            $galat = collect($e->errors())->mapWithKeys(fn (array $pesan, string $kunci) => [$kunci === 'keputusan' ? 'aksi' : $kunci => $pesan])->all();

            return response()->json(['pesan' => collect($galat)->flatten()->first(), 'galat' => $galat], 422, $tanpaCache);
        } catch (PengaturanBerubah $e) {
            return response()->json(['pesan' => $e->getMessage(), 'versi' => AlurStatus::versi($repo->temukan($kegiatan) ?? $data)], 409, $tanpaCache);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403, $tanpaCache);
        }

        return response()->json([
            'id' => $kegiatan,
            'status' => $hasil['kegiatan']['status'],
            'label_status' => StatusKegiatan::from($hasil['kegiatan']['status'])->label(),
            'entri' => $hasil['entri'],
            'dokumen' => $hasil['dokumen'],
            'versi' => $hasil['versi'],
            'pesan' => $hasil['pesan'],
        ], headers: $tanpaCache);
    }
}
