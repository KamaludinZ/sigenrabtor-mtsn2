<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentRabRepository;
use App\Support\Stub\StubKegiatanRepository;
use App\Support\Stub\StubRab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class TotalAnggaranTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_per_sumber_dana_beserta_terbilang_dan_pagu(): void
    {
        $respons = $this->getJson(route('kegiatan.total_anggaran', ['kegiatan' => 2]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('kegiatan_id', 2)
            ->assertJsonPath('sumber.0.sumber', 'DIPA')
            ->assertJsonPath('sumber.0.total', 5_400_000)
            ->assertJsonPath('sumber.0.terbilang', 'Lima Juta Empat Ratus Ribu Rupiah')
            ->assertJsonPath('sumber.0.dokumen', 'Rp5.400.000,- (Lima Juta Empat Ratus Ribu Rupiah)')
            ->assertJsonPath('sumber.0.jumlah_item', 6)
            ->assertJsonPath('sumber.0.pagu', 6_000_000)
            ->assertJsonPath('sumber.0.status_pagu', 'sisa')
            ->assertJsonPath('sumber.0.selisih_pagu', 600_000)
            ->assertJsonPath('sumber.0.keterangan_pagu', 'Sisa pagu Rp600.000')
            ->assertJsonPath('sumber.1.sumber', 'Komite');

        $this->assertSame($respons->json('sumber.0.total') + $respons->json('sumber.1.total'), $respons->json('total'));
        $this->assertSame(['jumlah', 'total', 'status', 'keterangan'], array_keys($respons->json('sumber.1.termin')));
    }

    public function test_hak_akses_dan_kegiatan_tidak_ada(): void
    {
        $this->getJson(route('kegiatan.total_anggaran', ['kegiatan' => 999]))->assertNotFound();

        session(['stub_role' => 'admin']);
        $lain = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Lomba Kebersihan Kelas', 'leading' => '1.2', 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 1_500_000]]]);
        $this->getJson(route('kegiatan.total_anggaran', ['kegiatan' => $lain]))->assertOk()->assertJsonPath('sumber.0.status_pagu', 'sisa')->assertJsonPath('total', 0)->assertJsonPath('terbilang', null);

        // Akun leading 1.6 tidak melihat kegiatan leading lain buatan admin.
        session(['stub_role' => 'leading']);
        $this->getJson(route('kegiatan.total_anggaran', ['kegiatan' => $lain]))->assertForbidden();
    }

    public function test_hasil_sama_dari_database(): void
    {
        $tiruan = $this->getJson(route('kegiatan.total_anggaran', ['kegiatan' => 2]))->json();

        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(RabRepository::class, EloquentRabRepository::class);
        $id = app(KegiatanRepository::class)->simpan(Arr::except(StubKegiatanRepository::contohDuaSumber(), ['id', 'status']));
        foreach (StubRab::DAFTAR_CONTOH[2] as $sumber) {
            foreach (StubRab::rincian(2, $sumber) as $item) {
                app(RabRepository::class)->tambahItem($id, $sumber, $item);
            }
            app(RabRepository::class)->simpanTermin($id, $sumber, StubRab::termin(2, $sumber));
        }

        $this->assertSame(Arr::except($tiruan, 'kegiatan_id'), Arr::except($this->getJson(route('kegiatan.total_anggaran', ['kegiatan' => $id]))->assertOk()->json(), 'kegiatan_id'));
    }
}
