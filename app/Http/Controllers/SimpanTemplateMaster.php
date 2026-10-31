<?php

namespace App\Http\Controllers;

use App\Support\Dokumen\DaftarTemplateMaster;
use App\Support\Pengaturan\UnggahMasterTemplate;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint unggah template master (rute admin `pengaturan.template.master.simpan`, `POST /pengaturan/template/master`,
 * multipart: `berkas` .docx, `nama`, `jenis`, `leading` (kosong = umum), `catatan`, `default`): UnggahMasterTemplate::simpan
 * tahun aktif — penanda harus ada & terdaftar. 201 {pesan, template}; 422 {pesan, galat}; 403 tahun terkunci.
 */
class SimpanTemplateMaster extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $berkas = $request->file('berkas');
        try {
            $master = UnggahMasterTemplate::simpan((int) StubData::tahunAktif()['tahun'], $berkas instanceof UploadedFile ? $berkas : null, (string) $request->input('nama', ''), (string) $request->input('jenis', ''), StubData::currentUser(), [
                'leading' => $request->input('leading'), 'catatan' => $request->input('catatan'), 'default' => $request->boolean('default'),
            ]);
        } catch (ValidationException $e) {
            return response()->json(['pesan' => collect($e->errors())->flatten()->first(), 'galat' => array_map(fn (array $p) => $p[0], $e->errors())], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        }

        return response()->json(['pesan' => "Template “{$master['nama']}” tersimpan.", 'template' => DaftarTemplateMaster::baris($master['id'])], 201, ['Cache-Control' => 'no-store, private']);
    }
}
