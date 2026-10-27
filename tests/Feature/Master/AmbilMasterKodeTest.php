<?php

namespace Tests\Feature\Master;

use App\Contracts\MasterKodeRepository;
use App\Repositories\EloquentMasterKodeRepository;
use App\Support\MasterKode;
use App\Support\Stub\StubMasterKodeRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MasterKodeDb;
use Tests\TestCase;

class AmbilMasterKodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_repositori_database_sama_dengan_tiruan(): void
    {
        $tiruan = new StubMasterKodeRepository;
        foreach ($tiruan->tahunTersedia() as $tahun) {
            MasterKodeDb::muat($tahun);
        }
        $repo = new EloquentMasterKodeRepository;

        $this->assertSame($tiruan->tahunTersedia(), $repo->tahunTersedia());
        foreach ([...$tiruan->tahunTersedia(), 2030] as $tahun) {
            foreach (['kodeSk', 'kodeIksk', 'kodeRktm', 'kodeEdm', 'komponenEdm', 'budayaMutu'] as $metode) {
                $this->assertSame($tiruan->{$metode}($tahun), $repo->{$metode}($tahun), "{$metode} {$tahun}");
            }
        }

        // Dibaca sekali per permintaan: pemanggilan berikutnya tanpa kueri.
        DB::enableQueryLog();
        $repo->kodeRktm(2026);
        $repo->budayaMutu(2026);
        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_endpoint_master_per_jenis_tahun_dan_pencarian(): void
    {
        $this->getJson(route('master.kode', ['jenis' => 'sk']))
            ->assertOk()
            ->assertJsonPath('tahun', 2026)
            ->assertJsonPath('tersedia', true)
            ->assertJsonPath('jumlah', count(MasterKode::kodeSk()))
            ->assertJsonPath('baris.0', MasterKode::kodeSk()[0]);

        $iksk = $this->getJson(route('master.kode', ['jenis' => 'iksk', 'sk' => 'SK.11']))->assertOk()->json('baris');
        $this->assertSame(['SK.11'], array_values(array_unique(array_column($iksk, 'kode_sk'))));

        $cari = $this->getJson(route('master.kode', ['jenis' => 'rktm', 'cari' => 'SILABUS']))->assertOk()->json('baris');
        $this->assertNotEmpty($cari);
        foreach ($cari as $b) {
            $this->assertStringContainsStringIgnoringCase('silabus', implode(' ', $b));
        }

        $this->getJson(route('master.kode', ['jenis' => 'edm']))->assertJsonPath('baris.0.nama_komponen', MasterKode::komponenEdm()['A']);
        $this->getJson(route('master.kode', ['jenis' => 'budaya', 'ta' => 2025]))->assertJsonPath('tahun', 2025)->assertJsonPath('baris', MasterKode::budayaMutu(2025));
        $this->getJson(route('master.kode', ['jenis' => 'sk', 'ta' => 2030]))->assertJsonPath('tersedia', false)->assertJsonPath('jumlah', 0);
        $this->getJson(route('master.kode', ['jenis' => 'sk', 'ta' => 'abc']))->assertJsonPath('tahun', 2026);
        $this->get('/master-kode/leading')->assertNotFound();
    }

    public function test_endpoint_dari_database_sama_dengan_tiruan(): void
    {
        $tiruan = array_map(fn (string $jenis) => $this->getJson(route('master.kode', ['jenis' => $jenis]))->json(), ['sk', 'iksk', 'rktm', 'edm', 'budaya']);

        MasterKodeDb::muat(2026);
        $this->app->singleton(MasterKodeRepository::class, EloquentMasterKodeRepository::class);

        $this->assertSame($tiruan, array_map(fn (string $jenis) => $this->getJson(route('master.kode', ['jenis' => $jenis]))->json(), ['sk', 'iksk', 'rktm', 'edm', 'budaya']));
    }
}
