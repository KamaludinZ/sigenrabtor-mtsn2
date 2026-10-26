<?php

namespace Tests\Feature\Monitoring;

use App\Contracts\KegiatanRepository;
use App\Livewire\Monitoring\Anggaran;
use App\Support\Monitoring\RingkasanAnggaran;
use Livewire\Livewire;
use Tests\TestCase;

class MonitoringAnggaranTest extends TestCase
{
    public function test_hanya_admin_dan_menu_aktif(): void
    {
        $this->get(route('monitoring.index'))->assertForbidden();

        $this->withSession(['stub_role' => 'admin'])->get(route('monitoring.index'))
            ->assertOk()
            ->assertSeeLivewire(Anggaran::class)
            ->assertSee('<title>Monitoring Anggaran · ', false)
            ->assertSeeHtml('href="'.route('monitoring.index').'"')
            ->assertSeeHtml('aria-current="page"');
    }

    public function test_ringkasan_contoh_kegiatan(): void
    {
        $ringkasan = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua());

        $this->assertSame(3, $ringkasan->jumlahKegiatan());
        $this->assertSame(['draf' => 3, 'diperiksa' => 0, 'dikembalikan' => 0, 'disetujui' => 0, 'final' => 0], $ringkasan->perStatus());
        $this->assertSame([
            'DIPA' => ['pagu' => 6_000_000, 'total' => 6_300_000, 'selisih' => -300_000, 'rab' => 2, 'melebihi' => 0, 'tanpa_pagu' => 1],
            'Komite' => ['pagu' => 20_000_000, 'total' => 20_450_000, 'selisih' => -450_000, 'rab' => 3, 'melebihi' => 1, 'tanpa_pagu' => 0],
        ], $ringkasan->perSumber());
        $this->assertSame(['pagu' => 26_000_000, 'total' => 26_750_000, 'selisih' => -750_000, 'rab' => 5, 'melebihi' => 1, 'tanpa_pagu' => 1], $ringkasan->total());

        $dipa = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), ['sumber' => 'DIPA']);
        $this->assertSame([2, 0], [$dipa->jumlahKegiatan(), $dipa->perSumber()['Komite']['rab']]);
        $this->assertSame(0, RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), ['leading' => '1.1'])->jumlahKegiatan());
        $this->assertSame(0, RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), ['status' => 'final'])->jumlahKegiatan());
    }

    public function test_halaman_dengan_saringan_di_url(): void
    {
        app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Final', 'status' => 'final', 'leading' => '1.1', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]]]);

        Livewire::test(Anggaran::class)
            ->assertSeeHtml('data-testid="kartu-kegiatan"')
            ->assertSeeHtmlInOrder(['Kegiatan', '4', 'data-status-jumlah="draf"', 'data-status-jumlah="final"'])
            ->assertSeeHtmlInOrder(['Total pagu', 'Rp27.000.000', 'Total RAB', 'Rp26.750.000', 'Sisa pagu', 'Rp250.000'])
            ->assertSeeHtml('data-testid="jumlah-melebihi"')
            ->assertSee('1 RAB melebihi pagunya')
            ->assertSee('1 RAB belum berpagu')
            ->assertSeeHtmlInOrder(['Total per leading', 'Rekap per kelompok belanja', 'Rincian per kegiatan', 'Rekap per operator'])
            ->set('sumber', 'Komite')
            ->assertSeeHtml('data-testid="saringan-aktif"')
            ->assertSee('Dana Komite')
            ->assertSeeHtmlInOrder(['Total pagu', 'Rp20.000.000', 'Total RAB', 'Rp20.450.000'])
            ->set('status', 'final')
            ->assertSeeHtmlInOrder(['Kegiatan', '0'])
            ->call('hapusSaringan', 'status')
            ->assertSet('status', '')
            ->assertSet('sumber', 'Komite')
            ->call('hapusSaringan')
            ->assertSet('sumber', '')
            ->assertDontSeeHtml('data-testid="saringan-aktif"');

        Livewire::withQueryParams(['leading' => '9.9', 'sumber' => 'BOS', 'status' => 'acak'])->test(Anggaran::class)
            ->assertSet('leading', '')->assertSet('sumber', '')->assertSet('status', '');
        Livewire::withQueryParams(['leading' => '1.1'])->test(Anggaran::class)
            ->assertSet('leading', '1.1')->assertSee('Waka Kurikulum')->assertSeeHtmlInOrder(['Total pagu', 'Rp1.000.000']);
    }
}
