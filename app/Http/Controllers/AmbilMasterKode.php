<?php

namespace App\Http\Controllers;

use App\Support\Master\IkskPerSk;
use App\Support\Master\JenisKode;
use App\Support\MasterKode;
use App\Support\Stub\StubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint baca master kode satu jenis (sk, iksk, rktm, edm, budaya) per tahun anggaran (JSON) untuk semua pengguna —
 * data rujukan isian Kode & Klasifikasi. Parameter: `ta` (bawaan tahun aktif), `cari` (teks di kolom mana pun, tanpa
 * beda huruf besar/kecil), `sk` (khusus IKSK: hanya indikator SK itu). Bentuk baris sama dengan MasterKode; EDM membawa
 * nama komponennya.
 */
class AmbilMasterKode extends Controller
{
    public function __invoke(Request $request, string $jenis): JsonResponse
    {
        abort_unless(isset(JenisKode::DAFTAR[$jenis]), 404);

        $tahun = filter_var($request->query('ta'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]) ?: StubData::tahunAktif()['tahun'];
        $baris = $jenis === 'iksk' && filled($request->query('sk'))
            ? IkskPerSk::pilihan((string) $request->query('sk'), $tahun)
            : JenisKode::baris($jenis, $tahun);

        if ($jenis === 'edm') {
            $komponen = MasterKode::komponenEdm($tahun);
            $baris = array_map(fn (array $b) => $b + ['nama_komponen' => $komponen[$b['komponen']] ?? null], $baris);
        }

        $cari = mb_strtolower(trim((string) $request->query('cari', '')));
        if ($cari !== '') {
            $baris = array_values(array_filter($baris, fn (array $b) => str_contains(mb_strtolower(implode(' ', array_map('strval', $b))), $cari)));
        }

        return response()->json([
            'jenis' => $jenis,
            'label' => JenisKode::DAFTAR[$jenis]['label'],
            'tahun' => $tahun,
            'tersedia' => MasterKode::tersedia($tahun),
            'jumlah' => count($baris),
            'baris' => $baris,
        ]);
    }
}
