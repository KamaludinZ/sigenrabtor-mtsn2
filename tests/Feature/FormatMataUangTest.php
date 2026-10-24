<?php

namespace Tests\Feature;

use App\Support\Format;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class FormatMataUangTest extends TestCase
{
    public function test_rupiah_baku_dan_negatif(): void
    {
        $this->assertSame('Rp0', Format::rupiah(0));
        $this->assertSame('Rp600.000', Format::rupiah(600_000));
        $this->assertSame('-Rp600.000', Format::rupiah(-600_000));
        $this->assertSame('-1.500', Format::angka(-1_500));
    }

    public function test_gaya_dokumen_madrasah(): void
    {
        $this->assertSame('Rp10.000.000,-', Format::rupiahDokumen(10_000_000));
    }

    public function test_komponen_uang(): void
    {
        $this->assertSame(
            '<span class="whitespace-nowrap tabular-nums">Rp1.250.000</span>',
            trim(Blade::render('<x-ui.uang :nilai="1250000" />')),
        );
        $this->assertStringContainsString('>1.250.000<', Blade::render('<x-ui.uang :nilai="1250000" polos />'));
        $this->assertStringContainsString('text-red-600', Blade::render('<x-ui.uang :nilai="-5" tanda />'));
        $this->assertStringContainsString('-Rp5', Blade::render('<x-ui.uang :nilai="-5" tanda />'));
    }

    public function test_tabel_rab_memakai_judul_kolom_rupiah_dan_header_gaya_dokumen(): void
    {
        $this->get(route('kegiatan.rab', ['kegiatan' => 1]))
            ->assertSee('Harga satuan (Rp)')
            ->assertSee('Jumlah (Rp)')
            ->assertSee('Rp10.000.000,-');
    }
}
