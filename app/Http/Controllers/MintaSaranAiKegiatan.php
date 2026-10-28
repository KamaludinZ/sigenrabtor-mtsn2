<?php

namespace App\Http\Controllers;

use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\GagalPenyediaAi;
use App\Support\Kegiatan\GagalUsulanAi;
use App\Support\Kegiatan\SaranKalimatAi;
use App\Support\Kegiatan\StatusKuotaAi;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint permintaan saran kalimat AI (`POST /kegiatan/{kegiatan}/saran-ai/{bagian}` {instruksi?, acuan?}, rute
 * `kegiatan.saran_ai.minta`): SaranKalimatAi::minta pada data kegiatan tersimpan → 201 {bagian, permintaan, versi,
 * peringatan (Batas Aman per versi), bertentangan (pertentangan dengan data master per versi, BatasDataMaster),
 * catatan_penyedia, penyedia, kuota (StatusKuotaAi: batas, terpakai, sisa)}. Isian salah 422 {pesan, galat}; bagian
 * nonaktif/nama kosong/batas habis/tanpa saran 422 {pesan}; penyedia gagal 503 {pesan, jenis}; bukan cakupan akun atau
 * terkunci 403; kegiatan tidak ada 404.
 */
class MintaSaranAiKegiatan extends Controller
{
    public function __invoke(Request $request, KegiatanRepository $repo, int $kegiatan, string $bagian): JsonResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        $pengguna = StubData::currentUser();
        abort_unless(AksesKegiatan::bolehLihat($data, $pengguna), 403, AksesKegiatan::PESAN_TOLAK);

        try {
            $hasil = SaranKalimatAi::minta($data, $bagian, $pengguna, (string) $request->input('instruksi', ''), $request->filled('acuan') ? (string) $request->input('acuan') : null);
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => $e->errors()], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        } catch (GagalUsulanAi $e) {
            $penyedia = $e->getPrevious() instanceof GagalPenyediaAi ? $e->getPrevious() : null;

            return response()->json(['pesan' => $e->getMessage()] + ($penyedia ? ['jenis' => $penyedia->jenis] : []), $penyedia ? 503 : 422);
        }

        $kuota = StatusKuotaAi::untuk($pengguna);

        return response()->json([
            'bagian' => $bagian,
            'permintaan' => $hasil['permintaan'],
            'versi' => $hasil['versi'],
            'peringatan' => $hasil['peringatan'],
            'bertentangan' => $hasil['bertentangan'],
            'catatan_penyedia' => $hasil['catatan'],
            'penyedia' => $hasil['penyedia'],
            'kuota' => array_intersect_key($kuota, array_flip(['batas', 'terpakai', 'sisa'])),
        ], 201, ['Cache-Control' => 'no-store, private']);
    }
}
