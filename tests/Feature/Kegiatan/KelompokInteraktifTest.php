<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\Rab;
use App\Support\Rab\KelompokRincian;
use Livewire\Livewire;
use Tests\TestCase;

class KelompokInteraktifTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function item(int $index): array
    {
        return app(RabRepository::class)->rincian(1, 'Komite')[$index];
    }

    public function test_kelompok_kosong_disertakan_sebagai_sasaran_seret(): void
    {
        $rincian = [['kode' => '', 'kategori' => 'bahan', 'jumlah' => 100]];

        $this->assertSame(['bahan'], array_column(KelompokRincian::kelompokkan($rincian, 'kategori'), 'kunci'));
        $this->assertSame(['bahan', 'jasa', 'lain'], array_column(KelompokRincian::kelompokkan($rincian, 'kategori', sertakanKosong: true), 'kunci'));
    }

    public function test_seret_item_ke_kelompok_lain_mengubah_kategorinya(): void
    {
        $air = $this->item(10); // Air Mineral (bahan)

        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->set('kelompok', 'kategori')
            ->assertSee('Seret nomor item ke kelompok lain')
            ->assertSeeHtml('wire:sort:group-id="jasa"')
            ->call('pindahKategoriItem', $air['id'], 0, 'lain')
            ->assertDispatched('notify', message: '“Air Mineral” dipindah ke kelompok Lain-lain.')
            ->assertSee('Rp270.000');

        $this->assertSame('lain', $this->item(10)['kategori']);
        $this->assertSame($air['id'], $this->item(10)['id'], 'Urutan cetak tidak berubah.');
    }

    public function test_lepas_di_kelompok_yang_sama_tidak_mengubah_apa_pun(): void
    {
        $honor = $this->item(0);

        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->set('kelompok', 'kategori')
            ->call('pindahKategoriItem', $honor['id'], 3, 'jasa')
            ->assertNotDispatched('notify');

        $this->assertSame('jasa', $this->item(0)['kategori']);
    }

    public function test_kategori_tidak_dikenal_diabaikan(): void
    {
        $honor = $this->item(0);

        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->call('ubahKategoriItem', $honor['id'], 'rahasia')
            ->assertNotDispatched('notify');

        $this->assertSame('jasa', $this->item(0)['kategori']);
    }

    public function test_pilihan_kategori_di_kartu_mobile_tersedia(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->assertSeeHtml('wire:change="ubahKategoriItem(');
    }

    public function test_tanpa_hak_ubah_tidak_ada_seret_dan_kelompok_kosong(): void
    {
        $repo = app(KegiatanRepository::class);
        $repo->simpan(['status' => 'disetujui'] + $repo->temukan(1), 1);

        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->set('kelompok', 'kategori')
            ->assertDontSeeHtml('wire:sort:group')
            ->assertDontSee('Seret nomor item')
            ->call('ubahKategoriItem', $this->item(0)['id'], 'bahan')
            ->assertForbidden();
    }
}
