<?php

namespace Tests\Feature\Kegiatan;

use App\Livewire\Kegiatan\Form;
use Livewire\Livewire;
use Tests\TestCase;

class InputUraianTest extends TestCase
{
    public function test_komponen_uraian_dirender_dengan_penghitung_dan_tombol_rapikan(): void
    {
        $this->get(route('kegiatan.create'))
            ->assertOk()
            ->assertSee('id="tujuan"', false)
            ->assertSee('wire:model.live.blur="form.tujuan"', false)
            ->assertSee('maxlength="2000"', false)
            ->assertSee('maxlength="5000"', false)
            ->assertSee('Rapikan spasi');
    }

    public function test_uraian_dirapikan_di_server_saat_disinkronkan(): void
    {
        Livewire::test(Form::class)
            ->set('form.tujuan', "  menghasilkan   dokumen\n\n\n\nkurikulum  yang terlegitimasi.  ")
            ->assertSet('form.tujuan', "menghasilkan dokumen\n\nkurikulum yang terlegitimasi.");
    }

    public function test_nama_kegiatan_tidak_ikut_dirapikan_sebagai_uraian(): void
    {
        Livewire::test(Form::class)
            ->set('form.nama_kegiatan', 'Workshop  Kurikulum')
            ->assertSet('form.nama_kegiatan', 'Workshop  Kurikulum');
    }

    public function test_uraian_melebihi_batas_ditolak(): void
    {
        Livewire::test(Form::class)
            ->set('form.sasaran', str_repeat('a', 2001))
            ->assertHasErrors(['form.sasaran' => 'max']);
    }
}
