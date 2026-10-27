<?php

namespace Tests\Feature\Master;

use App\Support\Stub\StubMasterKodeRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\MasterKodeDb;
use Tests\TestCase;

class SkemaMasterKodeTest extends TestCase
{
    use RefreshDatabase;

    private function ditolak(callable $aksi): void
    {
        try {
            DB::transaction(function () use ($aksi) {
                // FK IKSK → SK DEFERRABLE diperiksa saat commit; transaksi tes tidak pernah commit.
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
                $aksi();
            });
        } catch (QueryException) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail('Seharusnya ditolak database.');
    }

    private function muat(int $tahun): void
    {
        MasterKodeDb::muat($tahun);
    }

    public function test_seluruh_master_tiruan_muat_di_tabel(): void
    {
        $repo = new StubMasterKodeRepository;
        foreach ($repo->tahunTersedia() as $tahun) {
            $this->muat($tahun);
        }
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');

        foreach ($repo->tahunTersedia() as $tahun) {
            $this->assertSame(
                [count($repo->kodeSk($tahun)), count($repo->kodeIksk($tahun)), count($repo->kodeRktm($tahun)), count($repo->kodeEdm($tahun)), count($repo->komponenEdm($tahun)), count($repo->budayaMutu($tahun))],
                array_map(fn (string $t) => DB::table($t)->where('tahun_anggaran', $tahun)->count(), ['kode_sk', 'kode_iksk', 'kode_rktm', 'kode_edm', 'komponen_edm', 'budaya_mutu']),
                "TA {$tahun}",
            );
        }
        $this->assertTrue(Schema::hasColumns('kode_edm', ['tahun_anggaran', 'urutan', 'kode', 'komponen', 'uraian']));
    }

    public function test_kode_unik_per_tahun_dan_iksk_milik_sk_tahun_yang_sama(): void
    {
        $this->muat(2026);

        // Kode sama boleh di tahun lain, tidak boleh dobel di tahun yang sama.
        DB::table('kode_sk')->insert(['tahun_anggaran' => 2027, 'urutan' => 1, 'kode' => 'SK.10', 'uraian' => 'Sasaran 2027']);
        $this->ditolak(fn () => DB::table('kode_sk')->insert(['tahun_anggaran' => 2026, 'urutan' => 99, 'kode' => 'SK.10', 'uraian' => 'Dobel']));
        $this->ditolak(fn () => DB::table('kode_rktm')->insert(['tahun_anggaran' => 2026, 'urutan' => 99, 'kode' => '6.17.12', 'snp' => 'x', 'kegiatan' => 'x', 'sub_kegiatan' => 'x']));
        $this->ditolak(fn () => DB::table('budaya_mutu')->insert(['tahun_anggaran' => 2026, 'nomor' => 9, 'nama' => 'Integritas']));
        $this->ditolak(fn () => DB::table('budaya_mutu')->insert(['tahun_anggaran' => 2026, 'nomor' => 0, 'nama' => 'Nol']));

        // IKSK hanya untuk SK yang ada di master tahun itu; SK yang masih punya IKSK tidak bisa dihapus.
        $this->ditolak(fn () => DB::table('kode_iksk')->insert(['tahun_anggaran' => 2027, 'urutan' => 1, 'kode' => 'IKSK 11.1', 'kode_sk' => 'SK.11', 'uraian' => 'x']));
        $this->ditolak(fn () => DB::table('kode_sk')->where('tahun_anggaran', 2026)->where('kode', 'SK.10')->delete());
    }

    public function test_master_satu_tahun_bisa_diganti_dalam_satu_transaksi(): void
    {
        $this->muat(2026);

        DB::transaction(function () {
            DB::statement('SET CONSTRAINTS ALL DEFERRED');
            // Urutan bebas: SK dihapus lebih dulu, IKSK menyusul — sah selama konsisten saat transaksi selesai.
            DB::table('kode_sk')->where('tahun_anggaran', 2026)->delete();
            DB::table('kode_iksk')->where('tahun_anggaran', 2026)->delete();
            DB::table('kode_iksk')->insert(['tahun_anggaran' => 2026, 'urutan' => 1, 'kode' => 'IKSK 10.1', 'kode_sk' => 'SK.10', 'uraian' => 'Indikator baru']);
            DB::table('kode_sk')->insert(['tahun_anggaran' => 2026, 'urutan' => 1, 'kode' => 'SK.10', 'uraian' => 'Sasaran baru']);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });

        $this->assertSame([1, 1], [DB::table('kode_sk')->where('tahun_anggaran', 2026)->count(), DB::table('kode_iksk')->where('tahun_anggaran', 2026)->count()]);
    }
}
