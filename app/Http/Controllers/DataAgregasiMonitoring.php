<?php

namespace App\Http\Controllers;

use App\Support\Monitoring\AgregasiBelanja;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Endpoint baca agregasi belanja per kelompok & per operator (JSON, AgregasiBelanja; khusus admin, saringan URL sama dengan /monitoring). */
class DataAgregasiMonitoring extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(AgregasiBelanja::untuk($request->query()), headers: ['Cache-Control' => 'no-store, private']);
    }
}
