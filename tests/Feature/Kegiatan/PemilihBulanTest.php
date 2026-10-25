<?php

namespace Tests\Feature\Kegiatan;

use App\Livewire\Kegiatan\Form;
use Livewire\Livewire;
use Tests\TestCase;

class PemilihBulanTest extends TestCase
{
    public function test_pilihan_dari_klien_dirapikan_tanpa_mengubah_yang_sudah_rapi(): void
    {
        Livewire::test(Form::class)
            ->set('form.bulan_pelaksanaan', ['9', 7, 7, '8'])
            ->assertSet('form.bulan_pelaksanaan', [7, 8, 9])
            ->assertHasNoErrors('form.bulan_pelaksanaan')
            ->assertSee('Tercetak: Juli – September 2026')
            ->set('form.bulan_pelaksanaan', [3, 4])
            ->assertSet('form.bulan_pelaksanaan', [3, 4])
            // Nilai bukan angka tidak "dibetulkan" diam-diam; tertolak saat disimpan.
            ->set('form.nama_kegiatan', 'Kegiatan Bulan')
            ->set('form.bulan_pelaksanaan', ['x'])
            ->assertSet('form.bulan_pelaksanaan', ['x'])
            ->call('saveDraft')
            ->assertHasErrors('form.bulan_pelaksanaan.0');
    }

    public function test_bulan_kegiatan_berlangsung_ditandai(): void
    {
        $komponen = Livewire::test(Form::class)
            ->assertDontSeeHtml('data-testid="keterangan-berlangsung"')
            ->set('form.tanggal_mulai', '2026-07-30')
            ->set('form.lama_hari', 4)
            ->assertSet('form.bulan_pelaksanaan', [7, 8])
            ->assertSeeHtml('data-testid="keterangan-berlangsung"')
            ->assertSeeHtml('aria-label="Juli 2026, kegiatan berlangsung"')
            ->assertSeeHtml('aria-label="Agustus 2026, kegiatan berlangsung"')
            ->assertSeeHtml('aria-label="September 2026"');

        preg_match_all('/data-bulan="(\d+)"\s+data-berlangsung/', $komponen->html(), $cocok);
        $this->assertSame(['7', '8'], $cocok[1]);
    }

    public function test_tombol_kosongkan_hanya_saat_ada_pilihan(): void
    {
        Livewire::test(Form::class)
            ->assertDontSeeHtml('x-on:click="kosongkan()"')
            ->set('form.bulan_pelaksanaan', [6])
            ->assertSeeHtml('x-on:click="kosongkan()"')
            ->assertSeeHtml('data-bulan="6"')
            ->set('form.bulan_pelaksanaan', [])
            ->assertDontSeeHtml('x-on:click="kosongkan()"')
            ->assertSee('Shift + klik untuk rentang');
    }

    public function test_pilihan_bulan_terkunci_di_luar_draf(): void
    {
        Livewire::test(Form::class, ['kegiatan' => 1])
            ->call('ubahStatus', 'ajukan')
            ->set('form.bulan_pelaksanaan', [1, 2])
            ->assertForbidden();
    }
}
