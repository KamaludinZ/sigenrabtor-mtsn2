<?php

namespace Tests\Feature;

use App\Livewire\Kegiatan\TerminPembayaran;
use App\Support\Format;
use Livewire\Livewire;
use Tests\TestCase;

class TanggalIndonesiaTest extends TestCase
{
    public function test_tanggal_panjang_server_sama_dengan_kembaran_klien(): void
    {
        // Berkas yang sama dipakai tests/e2e/format.spec.js untuk tanggalPanjang()/akhirPekan() di resources/js/format.js.
        $kasus = json_decode(file_get_contents(base_path('tests/fixtures/tanggal.json')), true);
        $this->assertNotEmpty($kasus);

        foreach ($kasus as $k) {
            $this->assertSame($k['panjang'] === '' ? null : $k['panjang'], Format::tanggalPanjang($k['iso']), $k['iso']);
            $this->assertSame($k['akhirPekan'], Format::akhirPekan($k['iso']), $k['iso']);
        }

        $this->assertNull(Format::tanggalPanjang(null));
        $this->assertNull(Format::tanggalPanjang('17/06/2026'), 'Hanya format ISO dari input tanggal.');
    }

    public function test_tanggal_termin_terbaca_dan_peringatan_akhir_pekan(): void
    {
        Livewire::test(TerminPembayaran::class, ['kegiatanId' => 1, 'sumber' => 'Komite'])
            ->assertSee('Rabu, 17 Juni 2026')
            ->assertSee('Jumat, 19 Juni 2026')
            ->assertSeeHtml('x-text="$tanggalPanjang(')
            ->assertDontSee('Jatuh pada hari Minggu')
            ->set('baris.1.tanggal', '2026-06-21')
            ->assertSee('Minggu, 21 Juni 2026')
            ->assertSee('Jatuh pada hari Minggu — pastikan pencairan bisa dilakukan.');
    }
}
