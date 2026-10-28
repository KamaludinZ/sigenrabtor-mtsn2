<?php

namespace App\Http\Controllers;

use App\Support\Monitoring\DataMonitoring;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint baca data Monitoring Anggaran terfilter & terpaginasi (JSON, DataMonitoring; khusus admin, parameter URL
 * sama dengan /monitoring + `per`), tidak di-cache.
 */
class DataMonitoringAnggaran extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(DataMonitoring::untuk($request->query()), headers: ['Cache-Control' => 'no-store, private']);
    }
}
