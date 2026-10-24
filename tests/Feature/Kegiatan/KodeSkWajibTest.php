<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Form;
use App\Livewire\Kegiatan\Klasifikasi;
use Livewire\Livewire;
use Tests\TestCase;

class KodeSkWajibTest extends TestCase
{
    private function kegiatanTanpaSk(): int
    {
        return app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Kegiatan Tanpa SK',
            'nama_kegiatan_cover' => 'KEGIATAN TANPA SK',
            'leading' => '1.6',
            'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]],
        ]);
    }

    public function test_halaman_kode_menandai_sk_wajib_dan_menampilkan_combobox(): void
    {
        Livewire::test(Klasifikasi::class, ['kegiatan' => $this->kegiatanTanpaSk()])
            ->assertSee('Wajib: Sasaran Kegiatan Perjanjian Kinerja')
            ->assertSeeHtml('id="kode_sk"')
            ->assertSeeHtml('role="combobox"')
            ->assertSee('indikator IKSK');
    }

    public function test_simpan_kode_tanpa_sk_ditolak(): void
    {
        $id = $this->kegiatanTanpaSk();

        Livewire::test(Klasifikasi::class, ['kegiatan' => $id])
            ->set('form.kode_edm', 'C1')
            ->call('simpan')
            ->assertHasErrors('form.kode_sk')
            ->assertSee('Kode SK wajib dipilih')
            ->assertDispatched('notify', type: 'error');

        $this->assertArrayNotHasKey('kode_edm', array_filter(app(KegiatanRepository::class)->temukan($id)));
    }

    public function test_simpan_kode_dengan_sk_berhasil_dan_uraian_tampil(): void
    {
        Livewire::test(Klasifikasi::class, ['kegiatan' => $this->kegiatanTanpaSk()])
            ->set('form.kode_sk', 'SK.12')
            ->assertSee('Meningkatnya kualitas perencanaan dan anggaran')
            ->call('simpan')
            ->assertHasNoErrors();
    }

    public function test_draf_boleh_tanpa_sk_tetapi_pengajuan_mewajibkan_sk(): void
    {
        $id = $this->kegiatanTanpaSk();

        Livewire::test(Form::class, ['kegiatan' => $id])
            ->call('saveDraft')
            ->assertHasNoErrors()
            ->call('ubahStatus', 'ajukan')
            ->assertHasErrors('form.kode_sk')
            ->assertSee('Atur di halaman Kode &amp; Klasifikasi', false)
            ->assertSet('status', 'draf');
    }
}
