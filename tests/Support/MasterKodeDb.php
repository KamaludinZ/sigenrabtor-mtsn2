<?php

namespace Tests\Support;

use App\Support\Stub\StubMasterKodeRepository;
use Illuminate\Support\Facades\DB;

/** Isi tabel master kode PostgreSQL satu tahun dari master tiruan (app/Support/Stub/data/master-{tahun}.php). */
final class MasterKodeDb
{
    public static function muat(int $tahun): void
    {
        $repo = new StubMasterKodeRepository;
        $urut = fn (array $baris) => array_map(fn (array $b, int $i) => ['tahun_anggaran' => $tahun, 'urutan' => $i + 1] + $b, $baris, array_keys($baris));

        DB::table('kode_sk')->insert($urut($repo->kodeSk($tahun)));
        DB::table('kode_iksk')->insert($urut($repo->kodeIksk($tahun)));
        DB::table('kode_rktm')->insert($urut($repo->kodeRktm($tahun)));
        DB::table('kode_edm')->insert($urut($repo->kodeEdm($tahun)));
        DB::table('komponen_edm')->insert(array_map(fn (string $huruf, string $nama) => ['tahun_anggaran' => $tahun, 'huruf' => $huruf, 'nama' => $nama], array_keys($repo->komponenEdm($tahun)), $repo->komponenEdm($tahun)));
        DB::table('budaya_mutu')->insert(array_map(fn (array $b) => ['tahun_anggaran' => $tahun] + $b, $repo->budayaMutu($tahun)));
    }
}
