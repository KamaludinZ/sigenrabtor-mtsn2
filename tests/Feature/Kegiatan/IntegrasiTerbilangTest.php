<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\Form;
use App\Livewire\Kegiatan\Rab;
use App\Support\Kegiatan\KalimatKak;
use Livewire\Livewire;
use Tests\TestCase;

class IntegrasiTerbilangTest extends TestCase
{
    public function test_kalimat_pembiayaan_kak_dari_total(): void
    {
        $this->assertSame(
            'Biaya yang terkait dengan kegiatan ini diperlukan sebesar Rp10.000.000,- (Sepuluh Juta Rupiah) yang dibebankan pada dana KOMITE MTsN 2 Kota Malang Tahun 2026, sebagaimana RAB terlampir.',
            KalimatKak::pembiayaan(10_000_000, 'Komite', 2026, 'MTsN 2 Kota Malang'),
        );
    }

    public function test_halaman_rab_menampilkan_terbilang_di_header_ringkasan_dan_kalimat_kak(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->assertSeeHtml('data-testid="header-terbilang">Sepuluh Juta Rupiah')
            ->assertSee('“Sepuluh Juta Rupiah”', false)
            ->assertSee('sebesar Rp10.000.000,- (Sepuluh Juta Rupiah) yang dibebankan pada dana KOMITE');
    }

    public function test_terbilang_ikut_berubah_saat_item_diubah(): void
    {
        $item = app(RabRepository::class)->rincian(1, 'Komite')[0];

        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->call('ubahItem', $item['id'])
            ->set('item.volume', '7')
            ->call('simpanItem')
            ->assertSee('Sepuluh Juta Enam Ratus Ribu Rupiah')
            ->assertDontSee('sebesar Rp10.000.000,-');
    }

    public function test_rekap_dan_data_kegiatan_menampilkan_terbilang(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->set('halaman', 'rekap')
            ->assertSeeHtml('data-testid="rekap-terbilang"')
            ->assertSee('Sepuluh Juta Rupiah');

        Livewire::test(Form::class, ['kegiatan' => 1])
            ->assertSee('RAB tersusun (15 item)')
            ->assertSee('Sepuluh Juta Rupiah');
    }

    public function test_form_item_punya_pratinjau_terbilang_klien(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->call('bukaFormItem')
            ->assertSeeHtml('x-text="$terbilang(jumlah)"');
    }
}
