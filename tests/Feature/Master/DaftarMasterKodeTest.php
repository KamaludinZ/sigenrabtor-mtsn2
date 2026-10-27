<?php

namespace Tests\Feature\Master;

use App\Contracts\MasterKodeRepository;
use App\Livewire\Master\Daftar;
use App\Support\Master\JenisKode;
use App\Support\MasterKode;
use App\Support\Navigation;
use App\Support\Pengaturan\BagianPengaturan;
use App\Support\Stub\StubMasterKodeRepository;
use Livewire\Livewire;
use Tests\TestCase;

class DaftarMasterKodeTest extends TestCase
{
    public function test_khusus_admin_menu_aktif_dan_bagian_pengaturan_tersedia(): void
    {
        $this->get(route('master.index'))->assertForbidden();

        $this->withSession(['stub_role' => 'admin'])->get(route('master.index'))
            ->assertOk()
            ->assertSeeLivewire(Daftar::class)
            ->assertSee('<title>Master Kode · ', false);

        session(['stub_role' => 'admin']);
        $menu = collect(Navigation::groups('admin'))->flatMap(fn ($g) => $g['items'])->firstWhere('label', 'Master Kode');
        $this->assertTrue($menu['available']);
        $this->assertTrue(collect(BagianPengaturan::semua())->firstWhere('kunci', 'master')['tersedia']);
    }

    public function test_jenis_kode_jumlah_dan_pemakaian(): void
    {
        $jumlah = JenisKode::jumlah(2026);
        $this->assertSame(['sk', 'iksk', 'rktm', 'edm', 'budaya'], array_keys($jumlah));
        $this->assertSame(count(MasterKode::kodeSk(2026)), $jumlah['sk']);
        $this->assertSame(count(MasterKode::kodeRktm(2026)), $jumlah['rktm']);
        $this->assertSame(count(MasterKode::budayaMutu(2026)), $jumlah['budaya']);
        $this->assertSame(['sk' => 0, 'iksk' => 0, 'rktm' => 0, 'edm' => 0, 'budaya' => 0], JenisKode::jumlah(2030));

        $kegiatan = [['kode_sk' => 'SK.10'], ['kode_sk' => 'SK.10', 'budaya_mutu' => 2], ['kode_sk' => null], ['kode_sk' => 'SK.11']];
        $this->assertSame(['SK.10' => 2, 'SK.11' => 1], JenisKode::pemakaian('sk', $kegiatan));
        $this->assertSame(['2' => 1], JenisKode::pemakaian('budaya', $kegiatan));
    }

    public function test_tab_per_jenis_dengan_jumlah_dan_kode_dipakai(): void
    {
        session(['stub_role' => 'admin']);
        $jumlah = JenisKode::jumlah(2026);

        $komponen = Livewire::test(Daftar::class)
            ->assertSet('jenis', 'sk')
            ->assertSeeHtml('data-testid="master-kode"')
            ->assertSeeHtmlInOrder(['data-jenis="sk"', (string) $jumlah['sk'], 'data-jenis="iksk"', 'data-jenis="rktm"', 'data-jenis="edm"', 'data-jenis="budaya"', '5 Budaya Mutu'])
            ->assertSeeHtml('aria-selected="true" data-jenis="sk"')
            ->assertSee('Sasaran Kegiatan (SK)')
            ->assertSeeHtml('data-kode="SK.10"')
            // Contoh kegiatan 1 & 3 memakai SK.10, kegiatan 2 SK.11.
            ->assertSeeHtmlInOrder(['data-kode="SK.10"', '2 kegiatan'])
            ->assertSee($jumlah['sk'].' kode · 2 dipakai kegiatan TA 2026');

        $komponen->set('jenis', 'iksk')
            ->assertSee('Indikator Kinerja Sasaran Kegiatan (IKSK)')
            ->assertSeeHtmlInOrder(['data-kode="IKSK 10.2"', 'SK.10', 'Persentase rencana kerja tahunan']);
        $komponen->set('jenis', 'rktm')
            ->assertSeeHtmlInOrder(['data-kode="6.17.12"', '1 kegiatan']);
        $komponen->set('jenis', 'edm')->assertSeeHtml('data-kode="A1"');
        $komponen->set('jenis', 'budaya')->assertSee('Integritas')->assertSeeHtml('data-kode="1"');
        $komponen->set('jenis', 'tidak-ada')->assertSet('jenis', 'sk');

        Livewire::withQueryParams(['jenis' => 'rktm'])->test(Daftar::class)->assertSet('jenis', 'rktm')->assertSee('Rencana Kerja Tahunan Madrasah (RKTM)');
    }

    public function test_tahun_lain_terkunci_dan_tahun_tanpa_master(): void
    {
        session(['stub_role' => 'admin']);

        Livewire::test(Daftar::class)
            ->set('tahun', '2025')
            ->assertSet('tahun', '2025')
            ->assertSeeHtml('aria-pressed="true" data-tahun="2025"')
            ->assertSeeHtml('data-testid="master-terkunci"')
            ->assertSee(JenisKode::jumlah(2025)['sk'].' kode · 0 dipakai kegiatan TA 2025')
            ->set('tahun', '2026')
            ->assertSet('tahun', '')
            ->set('tahun', '1999')
            ->assertSet('tahun', '');

        $this->app->instance(MasterKodeRepository::class, new class extends StubMasterKodeRepository
        {
            public function kodeRktm(int $tahun): array
            {
                return [];
            }
        });
        Livewire::test(Daftar::class)
            ->assertSeeHtml('data-testid="master-kode-kosong"')
            ->assertSee('Master kode TA 2026 belum diunggah')
            ->assertDontSeeHtml('data-testid="master-kode"');
    }
}
