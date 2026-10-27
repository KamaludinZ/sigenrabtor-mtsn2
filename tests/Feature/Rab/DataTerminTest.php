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

class DataTerminTest extends TestCase
{
    use RefreshDatabase;

    public function test_termin_per_sumber_dana_dengan_blok_dokumen(): void
    {
        $komite = $this->getJson(route('kegiatan.termin', ['kegiatan' => 1]))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json('sumber.0');

        $this->assertSame(['Komite', 10_000_000, 2, 10_000_000, 0, 'pas', 'Jumlah termin sama dengan total RAB'], [
            $komite['sumber'], $komite['total_rab'], $komite['jumlah'], $komite['total_termin'], $komite['selisih'], $komite['status'], $komite['keterangan'],
        ]);
        $this->assertSame([
            'nomor' => 1, 'tanggal' => '2026-06-17', 'tanggal_terbaca' => 'Rabu, 17 Juni 2026', 'nominal' => 7_680_000,
            'terbilang' => 'Tujuh Juta Enam Ratus Delapan Puluh Ribu Rupiah', 'dokumen' => 'Rp7.680.000,- (Tujuh Juta Enam Ratus Delapan Puluh Ribu Rupiah)',
            'persen' => 76.8, 'kumulatif' => 7_680_000,
        ], $komite['termin'][0]);
        $this->assertSame([10_000_000, [['bulan' => 6, 'nominal' => 10_000_000]]], [$komite['termin'][1]['kumulatif'], $komite['per_bulan']]);
        $this->assertSame(['label' => 'Termin 1 (17/06/2026)', 'nilai' => 'Rp7.680.000,00'], $komite['dokumen'][2]);
    }

    public function test_dibayar_sekaligus_dan_termin_tidak_pas(): void
    {
        $json = $this->getJson(route('kegiatan.termin', ['kegiatan' => 2]))->assertOk()->json();
        $this->assertSame([['DIPA', 'sekaligus', []], ['Komite', 'sekaligus', []]], array_map(fn ($s) => [$s['sumber'], $s['status'], $s['dokumen']], $json['sumber']));

        app(RabRepository::class)->simpanTermin(2, 'DIPA', [['tanggal' => '2026-09-01', 'nominal' => 5_000_000]]);
        $dipa = $this->getJson(route('kegiatan.termin', ['kegiatan' => 2]))->json('sumber.0');
        $this->assertSame(['kurang', 400_000, 'Termin kurang Rp400.000 dari total RAB'], [$dipa['status'], $dipa['selisih'], $dipa['keterangan']]);
    }

    public function test_hak_akses_dan_hasil_dari_database(): void
    {
        $this->getJson(route('kegiatan.termin', ['kegiatan' => 999]))->assertNotFound();
        session(['stub_role' => 'admin']);
        $lain = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Lomba Kebersihan Kelas', 'leading' => '1.2']);
        session(['stub_role' => 'leading']);
        $this->getJson(route('kegiatan.termin', ['kegiatan' => $lain]))->assertForbidden();

        $tiruan = $this->getJson(route('kegiatan.termin', ['kegiatan' => 1]))->json();
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(RabRepository::class, EloquentRabRepository::class);
        $id = app(KegiatanRepository::class)->simpan(Arr::except(StubKegiatanRepository::contoh(), ['id', 'status']));
        foreach (StubRab::rincian(1, 'Komite') as $item) {
            app(RabRepository::class)->tambahItem($id, 'Komite', $item);
        }
        app(RabRepository::class)->simpanTermin($id, 'Komite', StubRab::termin(1, 'Komite'));

        $this->assertSame(Arr::except($tiruan, 'kegiatan_id'), Arr::except($this->getJson(route('kegiatan.termin', ['kegiatan' => $id]))->assertOk()->json(), 'kegiatan_id'));
    }
}
