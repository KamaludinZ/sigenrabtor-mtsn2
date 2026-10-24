<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Rab;
use App\Support\Stub\StubRab;
use Livewire\Livewire;
use Tests\TestCase;

class HalamanRabTest extends TestCase
{
    public function test_halaman_rab_menampilkan_header_dan_rincian_contoh(): void
    {
        $this->get(route('kegiatan.rab', ['kegiatan' => 1]))
            ->assertOk()
            ->assertSee('Rencana Anggaran Biaya')
            ->assertSee('RAB Komite')
            ->assertSee('Tim Penjamin Mutu')
            ->assertSee('6.17.12')
            ->assertSee('Honorarium Pemateri')
            ->assertSee('10.000.000')
            ->assertSee('Rp10.000.000,-');
    }

    public function test_jumlah_baris_dihitung_dari_volume_kali_harga_dan_total_cocok_dengan_dokumen(): void
    {
        $komponen = Livewire::test(Rab::class, ['kegiatan' => 1]);

        $this->assertCount(15, $komponen->instance()->rincian);
        $this->assertSame(3_600_000, $komponen->instance()->rincian[0]['jumlah']);
        $this->assertSame(10_000_000, $komponen->instance()->total);
        $komponen->assertSee('Sesuai pagu');
    }

    public function test_per_kelompok_belanja_dijumlahkan(): void
    {
        $total = collect(StubRab::rincian(1, 'Komite'))
            ->groupBy('kategori')
            ->map(fn ($baris) => $baris->sum(fn ($i) => $i['volume'] * $i['harga_satuan']));

        $this->assertSame(10_000_000, (int) $total->sum());
        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->assertSee('Per kelompok belanja')
            ->assertSee('Rp'.number_format($total['jasa'], 0, ',', '.'));
    }

    public function test_pilih_sumber_dana_dan_simpan_di_url(): void
    {
        $id = app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Kegiatan Dua Sumber',
            'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 5_000_000], ['sumber' => 'Komite', 'pagu' => 2_000_000]],
        ]);

        Livewire::test(Rab::class, ['kegiatan' => $id])
            ->assertSet('sumber', 'DIPA')
            ->assertSee('Belum ada rincian belanja untuk RAB DIPA')
            ->call('pilihSumber', 'Komite')
            ->assertSet('sumber', 'Komite')
            ->call('pilihSumber', 'Lainnya')
            ->assertSet('sumber', 'Komite');

        Livewire::withQueryParams(['sumber' => 'Komite'])
            ->test(Rab::class, ['kegiatan' => $id])
            ->assertSet('sumber', 'Komite');
    }

    public function test_kegiatan_tanpa_sumber_dana_diarahkan_mengatur_sumber_dana(): void
    {
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Tanpa Dana']);

        $this->get(route('kegiatan.rab', ['kegiatan' => $id]))
            ->assertOk()
            ->assertSee('Sumber dana belum dipilih')
            ->assertSee('Atur sumber dana');
    }

    public function test_kegiatan_tidak_ada_404(): void
    {
        $this->get(route('kegiatan.rab', ['kegiatan' => 99]))->assertNotFound();
    }

    public function test_tab_rab_aktif_di_halaman_data_kegiatan_tersimpan(): void
    {
        $this->get(route('kegiatan.edit', ['kegiatan' => 1]))
            ->assertSee(route('kegiatan.rab', ['kegiatan' => 1]), false);
    }
}
