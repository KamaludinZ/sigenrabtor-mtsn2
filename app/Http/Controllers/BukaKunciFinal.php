<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\KunciDokumen;
use App\Support\Kegiatan\KunciFinal;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint buka kunci final (`DELETE /kegiatan/{kegiatan}/kunci`, rute `kegiatan.kunci.buka`; isian `alasan` wajib,
 * `versi` opsional): KunciFinal::buka — hanya admin, status kembali Dikembalikan dengan alasan tercatat di riwayat.
 * Jawaban sama dengan `kegiatan.kunci.pasang` (jejak null); alasan kosong/terlalu panjang 422 {pesan, galat.alasan},
 * bukan admin/tidak terkunci/tahun terkunci 403, berubah di tempat lain 409, kegiatan tidak ada 404.
 */
class BukaKunciFinal extends Controller
{
    public function __invoke(Request $request, KegiatanRepository $repo, int $kegiatan): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        abort_unless(AksesKegiatan::bolehLihat($data, StubData::currentUser()), 403, AksesKegiatan::PESAN_TOLAK);

        try {
            $hasil = KunciFinal::buka($kegiatan, $request->input('alasan'), StubData::currentUser(), $request->filled('versi') ? (string) $request->input('versi') : null);
        } catch (ValidationException $e) {
            $galat = collect($e->errors())->mapWithKeys(fn (array $pesan, string $kunci) => [$kunci === 'catatan' ? 'alasan' : $kunci => $pesan])->all();

            return response()->json(['pesan' => collect($galat)->flatten()->first(), 'galat' => $galat], 422);
        } catch (PengaturanBerubah $e) {
            return response()->json(['pesan' => $e->getMessage()], 409);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        }

        return response()->json([
            'id' => $kegiatan,
            'status' => $hasil['kegiatan']['status'],
            'label_status' => StatusKegiatan::from($hasil['kegiatan']['status'])->label(),
            'entri' => $hasil['entri'],
            'jejak' => ($jejak = KunciDokumen::dari($hasil['kegiatan'])) === null ? null : ['pada' => $jejak->pada, 'oleh' => $jejak->oleh, 'kalimat' => $jejak->kalimat()],
            'versi' => $hasil['versi'],
            'pesan' => $hasil['pesan'],
        ], headers: ['Cache-Control' => 'no-store, private']);
    }
}
