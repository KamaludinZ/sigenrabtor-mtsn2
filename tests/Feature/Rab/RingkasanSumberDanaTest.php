<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Rab;
use Livewire\Livewire;
use Tests\TestCase;

class RingkasanSumberDanaTest extends TestCase
{
    public function test_kartu_per_sumber_dan_total_kegiatan(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 2])
            ->assertSeeHtml('data-testid="ringkasan-sumber"')
            ->assertSeeInOrder(['RAB DIPA', 'Rp5.400.000', 'Pagu Rp6.000.000 · 6 item', 'Sisa Rp600.000', 'RAB Komite', 'Rp2.500.000', 'Sesuai pagu'])
            ->assertSeeHtml('data-testid="total-kegiatan"')
            ->assertSee('Rp7.900.000')
            ->assertSee('Pagu Rp8.500.000 · 9 item')
            ->assertSee('2 RAB terpisah')
            ->assertSee('Sedang dibuka');
    }

    public function test_satu_sumber_tanpa_kartu_total_dan_menampilkan_termin(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->assertDontSeeHtml('data-testid="total-kegiatan"')
            ->assertSee('2 termin')
            ->assertDontSee('belum pas');
    }

    public function test_kartu_mengikuti_perubahan_rincian(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 2])
            ->call('bukaFormItem')
            ->set('item.uraian', 'Tambahan ATK')
            ->set('item.volume', '1')
            ->set('item.satuan', 'paket')
            ->set('item.harga_satuan', '900000')
            ->call('simpanItem')
            ->assertSee('Melebihi Rp300.000')
            ->assertSee('Pagu Rp6.000.000 · 7 item')
            ->assertSee('Rp8.800.000');
    }

    public function test_kartu_dari_rekap_membuka_rincian_sumber_itu(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 2])
            ->set('halaman', 'rekap')
            ->assertDontSee('Sedang dibuka')
            ->call('pilihSumber', 'Komite')
            ->assertSet('halaman', 'rincian')
            ->assertSet('sumber', 'Komite');
    }

    public function test_rab_sumber_yang_dilepas_diberi_peringatan(): void
    {
        $repo = app(KegiatanRepository::class);
        $kegiatan = $repo->temukan(2);
        $kegiatan['sumber_dana'] = [['sumber' => 'Komite', 'pagu' => 2_500_000]];
        $repo->simpan($kegiatan, 2);

        Livewire::test(Rab::class, ['kegiatan' => 2])
            ->assertSeeHtml('data-testid="rab-terlepas"')
            ->assertSee('RAB DIPA (6 item, Rp5.400.000) masih tersimpan')
            ->assertDontSeeHtml('data-sumber="DIPA"')
            ->assertDontSeeHtml('data-testid="total-kegiatan"');
    }
}
