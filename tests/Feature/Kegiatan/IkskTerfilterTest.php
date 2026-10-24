<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Klasifikasi;
use Livewire\Livewire;
use Tests\TestCase;

class IkskTerfilterTest extends TestCase
{
    private function kegiatanTanpaKode(): int
    {
        return app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Kegiatan IKSK',
            'nama_kegiatan_cover' => 'KEGIATAN IKSK',
            'leading' => '1.6',
        ]);
    }

    public function test_iksk_nonaktif_sampai_sk_dipilih_lalu_hanya_berisi_indikator_sk_itu(): void
    {
        Livewire::test(Klasifikasi::class, ['kegiatan' => $this->kegiatanTanpaKode()])
            ->assertSee('Pilih kode SK lebih dulu; daftar IKSK menyesuaikan SK.')
            ->assertSee('— Pilih SK dulu —')
            ->set('form.kode_sk', 'SK.9')
            ->assertSee('4 indikator di bawah SK.9.')
            ->assertSee('IKSK 9.1')
            ->assertDontSee('IKSK 10.6');
    }

    public function test_ganti_sk_mengosongkan_iksk_yang_tidak_cocok(): void
    {
        Livewire::test(Klasifikasi::class, ['kegiatan' => 1])
            ->assertSet('form.kode_iksk', 'IKSK 10.6')
            ->set('form.kode_sk', 'SK.9')
            ->assertSet('form.kode_iksk', '')
            ->assertDontSee('uraian-iksk');
    }

    public function test_sk_dengan_satu_iksk_memilih_otomatis(): void
    {
        Livewire::test(Klasifikasi::class, ['kegiatan' => $this->kegiatanTanpaKode()])
            ->set('form.kode_sk', 'SK.12')
            ->assertSet('form.kode_iksk', 'IKSK 12.1')
            ->assertDispatched('notify', message: 'IKSK IKSK 12.1 dipilih otomatis — satu-satunya indikator SK.12.')
            ->assertSee('Nilai Kinerja Anggaran');
    }

    public function test_iksk_dari_sk_lain_ditolak(): void
    {
        Livewire::test(Klasifikasi::class, ['kegiatan' => $this->kegiatanTanpaKode()])
            ->set('form.kode_sk', 'SK.9')
            ->set('form.kode_iksk', 'IKSK 10.6')
            ->assertHasErrors('form.kode_iksk')
            ->assertSee('IKSK 10.6 bukan indikator dari SK.9.');
    }
}
