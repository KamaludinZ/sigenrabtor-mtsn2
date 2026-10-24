<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Klasifikasi;
use Livewire\Livewire;
use Tests\TestCase;

class HalamanKlasifikasiTest extends TestCase
{
    private function kegiatanBaru(): int
    {
        return app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Kegiatan Tanpa Kode',
            'tujuan' => 'tujuan yang tidak boleh hilang.',
            'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]],
        ]);
    }

    public function test_halaman_menampilkan_lima_kartu_dan_pratinjau_header(): void
    {
        $this->get(route('kegiatan.kode', ['kegiatan' => 1]))
            ->assertOk()
            ->assertSee('Kode &amp; Klasifikasi', false)
            ->assertSee('Leading penanggung jawab')
            ->assertSee('Program RKTM')
            ->assertSee('Perjanjian Kinerja')
            ->assertSee('Evaluasi Diri Madrasah (EDM)')
            ->assertSee('5 Budaya Mutu')
            ->assertSee('Tercetak di header RAB')
            ->assertSee('IKSK 10.6')
            ->assertSee('PENGEMBANGAN STANDAR PENGELOLAAN');
    }

    public function test_tab_kode_tersedia_di_ruang_kerja_dan_ringkasan_di_data_kegiatan(): void
    {
        $this->get(route('kegiatan.edit', ['kegiatan' => 1]))
            ->assertSee(route('kegiatan.kode', ['kegiatan' => 1]), false)
            ->assertSee('Atur kode &amp; klasifikasi', false)
            ->assertSeeInOrder(['6.17.12', 'Kegiatan Workshop penyusunan Kurikulum Madrasah']);
    }

    public function test_pilih_dan_simpan_kode_tanpa_menyentuh_isian_lain(): void
    {
        $id = $this->kegiatanBaru();

        Livewire::test(Klasifikasi::class, ['kegiatan' => $id])
            ->assertSee('belum dipilih')
            ->set('form.leading', '1.6')
            ->set('form.kode_rktm', '1.1.7')
            ->set('form.kode_sk', 'SK.10')
            ->set('form.kode_iksk', 'IKSK 10.6')
            ->set('form.kode_edm', 'C1')
            ->set('form.budaya_mutu', '2')
            ->assertSet('adaPerubahan', true)
            ->assertSee('Workshop KTSP')
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertSet('adaPerubahan', false)
            ->assertDispatched('notify', message: 'Kode & klasifikasi tersimpan.');

        $tersimpan = app(KegiatanRepository::class)->temukan($id);
        $this->assertSame(['1.6', '1.1.7', 'SK.10', 'IKSK 10.6', 'C1', 2], [
            $tersimpan['leading'], $tersimpan['kode_rktm'], $tersimpan['kode_sk'], $tersimpan['kode_iksk'], $tersimpan['kode_edm'], $tersimpan['budaya_mutu'],
        ]);
        $this->assertSame('tujuan yang tidak boleh hilang.', $tersimpan['tujuan']);
        $this->assertSame([['sumber' => 'DIPA', 'pagu' => 1_000_000]], $tersimpan['sumber_dana']);
    }

    public function test_simpan_dan_lanjut_ke_rab(): void
    {
        Livewire::test(Klasifikasi::class, ['kegiatan' => 1])
            ->call('simpan', true)
            ->assertRedirect(route('kegiatan.rab', ['kegiatan' => 1]));
    }

    public function test_isian_selain_kode_ditolak_di_halaman_ini(): void
    {
        Livewire::test(Klasifikasi::class, ['kegiatan' => 1])
            ->set('form.nama_kegiatan', 'Diubah lewat halaman kode')
            ->assertForbidden();
    }

    public function test_kode_tidak_valid_ditolak_saat_simpan(): void
    {
        Livewire::test(Klasifikasi::class, ['kegiatan' => $this->kegiatanBaru()])
            ->set('form.kode_edm', 'Z9')
            ->assertHasErrors('form.kode_edm')
            ->call('simpan')
            ->assertHasErrors('form.kode_edm')
            ->assertDispatched('notify', type: 'error');
    }

    public function test_kode_terkunci_saat_status_bukan_draf(): void
    {
        $repo = app(KegiatanRepository::class);
        $repo->simpan(['status' => 'diperiksa'] + $repo->temukan(1), 1);

        Livewire::test(Klasifikasi::class, ['kegiatan' => 1])
            ->assertSee('Kode terkunci karena kegiatan berstatus Diperiksa.')
            ->assertDontSee('Simpan &amp; lanjut ke RAB', false)
            ->set('form.kode_edm', 'A1')
            ->assertForbidden();
    }

    public function test_kegiatan_tidak_ada_404(): void
    {
        $this->get(route('kegiatan.kode', ['kegiatan' => 77]))->assertNotFound();
    }
}
