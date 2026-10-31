<?php

namespace App\Http\Controllers;

use App\Contracts\TemplateDokumenRepository;
use App\Support\Dokumen\ValidasiVariabelTemplate;
use App\Support\Pengaturan\UnggahMasterTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * Endpoint periksa penanda template master tanpa menyimpan (rute admin `pengaturan.template.master.periksa`,
 * `POST /pengaturan/template/master/periksa`, multipart `berkas` .docx + `jenis`): ValidasiVariabelTemplate — 200
 * {bisa_disimpan, variabel (nama, jumlah, terdaftar, saran, keterangan, kelompok, jenis), galat, peringatan}; berkas/jenis
 * tidak sah 422 {pesan, galat}. Kontrak: docs/api/template-master.md.
 */
class PeriksaTemplateMaster extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $aturan = UnggahMasterTemplate::aturan();
        $validator = Validator::make($request->all(), ['berkas' => $aturan['berkas'], 'jenis' => $aturan['jenis']], UnggahMasterTemplate::pesan());
        if ($validator->fails()) {
            return response()->json(['pesan' => $validator->errors()->first(), 'galat' => array_map(fn (array $p) => $p[0], $validator->errors()->toArray())], 422);
        }

        try {
            $hasil = ValidasiVariabelTemplate::periksa($request->file('berkas')->getRealPath(), (string) $request->input('jenis'));
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage(), 'galat' => ['berkas' => $e->getMessage()]], 422);
        }

        return response()->json([
            'jenis' => (string) $request->input('jenis'),
            'label_jenis' => TemplateDokumenRepository::JENIS[$request->input('jenis')],
            'bisa_disimpan' => $hasil['bisa_disimpan'],
            'variabel' => $hasil['analisis']->variabel,
            'galat' => $hasil['galat'],
            'peringatan' => $hasil['peringatan'],
        ], headers: ['Cache-Control' => 'no-store, private']);
    }
}
