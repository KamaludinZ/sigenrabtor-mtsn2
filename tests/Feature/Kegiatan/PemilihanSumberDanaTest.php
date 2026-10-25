<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Form;
use Livewire\Livewire;
use Tests\TestCase;

class PemilihanSumberDanaTest extends TestCase
{
    public function test_ringkasan_pilihan_mengikuti_centang(): void
    {
        Livewire::test(Form::class)
            ->assertSeeHtml('data-testid="ringkasan-pilihan-sumber" data-jumlah="0"')
            ->assertSee('Belum ada sumber dana dipilih')
            ->assertSeeHtml('data-testid="sumber-dipa" data-dipilih="tidak"')
            ->assertSee('Centang DIPA bila kegiatan ini juga dibiayai DIPA.')
            ->set('form.dana_dipa', true)
            ->assertSee('Dibiayai DIPA')
            ->assertSee('Akan dibuat 1 dokumen KAK/TOR & RAB DIPA.')
            ->assertSeeHtml('data-testid="sumber-dipa" data-dipilih="ya"')
            ->set('form.dana_komite', true)
            ->assertSeeHtml('data-jumlah="2"')
            ->assertSee('Dibiayai DIPA dan Komite')
            ->assertSee('Akan dibuat 2 dokumen KAK/TOR & RAB terpisah');
    }

    public function test_kartu_memuat_ringkasan_dan_tautan_rab(): void
    {
        Livewire::test(Form::class, ['kegiatan' => 2])
            ->assertSeeInOrder(['RAB DIPA', 'RAB tersusun (6 item)', 'Rp5.400.000', 'Lima Juta Empat Ratus Ribu Rupiah', 'Sisa pagu Rp600.000'])
            ->assertSeeHtml('href="'.route('kegiatan.rab', ['kegiatan' => 2, 'sumber' => 'DIPA']).'"')
            ->assertSee('Buka RAB Komite →');

        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Belum ada RAB', 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 1_000_000]]]);
        Livewire::test(Form::class, ['kegiatan' => $id])
            ->assertSee('RAB belum berisi rincian')
            ->assertSee('Susun RAB Komite →')
            ->assertDontSeeHtml('data-testid="rincian-rab-komite"');
    }

    public function test_melepas_sumber_dengan_rab_berisi_diberi_peringatan(): void
    {
        Livewire::test(Form::class, ['kegiatan' => 2])
            ->assertDontSeeHtml('data-testid="rab-terlepas-dipa"')
            ->set('form.dana_dipa', false)
            ->assertSeeHtml('data-testid="rab-terlepas-dipa"')
            ->assertSee('RAB DIPA tersimpan (6 item, Rp5.400.000) tetapi tidak ikut dicetak selama DIPA tidak dipilih.')
            ->assertDontSeeHtml('data-testid="rekap-rab-dipa"')
            ->set('form.dana_dipa', true)
            ->assertDontSeeHtml('data-testid="rab-terlepas-dipa"')
            ->assertSeeHtml('data-testid="rekap-rab-dipa"');

        // Kegiatan baru (belum tersimpan) tidak punya RAB untuk dilepas.
        Livewire::test(Form::class)->set('form.dana_komite', true)->set('form.dana_komite', false)->assertDontSee('tidak ikut dicetak');
    }
}
