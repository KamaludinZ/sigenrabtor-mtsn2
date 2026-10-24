<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\Form;
use App\Livewire\Kegiatan\Rab;
use Livewire\Livewire;
use Tests\TestCase;

class TotalRabOtomatisTest extends TestCase
{
    public function test_total_header_ringkasan_dan_data_kegiatan_selalu_sama(): void
    {
        $item = app(RabRepository::class)->rincian(1, 'Komite')[0];

        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->call('ubahItem', $item['id'])
            ->set('item.volume', '7')
            ->call('simpanItem')
            ->assertSee('Rp10.600.000,-')          // header "Jumlah yang diajukan"
            ->assertSee('10.600.000')              // total tabel
            ->assertSee('Melebihi pagu')
            ->assertSee('Rp600.000');

        Livewire::test(Form::class, ['kegiatan' => 1])
            ->assertSee('RAB tersusun (15 item)')
            ->assertSee('Rp10.600.000 dari pagu Rp10.000.000');
    }

    public function test_kegiatan_baru_belum_menampilkan_rekap_rab(): void
    {
        Livewire::test(Form::class)
            ->set('form.dana_dipa', true)
            ->assertDontSee('RAB tersusun');
    }
}
