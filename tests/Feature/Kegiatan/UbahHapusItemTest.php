<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Livewire\Forms\ItemBelanjaForm;
use App\Livewire\Kegiatan\Rab;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class UbahHapusItemTest extends TestCase
{
    /**
     * @return list<array<string, mixed>>
     */
    private function rincian(): array
    {
        return app(RabRepository::class)->rincian(1, 'Komite');
    }

    public function test_volume_format_indonesia_dibaca_benar(): void
    {
        $this->assertSame(1000.0, ItemBelanjaForm::angkaVolume('1.000'));
        $this->assertSame(1250.5, ItemBelanjaForm::angkaVolume('1.250,5'));
        $this->assertSame(1.5, ItemBelanjaForm::angkaVolume('1,5'));
        $this->assertSame('1,5', ItemBelanjaForm::teksVolume(1.5));
        $this->assertSame('1000', ItemBelanjaForm::teksVolume(1000));
    }

    public function test_ubah_item_mengisi_form_lalu_menyimpan_perubahan_di_tempat(): void
    {
        $honor = $this->rincian()[0];

        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->call('ubahItem', $honor['id'])
            ->assertSet('itemDiedit', $honor['id'])
            ->assertSet('formItemTerbuka', true)
            ->assertSet('item.uraian', 'Honorarium Pemateri')
            ->assertSet('item.volume', '6')
            ->assertSet('item.harga_satuan', '600.000')
            ->assertSet('item.kategori', 'jasa')
            ->assertSee('Ubah item no. 1')
            ->assertSee('Simpan perubahan')
            ->set('item.volume', '5')
            ->call('simpanItem')
            ->assertHasNoErrors()
            ->assertSet('itemDiedit', null)
            ->assertSet('formItemTerbuka', false)
            ->assertDispatched('notify', message: 'Item diperbarui: Honorarium Pemateri (Rp3.000.000).')
            ->assertSee('9.400.000');

        $rincian = $this->rincian();
        $this->assertCount(15, $rincian, 'Mengubah tidak menambah baris.');
        $this->assertSame($honor['id'], $rincian[0]['id'], 'Posisi & id item tetap.');
        $this->assertSame(5, $rincian[0]['volume']);
    }

    public function test_hapus_item_lalu_urungkan_ke_posisi_semula(): void
    {
        $item = $this->rincian()[3];

        $komponen = Livewire::test(Rab::class, ['kegiatan' => 1])
            ->call('hapusItem', $item['id'])
            ->assertSee('“Nasi Kotak Guru” dihapus dari RAB Komite.')
            ->assertSee('8.380.000');

        $this->assertCount(14, $this->rincian());

        $komponen->call('urungkanHapusItem')
            ->assertSet('itemTerhapus', null)
            ->assertSee('10.000.000');

        $this->assertSame($item['id'], $this->rincian()[3]['id']);
    }

    public function test_menghapus_item_yang_sedang_diubah_menutup_form(): void
    {
        $item = $this->rincian()[0];

        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->call('ubahItem', $item['id'])
            ->call('hapusItem', $item['id'])
            ->assertSet('formItemTerbuka', false)
            ->assertSet('itemDiedit', null);
    }

    public function test_ubah_item_yang_tidak_ada_memberi_pesan(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->call('ubahItem', 'tidak-ada')
            ->assertDispatched('notify', type: 'error')
            ->assertSet('formItemTerbuka', false);
    }

    public function test_id_item_diedit_tidak_bisa_diubah_dari_browser(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(Rab::class, ['kegiatan' => 1])->set('itemDiedit', 'apa-saja');
    }

    public function test_ubah_dan_hapus_terkunci_saat_status_bukan_draf(): void
    {
        $repo = app(KegiatanRepository::class);
        $repo->simpan(['status' => 'final'] + $repo->temukan(1), 1);
        $id = $this->rincian()[0]['id'];

        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->assertDontSee('Ubah item 1')
            ->call('hapusItem', $id)
            ->assertForbidden();

        $this->assertCount(15, $this->rincian());
    }
}
