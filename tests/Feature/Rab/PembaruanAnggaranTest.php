<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\Daftar;
use App\Livewire\Kegiatan\Form;
use App\Livewire\Kegiatan\Rab;
use App\Support\Rab\AnggaranKegiatan;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pembaruan status pagu real-time antar-tab (tiruan): perubahan anggaran dikabarkan ("anggaran-berubah") dan tab lain
 * menghitung ulang begitu menerima "anggaran-diperbarui" — tanpa siaran balik.
 */
class PembaruanAnggaranTest extends TestCase
{
    private function tanda(int $kegiatanId): string
    {
        return AnggaranKegiatan::tanda(AnggaranKegiatan::untuk(app(KegiatanRepository::class)->temukan($kegiatanId), sertakanTerlepas: true));
    }

    public function test_tanda_anggaran_berubah_hanya_bila_pagu_total_atau_item_berubah(): void
    {
        $awal = $this->tanda(2);
        $this->assertSame($awal, $this->tanda(2));

        app(RabRepository::class)->tambahItem(2, 'Komite', ['kode' => '', 'uraian' => 'Map', 'kategori' => 'bahan', 'volume' => 1, 'satuan' => 'buah', 'harga_satuan' => 5_000]);
        $setelahItem = $this->tanda(2);
        $this->assertNotSame($awal, $setelahItem);

        $repo = app(KegiatanRepository::class);
        $kegiatan = $repo->temukan(2);
        $kegiatan['sumber_dana'][0]['pagu'] = 5_400_000;
        $repo->simpan($kegiatan, 2);
        $this->assertNotSame($setelahItem, $this->tanda(2));
    }

    public function test_rab_mengabarkan_perubahan_isi_bukan_perpindahan_tampilan(): void
    {
        $id = app(RabRepository::class)->rincian(2, 'DIPA')[0]['id'];

        Livewire::test(Rab::class, ['kegiatan' => 2])
            ->assertNotDispatched('anggaran-berubah')
            ->call('pilihSumber', 'Komite')
            ->set('halaman', 'pagu')
            ->assertNotDispatched('anggaran-berubah')
            ->call('pilihSumber', 'DIPA')
            ->call('salinItem', $id)
            ->assertDispatched('anggaran-berubah', kegiatan: 2)
            ->call('hapusItem', $id)
            ->assertDispatched('anggaran-berubah', kegiatan: 2);
    }

    public function test_rab_menerima_pembaruan_dari_tab_lain_tanpa_siaran_balik(): void
    {
        $komponen = Livewire::test(Rab::class, ['kegiatan' => 2])->assertSee('Sisa pagu Rp600.000');

        // Tab lain menurunkan pagu DIPA menjadi Rp5.000.000.
        $repo = app(KegiatanRepository::class);
        $kegiatan = $repo->temukan(2);
        $kegiatan['sumber_dana'][0]['pagu'] = 5_000_000;
        $repo->simpan($kegiatan, 2);

        $komponen->dispatch('anggaran-diperbarui', kegiatan: 2)
            ->assertDispatched('notify', message: 'Anggaran diperbarui dari tab lain.')
            ->assertNotDispatched('anggaran-berubah')
            ->assertSee('Melebihi pagu Rp400.000')
            ->assertSeeHtml('data-testid="peringatan-pagu-dipa" data-status="melebihi"');

        $komponen->dispatch('anggaran-diperbarui', kegiatan: 1)->assertNotDispatched('notify');
    }

    public function test_data_kegiatan_mengabarkan_simpan_dan_menerima_total_rab_baru(): void
    {
        $komponen = Livewire::test(Form::class, ['kegiatan' => 2])
            ->call('saveDraft')
            ->assertDispatched('anggaran-berubah', kegiatan: 2)
            ->assertSeeInOrder(['Pagu DIPA', 'Terhadap total RAB Rp5.400.000', 'Sisa pagu Rp600.000']);

        // RAB DIPA bertambah Rp1.000.000 di tab lain.
        app(RabRepository::class)->tambahItem(2, 'DIPA', ['kode' => '', 'uraian' => 'Spanduk', 'kategori' => 'bahan', 'volume' => 1, 'satuan' => 'lembar', 'harga_satuan' => 1_000_000]);

        $komponen->dispatch('anggaran-diperbarui', kegiatan: 2)
            ->assertDispatched('notify', message: 'Total RAB diperbarui dari tab lain.')
            ->assertSeeInOrder(['Pagu DIPA', 'Terhadap total RAB Rp6.400.000', 'Melebihi pagu Rp400.000'])
            ->assertSeeHtml('wire:key="batas-pagu-dipa-6400000"');

        $komponen->dispatch('anggaran-diperbarui', kegiatan: 3)->assertNotDispatched('notify');
    }

    public function test_daftar_menghitung_ulang_saat_anggaran_berubah_di_tab_lain(): void
    {
        $komponen = Livewire::test(Daftar::class);
        $this->assertSame(26_750_000, $komponen->instance()->ringkasan['total']);

        app(RabRepository::class)->tambahItem(1, 'Komite', ['kode' => '', 'uraian' => 'Spanduk', 'kategori' => 'bahan', 'volume' => 1, 'satuan' => 'lembar', 'harga_satuan' => 250_000]);
        $komponen->dispatch('anggaran-diperbarui', kegiatan: 1)
            ->assertSee('Rp27.000.000');
        $this->assertSame(27_000_000, $komponen->instance()->ringkasan['total']);
        $this->assertSame(2, $komponen->instance()->jumlahPerKondisi['melebihi']);
    }
}
