<?php

namespace Tests\Feature;

use Tests\TestCase;

class IdentitasAplikasiTest extends TestCase
{
    public function test_nama_dan_versi_aplikasi_tampil_di_judul_dan_sidebar(): void
    {
        $this->assertSame(['nama' => 'SIRABTOR', 'versi' => '1.0.0'], config('sigenrabtor.aplikasi'));

        $this->get(route('kegiatan.edit', ['kegiatan' => 1]))
            ->assertOk()
            ->assertSee('· SIRABTOR</title>', false)
            ->assertSee('data-testid="versi-aplikasi">v1.0.0</span>', false)
            ->assertDontSee('SIGENRABTOR');
    }

    public function test_nama_tampilan_tidak_bergantung_app_name(): void
    {
        config(['app.name' => 'Nama Lain Dari Env']);

        $this->get(route('kegiatan.create'))
            ->assertSee('SIRABTOR</title>', false)
            ->assertDontSee('Nama Lain Dari Env');
    }
}
