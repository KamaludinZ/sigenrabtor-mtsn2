<?php

namespace App\Http\Controllers;

use App\Support\Monitoring\RingkasanDashboard;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;

/** Endpoint baca ringkasan Dashboard utama (JSON, RingkasanDashboard::untuk; cakupan sesuai akun), tidak di-cache. */
class DataDashboard extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(RingkasanDashboard::untuk(StubData::currentUser()), headers: ['Cache-Control' => 'no-store, private']);
    }
}
