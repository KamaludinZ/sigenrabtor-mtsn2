<?php

namespace Tests\Feature\Rab;

use App\Livewire\Kegiatan\Rab;
use App\Livewire\Kegiatan\TerminPembayaran;
use App\Support\Dokumen\PemeriksaTerbilang;
use App\Support\Format;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Angka yang belum divalidasi (masih diketik, atau tertulis di teks bebas) bisa melebihi Terbilang::MAKSIMUM; layar
 * tidak boleh gagal karenanya — terbilang dikosongkan seperti di klien, dan pemeriksa dokumen melaporkannya.
 */
class TerbilangDiLuarBatasTest extends TestCase
{
    public function test_isian_rupiah_dengan_angka_terlalu_besar_tetap_tampil(): void
    {
        Livewire::test(TerminPembayaran::class, ['kegiatanId' => 1, 'sumber' => 'Komite'])
            ->call('tambahTermin')
            ->set('baris.0.nominal', '99.999.999.999.999.999.999')
            ->assertOk()
            ->assertDontSee('Sembilan Ratus Sembilan Puluh Sembilan Triliun');
    }

    public function test_form_item_dengan_angka_terlalu_besar_tetap_tampil_dan_ditolak(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->call('bukaFormItem')
            ->set('item.uraian', 'Konsumsi peserta')
            ->set('item.volume', '99999999999999')
            ->set('item.harga_satuan', '999.999.999.999.999.999.999')
            ->assertOk()
            ->call('simpanItem')
            ->assertHasErrors(['item.volume', 'item.harga_satuan']);
    }

    public function test_baca_rupiah_dibatasi(): void
    {
        $this->assertSame([999_999_999_999_999, Format::MAKS_BACA_RUPIAH, Format::MAKS_BACA_RUPIAH, 0, null], [
            Format::parseRupiah('999.999.999.999.999'),
            Format::parseRupiah('1.000.000.000.000.000'),
            Format::parseRupiah(str_repeat('9', 40)),
            Format::parseRupiah('0000000000000000000000'),
            Format::parseRupiah('Rp'),
        ]);
    }

    public function test_pemeriksa_terbilang_melaporkan_nominal_di_luar_batas(): void
    {
        $temuan = PemeriksaTerbilang::periksa('Biaya sebesar Rp1.000.000.000.000.000,- (Satu Biliar Rupiah) dibebankan.');

        $this->assertSame([['nominal' => 1_000_000_000_000_000, 'tertulis' => 'Satu Biliar Rupiah', 'seharusnya' => '—', 'masalah' => 'Nominal terlalu besar untuk diterbilangkan (maksimal 999 triliun).']], $temuan);
    }
}
