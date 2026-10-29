<?php

namespace App\Http\Controllers;

use App\Contracts\GeneratorBerkasDokumen;
use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\Generasi\GagalBuatBerkas;
use App\Support\Dokumen\Generasi\PermintaanBerkas;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Unduh berkas dokumen yang dibuat server (`GET /kegiatan/{kegiatan}/berkas/unduh?format=&jenis=&sumber=`, rute
 * `kegiatan.berkas.unduh`): PermintaanBerkas::dari → GeneratorBerkasDokumen::buat → berkas biner dengan
 * BerkasDokumen::header(). Galat: 404 kegiatan, 403 AksesKegiatan, 422 {pesan, galat} isian, 501 {pesan, jenis
 * tidak_didukung, klien: true, tautan_cetak} format yang disusun peramban, 503 {pesan, jenis gagal, tautan_cetak}. Permintaan
 * bukan JSON yang gagal kembali ke Cetak & Unduh dengan notifikasi. Kontrak lengkap: docs/api/generasi-dokumen.md.
 */
class UnduhBerkasKegiatan extends Controller
{
    public function __invoke(Request $request, KegiatanRepository $repo, int $kegiatan): Response|JsonResponse|RedirectResponse
    {
        $data = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        $pengguna = StubData::currentUser();
        abort_unless(AksesKegiatan::bolehLihat($data, $pengguna), 403, AksesKegiatan::PESAN_TOLAK);
        $tanpaCache = ['Cache-Control' => 'no-store, private'];

        try {
            $berkas = app(GeneratorBerkasDokumen::class)->buat(PermintaanBerkas::dari($data, $request->only(['format', 'jenis', 'sumber']), $pengguna));
        } catch (ValidationException $e) {
            $pesan = collect($e->errors())->flatten()->first();

            return $request->expectsJson()
                ? response()->json(['pesan' => $pesan, 'galat' => array_map(fn (array $p) => $p[0], $e->errors())], 422, $tanpaCache)
                : redirect()->route('cetak.index')->with('notify', ['message' => $pesan, 'type' => 'error']);
        } catch (GagalBuatBerkas $e) {
            return $request->expectsJson()
                ? response()->json(array_filter(['pesan' => $e->getMessage(), 'jenis' => $e->jenis, 'klien' => $e->jenis === 'tidak_didukung' ? true : null, 'tautan_cetak' => $e->tautanCetak], fn ($v) => $v !== null), $e->status(), $tanpaCache)
                : redirect()->route('cetak.index')->with('notify', ['message' => $e->getMessage(), 'type' => 'error']);
        }

        return response($berkas->isi, 200, $berkas->header());
    }
}
