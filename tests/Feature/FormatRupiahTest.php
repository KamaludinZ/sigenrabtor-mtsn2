<?php

namespace Tests\Feature;

use App\Support\Format;
use App\Support\FormatRupiah;
use App\Support\Terbilang;
use Tests\TestCase;

class FormatRupiahTest extends TestCase
{
    public function test_akhiran_sen_dan_format_desimal(): void
    {
        $this->assertSame(
            [10_000_000, 7_680_000, 6_000_000, 1_000, 1_500_000, null],
            array_map(Format::parseRupiah(...), ['Rp10.000.000,-', '7.680.000,00', 'Rp 6.000.000,0', '1.000,', 'Rp 1.500.000', 'Rp']),
        );
        $this->assertSame('Rp7.680.000,00', Format::rupiahDesimal(7_680_000));
        $this->assertSame('-Rp600.000,00', Format::rupiahDesimal(-600_000));
    }

    public function test_endpoint_menjawab_semua_gaya_baku(): void
    {
        $this->getJson(route('format.rupiah', ['nilai' => 'Rp10.000.000,-']))->assertOk()->assertExactJson([
            'masukan' => 'Rp10.000.000,-',
            'nilai' => 10_000_000,
            'sah' => true,
            'galat' => null,
            'angka' => '10.000.000',
            'rupiah' => 'Rp10.000.000',
            'rupiah_dokumen' => 'Rp10.000.000,-',
            'rupiah_desimal' => 'Rp10.000.000,00',
            'terbilang' => 'Sepuluh Juta Rupiah',
            'terbilang_kata' => 'sepuluh juta',
            'terbilang_rupiah' => 'Sepuluh Juta Rupiah',
            'dengan_angka' => 'Rp10.000.000,- (Sepuluh Juta Rupiah)',
        ]);
        $this->getJson(route('format.rupiah', ['nilai' => '0']))->assertOk()->assertJson(['nilai' => 0, 'terbilang' => null, 'terbilang_rupiah' => 'Nol Rupiah']);
        $this->getJson(route('format.rupiah', ['nilai' => '-600.000']))->assertOk()->assertJson(['nilai' => -600_000, 'rupiah' => '-Rp600.000', 'terbilang_kata' => 'minus enam ratus ribu']);

        $this->getJson(route('format.rupiah', ['nilai' => 'abc']))->assertStatus(422)->assertJson(['sah' => false, 'galat' => FormatRupiah::GALAT_KOSONG]);
        $this->getJson(route('format.rupiah'))->assertStatus(422)->assertJsonPath('galat', FormatRupiah::GALAT_KOSONG);
        $this->getJson(route('format.rupiah', ['nilai' => (string) (Terbilang::MAKSIMUM + 1)]))->assertStatus(422)->assertJson(['galat' => FormatRupiah::GALAT_BESAR]);
        $this->getJson(route('format.rupiah', ['nilai' => '99999999999999999999']))->assertStatus(422)->assertJson(['galat' => FormatRupiah::GALAT_BESAR]);
    }
}
