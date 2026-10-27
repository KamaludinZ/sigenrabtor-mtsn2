<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentRabRepository;
use App\Support\Rab\KalkulatorRab;
use App\Support\Stub\StubKegiatanRepository;
use App\Support\Stub\StubRab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class RabLengkapTest extends TestCase
{
    use RefreshDatabase;

    public function test_rincian_dan_rekap_per_sumber_dana(): void
    {
        $json = $this->getJson(route('kegiatan.rab_lengkap', ['kegiatan' => 2]))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();
        $dipa = $json['sumber'][0];
        $rincian = StubRab::rincian(2, 'DIPA');

        $this->assertSame(['DIPA', true, 2026], [$dipa['sumber'], $dipa['dipilih'], $json['tahun_anggaran']]);
        $this->assertSame(range(1, count($rincian)), array_column($dipa['rincian'], 'nomor'));
        $this->assertSame(array_column($rincian, 'uraian'), array_column($dipa['rincian'], 'uraian'));
        $this->assertSame(array_column(KalkulatorRab::hitung($rincian)->rincian, 'jumlah'), array_column($dipa['rincian'], 'jumlah'));
        $this->assertSame(['521211', 'Belanja Bahan'], [$dipa['rincian'][0]['kode'], $dipa['rincian'][0]['akun']]);
        $this->assertSame([$dipa['total'], $dipa['total']], [array_sum(array_column($dipa['per_kategori'], 'total')), array_sum(array_column($dipa['per_kode'], 'total'))]);
        $this->assertSame(['bahan', 'jasa', 'lain'], array_column($dipa['per_kategori'], 'kategori'));
        $this->assertSame($json['sumber'][0]['total'] + $json['sumber'][1]['total'], $json['total']);
    }

    public function test_rab_sumber_yang_tidak_dipilih_ikut_tanpa_menambah_total(): void
    {
        app(RabRepository::class)->tambahItem(1, 'DIPA', ['kode' => '', 'uraian' => 'Spanduk kegiatan', 'kategori' => 'lain', 'volume' => 1, 'satuan' => 'buah', 'harga_satuan' => 150_000]);

        $json = $this->getJson(route('kegiatan.rab_lengkap', ['kegiatan' => 1]))->assertOk()->json();
        $this->assertSame([['DIPA', false], ['Komite', true]], array_map(fn ($s) => [$s['sumber'], $s['dipilih']], $json['sumber']), 'Urutan baku sumber dana.');
        $this->assertSame([10_000_000, 'Sepuluh Juta Rupiah'], [$json['total'], $json['terbilang']]);
        $this->assertSame(150_000, $json['sumber'][0]['total']);
    }

    public function test_hak_akses_dan_hasil_dari_database(): void
    {
        $this->getJson(route('kegiatan.rab_lengkap', ['kegiatan' => 999]))->assertNotFound();
        session(['stub_role' => 'admin']);
        $lain = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Lomba Kebersihan Kelas', 'leading' => '1.2']);
        session(['stub_role' => 'leading']);
        $this->getJson(route('kegiatan.rab_lengkap', ['kegiatan' => $lain]))->assertForbidden();

        $tiruan = $this->getJson(route('kegiatan.rab_lengkap', ['kegiatan' => 2]))->json();
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(RabRepository::class, EloquentRabRepository::class);
        $id = app(KegiatanRepository::class)->simpan(Arr::except(StubKegiatanRepository::contohDuaSumber(), ['id', 'status']));
        foreach (StubRab::DAFTAR_CONTOH[2] as $sumber) {
            foreach (StubRab::rincian(2, $sumber) as $item) {
                app(RabRepository::class)->tambahItem($id, $sumber, $item);
            }
        }
        $basisData = $this->getJson(route('kegiatan.rab_lengkap', ['kegiatan' => $id]))->assertOk()->json();

        $tanpaId = fn (array $j) => array_merge(Arr::except($j, 'kegiatan_id'), ['sumber' => array_map(fn ($s) => array_merge($s, ['rincian' => array_map(fn ($r) => Arr::except($r, 'id'), $s['rincian'])]), $j['sumber'])]);
        $this->assertSame($tanpaId($tiruan), $tanpaId($basisData));
    }
}
