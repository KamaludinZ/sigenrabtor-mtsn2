<?php

namespace Tests\Feature;

use App\Contracts\KegiatanRepository;
use App\Livewire\Dashboard;
use App\Support\Monitoring\RingkasanAnggaran;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_halaman_utama_adalah_dashboard_dengan_menu_aktif(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeLivewire(Dashboard::class)
            ->assertSee('<title>Dashboard · ', false)
            ->assertSeeHtml('href="'.route('dashboard').'" wire:navigate')
            ->assertSeeHtml('aria-current="page"');
    }

    public function test_rekap_per_leading_dan_cakupan(): void
    {
        $repo = app(KegiatanRepository::class);
        $repo->simpan(['nama_kegiatan' => 'Lain', 'leading' => '1.1', 'status' => 'disetujui', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]]]);
        $repo->simpan(['nama_kegiatan' => 'Tanpa leading']);

        $perLeading = array_column(RingkasanAnggaran::dari($repo->semua())->perLeading(), null, 'kode');
        $this->assertSame([3, 3, 26_000_000, 26_750_000, 1], [$perLeading['1.6']['jumlah'], $perLeading['1.6']['perStatus']['draf'], $perLeading['1.6']['pagu'], $perLeading['1.6']['total'], $perLeading['1.6']['melebihi']]);
        $this->assertSame([1, 1], [$perLeading['1.1']['jumlah'], $perLeading['1.1']['perStatus']['disetujui']]);
        $this->assertSame(0, $perLeading['1.3']['jumlah']);
        $this->assertSame(['Leading belum dipilih', 1], [$perLeading['']['nama_unit'], $perLeading['']['jumlah']]);
        $this->assertCount(9, $perLeading);
        $this->assertCount(3, RingkasanAnggaran::dari($repo->semua())->perLeading(false));

        $cakupan = RingkasanAnggaran::dari($repo->semua(), ['leadings' => ['1.1']]);
        $this->assertSame(1, $cakupan->jumlahKegiatan());
    }

    public function test_leading_hanya_melihat_leading_nya(): void
    {
        app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Lain', 'leading' => '1.1', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]]]);

        Livewire::test(Dashboard::class)
            ->assertSee('1.6 · Tim Penjamin Mutu')
            ->assertSeeHtmlInOrder(['data-testid="kartu-kegiatan"', 'Kegiatan', '3'])
            ->assertSeeHtml('data-leading="1.6"')
            ->assertDontSeeHtml('data-leading="1.1"')
            ->assertDontSee('Monitoring Anggaran')
            ->assertSeeHtml('href="'.route('kegiatan.index', ['status' => 'draf']).'"')
            ->assertSeeHtml('data-testid="meter-pagu-komite" data-status="melebihi"')
            ->assertSee('Melebihi pagu Rp450.000')
            ->assertSeeHtml('data-testid="meter-pagu-dipa" data-status="melebihi"');

        session(['stub_role' => 'admin']);
        Livewire::test(Dashboard::class)
            ->assertSee('Semua leading')
            ->assertSeeHtml('data-leading="1.1"')
            ->assertSeeHtml('data-leading="1.3"')
            ->assertSee('Monitoring Anggaran')
            ->assertSeeHtmlInOrder(['data-testid="kartu-kegiatan"', 'Kegiatan', '4']);
    }

    public function test_meter_pagu_aman_sesuai_dan_tanpa_pagu(): void
    {
        $meter = fn (int $pagu, int $total, int $tanpa = 0) => Blade::render('<x-monitoring.meter-pagu sumber="DIPA" :pagu="$p" :total="$t" :tanpa-pagu="$n" />', ['p' => $pagu, 't' => $total, 'n' => $tanpa]);

        $this->assertStringContainsString('data-status="sisa"', $meter(10_000_000, 4_000_000));
        $this->assertStringContainsString('40%', $meter(10_000_000, 4_000_000));
        $this->assertStringContainsString('width: 40%', $meter(10_000_000, 4_000_000));
        $this->assertStringContainsString('Sesuai pagu', $meter(5_000_000, 5_000_000));
        $lebih = $meter(1_000_000, 1_500_000);
        $this->assertStringContainsString('width: 100%', $lebih, 'Isi meter tidak melampaui jalurnya.');
        $this->assertStringContainsString('150%', $lebih);
        $this->assertStringContainsString('bg-red-600', $lebih);
        $kosong = $meter(0, 900_000, 1);
        $this->assertStringContainsString('data-status="tanpa_pagu"', $kosong);
        $this->assertStringContainsString('pagu belum diisi', $kosong);
        $this->assertStringContainsString('1 RAB belum berpagu', $kosong);
    }
}
