<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Klasifikasi;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

class PilihLeadingTest extends TestCase
{
    public function test_akun_leading_hanya_melihat_leading_miliknya(): void
    {
        Livewire::test(Klasifikasi::class, ['kegiatan' => 1])
            ->assertSeeHtml('data-leading="1.6"')
            ->assertDontSeeHtml('data-leading="1.1"')
            ->assertSee('Penanda tangan: Ketua Tim Penjamin Mutu')
            ->assertSee('Akun Anda terikat ke leading ini.');
    }

    public function test_admin_melihat_semua_leading_dan_bisa_memilih(): void
    {
        session(['stub_role' => 'admin']);

        Livewire::test(Klasifikasi::class, ['kegiatan' => 1])
            ->assertSeeHtml('data-leading="1.1"')
            ->assertSeeHtml('data-leading="1.8"')
            ->assertDontSee('Akun Anda terikat ke leading ini.')
            ->set('form.leading', '1.1')
            ->assertHasNoErrors('form.leading')
            ->assertSee('Wakil Kepala Bidang Kurikulum')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame('1.1', app(KegiatanRepository::class)->temukan(1)['leading']);
    }

    public function test_akun_leading_tidak_bisa_memilih_leading_lain(): void
    {
        Livewire::test(Klasifikasi::class, ['kegiatan' => 1])
            ->set('form.leading', '1.2')
            ->assertHasErrors('form.leading');
    }

    public function test_leading_tersimpan_milik_orang_lain_diberi_tahu(): void
    {
        $repo = app(KegiatanRepository::class);
        $repo->simpan(['leading' => '1.3'] + $repo->temukan(1), 1);

        Livewire::test(Klasifikasi::class, ['kegiatan' => 1])
            ->assertSee('bukan milik akun Anda');
    }

    public function test_komponen_tanpa_opsi_memberi_arahan(): void
    {
        $html = Blade::render('<x-kode.pilih-leading :opsi="[]" />');

        $this->assertStringContainsString('belum ditautkan ke leading', $html);
    }
}
