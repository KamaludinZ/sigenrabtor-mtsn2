<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\TahunAnggaranRepository;
use App\Livewire\Pengaturan\DasarHukum;
use App\Livewire\Pengaturan\ProfilMadrasah;
use App\Support\Pengaturan\KontrolTahunAktif;
use App\Support\Pengaturan\TahunDipilih;
use Livewire\Livewire;
use Tests\TestCase;

class TahunDipilihTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    public function test_aturan_parameter_tahun(): void
    {
        foreach ([[null, 2026], ['', 2026], [' 2025 ', 2025], ['2026', 2026], [2025, 2025], ['2031', null], ['20x5', null], ['1999', null]] as [$ta, $harapan]) {
            $this->assertSame($harapan, TahunDipilih::dari($ta), json_encode($ta));
        }
        $this->assertNull(TahunDipilih::dari('2025', ['role' => 'leading']), 'Tahun lain hanya untuk admin.');
        $this->assertSame(2026, TahunDipilih::dari('2026', ['role' => 'leading']));
        $this->assertSame(2025, TahunDipilih::dari('2025', self::ADMIN));

        $this->assertSame(['', '2025', '', ''], [TahunDipilih::parameter('2026'), TahunDipilih::parameter('2025'), TahunDipilih::parameter('2031'), TahunDipilih::parameter('abc')]);
        $this->assertSame([true, false], [TahunDipilih::terkunci(2025), TahunDipilih::terkunci(2026)]);
    }

    public function test_tahun_aktif_berganti_mengubah_bawaan(): void
    {
        app(TahunAnggaranRepository::class)->tambah(2027);
        KontrolTahunAktif::aktifkan(2027, self::ADMIN);

        $this->assertSame(2027, TahunDipilih::dari(''));
        $this->assertSame('2026', TahunDipilih::parameter('2026'), 'Tahun aktif lama kini tahun lain.');
        $this->withSession(['stub_role' => 'admin'])->getJson(route('pengaturan.madrasah.dasar_hukum.data'))
            ->assertJsonPath('tahun', 2027)
            ->assertJsonPath('diatur', false)
            ->assertJsonPath('butir', []);
    }

    public function test_halaman_dan_endpoint_memakai_filter_yang_sama(): void
    {
        session(['stub_role' => 'admin']);
        Livewire::withQueryParams(['ta' => '2031'])->test(DasarHukum::class)->assertSet('tahun', '')->assertSee('TA 2026');
        Livewire::withQueryParams(['ta' => '2026'])->test(DasarHukum::class)->assertSet('tahun', '');
        Livewire::withQueryParams(['ta' => '2025'])->test(DasarHukum::class)->assertSet('tahun', '2025')
            ->set('tahun', 'xx')
            ->assertSet('tahun', '');
        Livewire::withQueryParams(['tab' => 'pejabat', 'ta' => '2031'])->test(ProfilMadrasah::class)->assertSet('tahunPejabat', '');

        foreach (['pengaturan.madrasah.pejabat', 'pengaturan.madrasah.dasar_hukum.data', 'pengaturan.madrasah.latar_belakang.data', 'pengaturan.madrasah.sk_rktm.data'] as $rute) {
            $this->getJson(route($rute, ['ta' => 2031]))->assertNotFound();
            $this->getJson(route($rute, ['ta' => 'abc']))->assertNotFound();
            $this->getJson(route($rute, ['ta' => 2025]))->assertOk()->assertJsonPath('tahun', 2025);
            $this->getJson(route($rute))->assertOk()->assertJsonPath('tahun', 2026);
        }
    }
}
