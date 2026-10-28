<?php

namespace App\Http\Controllers;

use App\Support\Monitoring\RingkasanPaguSumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Endpoint baca ringkasan pagu DIPA vs Komite (JSON, RingkasanPaguSumber; khusus admin, saringan URL sama dengan /monitoring). */
class DataPaguMonitoring extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(RingkasanPaguSumber::untuk($request->query()), headers: ['Cache-Control' => 'no-store, private']);
    }
}
