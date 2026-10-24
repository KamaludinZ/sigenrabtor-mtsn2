<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Form;
use App\Livewire\Kegiatan\Rab;
use App\Support\Terbilang;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

class TerbilangNolKosongTest extends TestCase
{
    private function kegiatanTanpaRincian(): int
    {
        return app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Kegiatan Kosong',
            'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 2_000_000]],
        ]);
    }

    public function test_tampil_null_untuk_nol_atau_kosong(): void
    {
        $this->assertNull(Terbilang::tampil(null));
        $this->assertNull(Terbilang::tampil(0));
        $this->assertSame('Seribu Rupiah', Terbilang::tampil(1_000));
        $this->assertSame('Nol Rupiah', Terbilang::rupiah(0), 'Fungsi dasar tetap mengeja nol.');
    }

    public function test_komponen_total_nol_menampilkan_keterangan_bukan_nol_rupiah(): void
    {
        $html = Blade::render('<x-rab.total-anggaran :nilai="0" :pagu="2000000" />');

        $this->assertStringNotContainsString('Nol Rupiah', $html);
        $this->assertStringContainsString('Belum ada rincian belanja', $html);
        $this->assertStringContainsString('Sisa pagu Rp2.000.000', $html);

        $kosong = Blade::render('<x-rab.total-anggaran :nilai="null" />');
        $this->assertStringContainsString('Rp0', $kosong);
        $this->assertStringNotContainsString('Nol Rupiah', $kosong);
    }

    public function test_halaman_rab_tanpa_rincian(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => $this->kegiatanTanpaRincian()])
            ->assertDontSee('Nol Rupiah')
            ->assertSee('belum ada rincian')
            ->assertSee('Kalimat pembiayaan tercetak setelah rincian RAB DIPA diisi')
            ->assertDontSee('sebesar Rp0,-');
    }

    public function test_rekap_dan_data_kegiatan_tanpa_rincian(): void
    {
        $id = $this->kegiatanTanpaRincian();

        Livewire::test(Rab::class, ['kegiatan' => $id])
            ->set('halaman', 'rekap')
            ->assertDontSee('Nol Rupiah')
            ->assertSee('Belum ada rincian belanja.');

        Livewire::test(Form::class, ['kegiatan' => $id])
            ->assertDontSee('Nol Rupiah')
            ->assertSee('RAB belum berisi rincian')
            ->assertSee('pagu Rp2.000.000');
    }
}
