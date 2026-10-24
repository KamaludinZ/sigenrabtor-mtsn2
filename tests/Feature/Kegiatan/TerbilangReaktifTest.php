<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\Form;
use App\Livewire\Kegiatan\Rab;
use Livewire\Livewire;
use Tests\TestCase;

class TerbilangReaktifTest extends TestCase
{
    public function test_terbilang_mengikuti_tambah_hapus_dan_urungkan_item(): void
    {
        $komponen = Livewire::test(Rab::class, ['kegiatan' => 1])
            ->assertSee('Sepuluh Juta Rupiah')
            ->call('bukaFormItem')
            ->set('item.uraian', 'Spanduk kegiatan')
            ->set('item.volume', '1')
            ->set('item.satuan', 'buah')
            ->set('item.harga_satuan', '250000')
            ->call('simpanItem')
            ->assertSee('Sepuluh Juta Dua Ratus Lima Puluh Ribu Rupiah');

        $baru = collect(app(RabRepository::class)->rincian(1, 'Komite'))->last();

        $komponen->call('hapusItem', $baru['id'])
            ->assertSee('Sepuluh Juta Rupiah')
            ->assertDontSee('Sepuluh Juta Dua Ratus Lima Puluh Ribu Rupiah')
            ->call('urungkanHapusItem')
            ->assertSee('Sepuluh Juta Dua Ratus Lima Puluh Ribu Rupiah');
    }

    public function test_terbilang_mengikuti_sumber_dana_yang_dipilih(): void
    {
        $id = app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Dua Sumber',
            'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000], ['sumber' => 'Komite', 'pagu' => 1_000_000]],
        ]);
        $repo = app(RabRepository::class);
        $repo->tambahItem($id, 'DIPA', ['kode' => '', 'uraian' => 'ATK', 'kategori' => 'bahan', 'volume' => 1, 'satuan' => 'paket', 'harga_satuan' => 300_000]);
        $repo->tambahItem($id, 'Komite', ['kode' => '', 'uraian' => 'Konsumsi', 'kategori' => 'bahan', 'volume' => 1, 'satuan' => 'paket', 'harga_satuan' => 750_000]);

        Livewire::test(Rab::class, ['kegiatan' => $id])
            ->assertSee('Tiga Ratus Ribu Rupiah')
            ->call('pilihSumber', 'Komite')
            ->assertSee('Tujuh Ratus Lima Puluh Ribu Rupiah')
            ->assertDontSee('Tiga Ratus Ribu Rupiah');
    }

    public function test_input_pagu_dan_harga_menyertakan_terbilang_langsung(): void
    {
        Livewire::test(Form::class, ['kegiatan' => 1])
            ->assertSeeHtml('data-testid="pagu_komite-terbilang"')
            ->assertSee('Sepuluh Juta Rupiah');

        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->call('bukaFormItem')
            ->assertSeeHtml('data-testid="item_harga_satuan-terbilang"')
            ->assertSeeHtml('$wire.item.harga_satuan');
    }
}
