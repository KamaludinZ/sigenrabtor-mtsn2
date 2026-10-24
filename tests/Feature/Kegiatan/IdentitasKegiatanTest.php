<?php

namespace Tests\Feature\Kegiatan;

use App\Livewire\Kegiatan\Form;
use Livewire\Livewire;
use Tests\TestCase;

class IdentitasKegiatanTest extends TestCase
{
    public function test_nama_cover_otomatis_kapital_mengikuti_nama_kegiatan(): void
    {
        Livewire::test(Form::class)
            ->set('form.nama_kegiatan', 'Workshop Penyusunan Kurikulum Madrasah')
            ->assertSet('form.nama_kegiatan_cover', 'WORKSHOP PENYUSUNAN KURIKULUM MADRASAH')
            ->assertSet('form.cover_manual', false);
    }

    public function test_nama_cover_manual_tidak_ditimpa_lalu_bisa_disamakan_lagi(): void
    {
        Livewire::test(Form::class)
            ->set('form.nama_kegiatan', 'Workshop Kurikulum')
            ->set('form.nama_kegiatan_cover', 'workshop kurikulum madrasah 2026')
            ->assertSet('form.nama_kegiatan_cover', 'WORKSHOP KURIKULUM MADRASAH 2026')
            ->assertSet('form.cover_manual', true)
            ->set('form.nama_kegiatan', 'Workshop Kurikulum Baru')
            ->assertSet('form.nama_kegiatan_cover', 'WORKSHOP KURIKULUM MADRASAH 2026')
            ->call('resetNamaCover')
            ->assertSet('form.cover_manual', false)
            ->assertSet('form.nama_kegiatan_cover', 'WORKSHOP KURIKULUM BARU');
    }

    public function test_simpan_draf_mewajibkan_nama_kegiatan(): void
    {
        Livewire::test(Form::class)
            ->call('saveDraft')
            ->assertHasErrors(['form.nama_kegiatan' => 'required'])
            ->assertDispatched('notify', type: 'error');
    }

    public function test_sasaran_terpotong_ditolak(): void
    {
        Livewire::test(Form::class)
            ->set('form.sasaran', 'dokumen perencanaan sesuai rencana riil yang dibutuhkan oleh')
            ->assertHasErrors('form.sasaran');
    }

    public function test_tanggal_pelaporan_tidak_boleh_sebelum_tanggal_dokumen(): void
    {
        Livewire::test(Form::class)
            ->set('form.tanggal_dokumen', '2026-07-01')
            ->set('form.tanggal_laporan', '2026-06-30')
            ->assertHasErrors('form.tanggal_laporan')
            ->set('form.tanggal_laporan', '2026-07-30')
            ->assertHasNoErrors('form.tanggal_laporan');
    }

    public function test_peringatan_awalan_yang_akan_tercetak_dobel(): void
    {
        Livewire::test(Form::class)
            ->set('form.nama_kegiatan', 'Kegiatan MGMP')
            ->set('form.tujuan', 'bertujuan meningkatkan kompetensi guru')
            ->assertSee('awalan “Kegiatan” akan tercetak dobel')
            ->assertSee('hapus kata “bertujuan” di awal');
    }

    public function test_draf_lengkap_tersimpan_tanpa_error(): void
    {
        Livewire::test(Form::class)
            ->set('form.nama_kegiatan', 'Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran')
            ->set('form.tujuan', 'menghasilkan dokumen kurikulum yang terlegitimasi.')
            ->set('form.sasaran', 'dokumen perencanaan yang handal dan akurat.')
            ->set('form.tanggal_dokumen', '2026-01-03')
            ->set('form.tanggal_laporan', '2026-07-30')
            ->call('saveDraft')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Draf kegiatan tersimpan (mode data tiruan).')
            ->assertSee('Tercetak: 3 Januari 2026');
    }
}
