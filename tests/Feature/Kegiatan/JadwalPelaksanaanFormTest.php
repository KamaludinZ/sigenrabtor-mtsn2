<?php

namespace Tests\Feature\Kegiatan;

use App\Livewire\Kegiatan\Form;
use Livewire\Livewire;
use Tests\TestCase;

class JadwalPelaksanaanFormTest extends TestCase
{
    public function test_tanggal_selesai_bulan_dan_laporan_dihitung_otomatis(): void
    {
        Livewire::test(Form::class)
            ->set('form.tanggal_mulai', '2026-07-14')
            ->set('form.lama_hari', '3')
            ->assertSee('Kamis, 16 Juli 2026')
            ->assertSet('form.bulan_pelaksanaan', [7])
            ->assertSet('form.tanggal_laporan', '2026-07-26')
            ->assertSee('akan dilaksanakan selama 3 hari, 14 – 16 Juli 2026')
            ->assertHasNoErrors();
    }

    public function test_hari_kerja_menggeser_tanggal_selesai_dan_laporan(): void
    {
        Livewire::test(Form::class)
            ->set('form.tanggal_mulai', '2026-07-17')
            ->set('form.lama_hari', 3)
            ->set('form.hari_kerja', true)
            ->assertSee('Selasa, 21 Juli 2026')
            ->assertSet('form.tanggal_laporan', '2026-07-31');
    }

    public function test_tanggal_mulai_harus_di_tahun_anggaran_aktif(): void
    {
        Livewire::test(Form::class)
            ->set('form.tanggal_mulai', '2025-12-20')
            ->assertHasErrors('form.tanggal_mulai')
            ->assertSee('Tanggal mulai harus di Tahun Anggaran 2026.');
    }

    public function test_kegiatan_yang_melewati_akhir_tahun_ditolak(): void
    {
        Livewire::test(Form::class)
            ->set('form.tanggal_mulai', '2026-12-28')
            ->set('form.lama_hari', 7)
            ->assertHasErrors('form.lama_hari')
            ->assertSee('melewati akhir Tahun Anggaran 2026');
    }

    public function test_laporan_manual_tidak_ditimpa_dan_bisa_kembali_otomatis(): void
    {
        Livewire::test(Form::class)
            ->set('form.tanggal_mulai', '2026-07-14')
            ->set('form.lama_hari', 1)
            ->assertSet('form.tanggal_laporan', '2026-07-24')
            ->set('form.tanggal_laporan', '2026-07-31')
            ->assertSet('form.laporan_otomatis', false)
            ->set('form.lama_hari', 2)
            ->assertSet('form.tanggal_laporan', '2026-07-31')
            ->set('form.tanggal_laporan', '')
            ->assertSet('form.laporan_otomatis', true)
            ->assertSet('form.tanggal_laporan', '2026-07-25');
    }

    public function test_laporan_sebelum_kegiatan_selesai_ditolak(): void
    {
        Livewire::test(Form::class)
            ->set('form.tanggal_dokumen', '2026-07-01')
            ->set('form.tanggal_mulai', '2026-07-14')
            ->set('form.lama_hari', 3)
            ->set('form.tanggal_laporan', '2026-07-15')
            ->assertHasErrors('form.tanggal_laporan')
            ->assertSee('tidak boleh sebelum kegiatan selesai');
    }

    public function test_bulan_bisa_dipilih_manual_lalu_disesuaikan_dengan_tanggal(): void
    {
        Livewire::test(Form::class)
            ->call('toggleBulan', 3)
            ->call('toggleBulan', 6)
            ->call('toggleBulan', 3)
            ->assertSet('form.bulan_pelaksanaan', [6])
            ->set('form.tanggal_mulai', '2026-07-30')
            ->set('form.lama_hari', 4)
            ->assertSet('form.bulan_pelaksanaan', [6])
            ->assertSee('Sesuaikan dengan tanggal')
            ->call('sesuaikanBulan')
            ->assertSet('form.bulan_pelaksanaan', [7, 8])
            ->assertSee('Tercetak: Juli – Agustus 2026');
    }

    public function test_lama_kegiatan_harus_angka_positif(): void
    {
        Livewire::test(Form::class)
            ->set('form.lama_hari', 0)
            ->assertHasErrors(['form.lama_hari' => 'min'])
            ->set('form.lama_hari', 'abc')
            ->assertHasErrors(['form.lama_hari' => 'integer']);
    }
}
