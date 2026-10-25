<?php

namespace Tests\Feature\Rab;

use App\Enums\SumberDana;
use App\Livewire\Kegiatan\Form;
use App\Livewire\Kegiatan\Rab;
use App\Support\Kegiatan\KalimatKak;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\StatusPagu;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

class LabelSumberPaguTest extends TestCase
{
    public function test_enum_sumber_dana_menjadi_satu_sumber_label(): void
    {
        $this->assertSame(AnggaranKegiatan::URUTAN_SUMBER, SumberDana::urutan());
        $this->assertSame(['dipa', 'komite'], array_map(fn (SumberDana $s) => $s->kunci(), SumberDana::cases()));
        $this->assertSame('RAB Komite', SumberDana::Komite->labelRab());
        $this->assertSame('KOMITE MTsN 2 Kota Malang Tahun 2026', SumberDana::Komite->sebutanDokumen('MTsN 2 Kota Malang', 2026));
        $this->assertSame(SumberDana::Dipa->sebutanDokumen('MTsN 2 Kota Malang', 2026), KalimatKak::sumberDana('DIPA', 'MTsN 2 Kota Malang', 2026));
        $this->assertSame('BOS MTsN 2 Kota Malang Tahun 2026', KalimatKak::sumberDana('BOS', 'MTsN 2 Kota Malang', 2026), 'Sumber di luar enum tetap tersebut.');
        $this->assertNotSame(SumberDana::Dipa->warna(), SumberDana::Komite->warna());
    }

    public function test_status_pagu_teks_ikon_dan_nada_seragam(): void
    {
        $kasus = [
            [100, null, 'tanpa_pagu', 'Pagu belum diisi', 'info', 'netral'],
            [100, 100, 'sesuai', 'Sesuai pagu', 'check-circle', 'aman'],
            [600, 1_000, 'sisa', 'Sisa pagu Rp400', 'check-circle', 'aman'],
            [1_300, 1_000, 'melebihi', 'Melebihi pagu Rp300', 'alert', 'bahaya'],
        ];

        foreach ($kasus as [$total, $pagu, $status, $teks, $ikon, $nada]) {
            $hasil = StatusPagu::hitung($total, $pagu);
            $this->assertSame([$status, $teks, $ikon, $nada, $status === 'melebihi'], [$hasil->status, $hasil->teks(), $hasil->ikon(), $hasil->nada(), $hasil->melebihi()]);
        }

        $html = Blade::render('<x-rab.status-pagu :status="$s" data-testid="uji" />', ['s' => StatusPagu::hitung(1_300, 1_000)]);
        $this->assertStringContainsString('data-status-pagu="melebihi"', $html);
        $this->assertStringContainsString('text-red-600', $html);
        $this->assertStringContainsString('data-testid="uji"', $html);
    }

    public function test_lencana_sumber_berwarna_per_sumber(): void
    {
        $dipa = Blade::render('<x-rab.lencana-sumber sumber="DIPA" awalan="RAB" />');
        $this->assertStringContainsString('>RAB DIPA</span>', $dipa);
        $this->assertStringContainsString(SumberDana::Dipa->warna(), $dipa);
        $this->assertStringContainsString('data-sumber-dana="DIPA"', $dipa);
        $this->assertStringContainsString('bg-stone-100', Blade::render('<x-rab.lencana-sumber sumber="BOS" />'));
    }

    public function test_header_dan_rincian_rab_menampilkan_sumber_dan_pagu(): void
    {
        $sebutan = SumberDana::Dipa->sebutanDokumen('MTsN 2 Kota Malang', 2026);

        Livewire::test(Rab::class, ['kegiatan' => 2])
            ->assertSet('sumber', 'DIPA')
            // Header RAB dan kalimat Pembiayaan KAK menyebut sumber dana dengan kata yang sama.
            ->assertSeeInOrder(['Sumber dana', $sebutan, 'dibebankan pada dana '.$sebutan])
            ->assertSeeHtml('data-testid="pagu-rincian"')
            ->assertSeeInOrder(['Rincian belanja', 'RAB DIPA', 'Pagu Rp6.000.000', 'Sisa pagu Rp600.000'])
            ->call('pilihSumber', 'Komite')
            ->assertSeeInOrder(['Rincian belanja', 'RAB Komite', 'Pagu Rp2.500.000', 'Sesuai pagu'])
            ->assertSee('KOMITE MTsN 2 Kota Malang Tahun 2026');
    }

    public function test_kartu_dan_semua_rab_memakai_status_yang_sama(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 2])
            ->assertSeeHtml('data-sumber-dana="DIPA"')
            ->assertSeeHtml('data-sumber-dana="Komite"')
            ->set('halaman', 'semua')
            ->assertSeeHtml('data-testid="status-pagu-semua-dipa"')
            ->assertSeeInOrder(['RAB DIPA', 'Pagu Rp6.000.000', 'Sisa pagu Rp600.000', 'RAB Komite', 'Pagu Rp2.500.000', 'Sesuai pagu']);
    }

    public function test_data_kegiatan_menampilkan_label_dan_status_pagu_per_sumber(): void
    {
        Livewire::test(Form::class, ['kegiatan' => 2])
            ->assertSeeHtml('data-sumber-dana="DIPA"')
            ->assertSeeHtml('data-testid="status-pagu-form-dipa"')
            ->assertSeeInOrder(['Daftar Isian Pelaksanaan Anggaran (APBN).', 'Pagu DIPA', 'Sisa pagu Rp600.000', 'Dana komite madrasah.', 'Pagu Komite', 'Sesuai pagu'])
            ->set('form.pagu_dipa', '5.000.000')
            ->assertSee('Melebihi pagu Rp400.000');
    }
}
