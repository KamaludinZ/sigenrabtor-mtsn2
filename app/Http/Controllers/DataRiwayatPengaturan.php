<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan\CatatanAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint baca catatan audit pengaturan (JSON, CatatanAudit::halaman; rute admin): saringan CatatanAudit::SARINGAN di
 * query string, halaman `hal`, 50 entri per halaman; tidak di-cache.
 */
class DataRiwayatPengaturan extends Controller
{
    public const PER_HALAMAN = 50;

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(
            CatatanAudit::halaman($request->only(CatatanAudit::SARINGAN), max(1, (int) $request->query('hal', 1)), self::PER_HALAMAN),
            headers: ['Cache-Control' => 'no-store, private'],
        );
    }
}
