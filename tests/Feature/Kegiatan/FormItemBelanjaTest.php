<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Livewire\Forms\ItemBelanjaForm;
use App\Livewire\Kegiatan\Rab;
use App\Support\Format;
use Livewire\Livewire;
use Tests\TestCase;

class FormItemBelanjaTest extends TestCase
{
    private function rab(int $kegiatan = 1)
    {
        return Livewire::test(Rab::class, ['kegiatan' => $kegiatan]);
    }

    public function test_tebak_kelompok_belanja_dari_uraian(): void
    {
        $this->assertSame('jasa', ItemBelanjaForm::tebakKategori('Honorarium Narasumber'));
        $this->assertSame('lain', ItemBelanjaForm::tebakKategori('Transport Pengawas'));
        $this->assertSame('bahan', ItemBelanjaForm::tebakKategori('Nasi Kotak Guru'));
    }

    public function test_volume_desimal_dan_format_angka(): void
    {
        $this->assertSame(1.5, ItemBelanjaForm::angkaVolume('1,5'));
        $this->assertNull(ItemBelanjaForm::angkaVolume('dua'));
        $this->assertSame('1,5', Format::angka(1.5));
        $this->assertSame('12', Format::angka(12.0));
    }

    public function test_buka_isi_dan_simpan_item_baru(): void
    {
        $this->rab()
            ->assertSee('Tambah item belanja')
            ->call('bukaFormItem')
            ->assertSet('formItemTerbuka', true)
            ->assertDispatched('form-item-dibuka')
            ->set('item.uraian', 'Honorarium Narasumber Tambahan')
            ->assertSet('item.kategori', 'jasa')
            ->set('item.kode', '521213')
            ->set('item.volume', '2')
            ->set('item.satuan', 'OJ')
            ->set('item.harga_satuan', '750000')
            ->assertSet('item.harga_satuan', '750.000')
            ->call('simpanItem')
            ->assertHasNoErrors()
            ->assertSet('formItemTerbuka', false)
            ->assertDispatched('notify', message: 'Item ditambahkan: Honorarium Narasumber Tambahan (Rp1.500.000).')
            ->assertSee('Honorarium Narasumber Tambahan')
            ->assertSee('11.500.000')
            ->assertSee('Melebihi pagu');

        $rincian = app(RabRepository::class)->rincian(1, 'Komite');
        $this->assertCount(16, $rincian);
        $this->assertSame(['kode' => '521213', 'uraian' => 'Honorarium Narasumber Tambahan', 'kategori' => 'jasa', 'volume' => 2, 'satuan' => 'OJ', 'harga_satuan' => 750_000],
            array_diff_key(end($rincian), ['id' => true]));
    }

    public function test_simpan_dan_tambah_lagi_mempertahankan_form_terbuka_dan_kosong(): void
    {
        $this->rab()
            ->call('bukaFormItem')
            ->set('item.uraian', 'Air Mineral')
            ->set('item.volume', '1,5')
            ->set('item.satuan', 'dus')
            ->set('item.harga_satuan', '40.000')
            ->call('simpanItem', true)
            ->assertSet('formItemTerbuka', true)
            ->assertSet('item.uraian', '')
            ->assertSet('item.kategori', '');

        $terakhir = collect(app(RabRepository::class)->rincian(1, 'Komite'))->last();
        $this->assertSame(1.5, $terakhir['volume']);
    }

    public function test_kategori_manual_tidak_ditimpa_tebakan(): void
    {
        $this->rab()
            ->call('bukaFormItem')
            ->set('item.uraian', 'Nasi Kotak')
            ->assertSet('item.kategori', 'bahan')
            ->set('item.kategori', 'lain')
            ->set('item.uraian', 'Nasi Kotak Narasumber Honor')
            ->assertSet('item.kategori', 'lain');
    }

    public function test_validasi_item_belanja(): void
    {
        $this->rab()
            ->call('bukaFormItem')
            ->call('simpanItem')
            ->assertHasErrors(['item.uraian', 'item.volume', 'item.satuan', 'item.harga_satuan'])
            ->set('item.volume', '0')
            ->assertHasErrors('item.volume')
            ->set('item.volume', 'dua')
            ->assertHasErrors('item.volume')
            ->set('item.kode', 'kode akun!')
            ->assertHasErrors('item.kode');

        $this->assertCount(15, app(RabRepository::class)->rincian(1, 'Komite'));
    }

    public function test_batal_menutup_dan_mengosongkan_form(): void
    {
        $this->rab()
            ->call('bukaFormItem')
            ->set('item.uraian', 'Spanduk')
            ->call('tutupFormItem')
            ->assertSet('formItemTerbuka', false)
            ->assertSet('item.uraian', '');
    }

    public function test_item_tersimpan_per_sumber_dana(): void
    {
        $id = app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Dua Sumber',
            'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000], ['sumber' => 'Komite', 'pagu' => 1_000_000]],
        ]);

        $this->rab($id)
            ->call('bukaFormItem')
            ->set('item.uraian', 'ATK peserta')
            ->set('item.volume', '10')
            ->set('item.satuan', 'pack')
            ->set('item.harga_satuan', '15.000')
            ->call('simpanItem');

        $this->assertCount(1, app(RabRepository::class)->rincian($id, 'DIPA'));
        $this->assertCount(0, app(RabRepository::class)->rincian($id, 'Komite'));
    }

    public function test_rab_terkunci_saat_kegiatan_diperiksa(): void
    {
        $repo = app(KegiatanRepository::class);
        $repo->simpan(['status' => 'diperiksa'] + $repo->temukan(1), 1);

        $this->rab()
            ->assertDontSee('Tambah item belanja')
            ->assertSee('RAB terkunci karena kegiatan berstatus Diperiksa.')
            ->call('bukaFormItem')
            ->assertForbidden();
    }
}
