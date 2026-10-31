<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan\TemplateMasihDipakai;
use App\Support\Pengaturan\UnggahMasterTemplate;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint hapus template master (rute admin `pengaturan.template.master.hapus`, `DELETE /pengaturan/template/master/{template}`):
 * UnggahMasterTemplate::hapus — template yang masih dipilih kegiatan ditolak 409 {pesan, kegiatan} (nonaktifkan saja);
 * 200 {pesan}; 403 tahun terkunci; 404.
 */
class HapusTemplateMaster extends Controller
{
    public function __invoke(string $template): JsonResponse
    {
        try {
            $master = UnggahMasterTemplate::hapus($template, StubData::currentUser());
        } catch (TemplateMasihDipakai $e) {
            return response()->json(['pesan' => $e->getMessage(), 'kegiatan' => $e->kegiatan], 409);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        }

        return $master === null
            ? response()->json(['pesan' => 'Template master tidak ditemukan.'], 404)
            : response()->json(['pesan' => "Template “{$master['nama']}” dihapus."]);
    }
}
