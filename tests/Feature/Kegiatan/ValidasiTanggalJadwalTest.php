<?php

namespace Tests\Feature\Kegiatan;

use App\Livewire\Kegiatan\Form;
use Livewire\Livewire;
use Tests\TestCase;

class ValidasiTanggalJadwalTest extends TestCase
{
    public function test_tanggal_dokumen_tidak_boleh_setelah_kegiatan_dimulai(): void
    {
        Livewire::test(Form::class)
            ->set('form.tanggal_mulai', '2026-07-14')
            ->set('form.tanggal_dokumen', '2026-07-20')
            ->assertHasErrors('form.tanggal_dokumen')
            ->assertSee('harus sebelum atau sama dengan tanggal mulai kegiatan (14 Juli 2026)')
            ->set('form.tanggal_dokumen', '2026-07-14')
            ->assertHasNoErrors('form.tanggal_dokumen');
    }

    public function test_mengubah_tanggal_mulai_memvalidasi_ulang_tanggal_dokumen(): void
    {
        Livewire::test(Form::class)
            ->set('form.tanggal_dokumen', '2026-03-01')
            ->set('form.tanggal_mulai', '2026-02-10')
            ->assertHasErrors('form.tanggal_dokumen')
            ->set('form.tanggal_mulai', '2026-03-10')
            ->assertHasNoErrors('form.tanggal_dokumen');
    }

    public function test_tanggal_dokumen_boleh_desember_tahun_sebelumnya_tetapi_tidak_lebih_awal(): void
    {
        Livewire::test(Form::class)
            ->set('form.tanggal_dokumen', '2025-12-23')
            ->assertHasNoErrors('form.tanggal_dokumen')
            ->set('form.tanggal_dokumen', '2024-12-31')
            ->assertHasErrors('form.tanggal_dokumen')
            ->assertSee('Tanggal dokumen harus antara 1 Januari 2025 dan 31 Desember 2026.')
            ->set('form.tanggal_dokumen', '2027-01-02')
            ->assertHasErrors('form.tanggal_dokumen');
    }

    public function test_bulan_pelaksanaan_wajib_mencakup_bulan_kegiatan(): void
    {
        Livewire::test(Form::class)
            ->call('toggleBulan', 6)
            ->set('form.tanggal_mulai', '2026-07-30')
            ->set('form.lama_hari', 4)
            ->assertHasErrors('form.bulan_pelaksanaan')
            ->assertSee('belum mencakup bulan kegiatan berlangsung: Juli, Agustus')
            ->call('toggleBulan', 7)
            ->assertHasErrors('form.bulan_pelaksanaan')
            ->call('toggleBulan', 8)
            ->assertHasNoErrors('form.bulan_pelaksanaan')
            ->assertSet('form.bulan_pelaksanaan', [6, 7, 8]);
    }

    public function test_bulan_kosong_tidak_divalidasi_terhadap_tanggal(): void
    {
        Livewire::test(Form::class)
            ->call('toggleBulan', 7)
            ->call('toggleBulan', 7)
            ->assertHasNoErrors('form.bulan_pelaksanaan');
    }

    public function test_peringatan_mulai_hari_minggu_dan_laporan_lewat_tahun(): void
    {
        Livewire::test(Form::class)
            ->set('form.tanggal_mulai', '2026-12-27') // Minggu
            ->set('form.lama_hari', 1)
            ->assertSee('Tanggal mulai jatuh pada hari Minggu.')
            ->assertSet('form.tanggal_laporan', '2027-01-06')
            ->assertSee('Batas laporan melewati Tahun Anggaran 2026.')
            ->assertHasNoErrors();
    }

    public function test_input_tanggal_memiliki_batas_min_max(): void
    {
        Livewire::test(Form::class)
            ->assertSeeHtml('min="2025-01-01" max="2026-12-31"')
            ->set('form.tanggal_mulai', '2026-07-14')
            ->set('form.lama_hari', 3)
            ->assertSeeHtml('max="2026-07-14"')
            ->assertSeeHtml('min="2026-07-16"');
    }

    public function test_simpan_draf_menjalankan_semua_validasi_tanggal(): void
    {
        Livewire::test(Form::class)
            ->set('form.nama_kegiatan', 'Workshop Penyusunan Kurikulum')
            ->set('form.tanggal_mulai', '2026-07-14')
            ->set('form.lama_hari', 2)
            ->set('form.bulan_pelaksanaan', [5])
            ->set('form.tanggal_dokumen', '2026-08-01')
            ->call('saveDraft')
            ->assertHasErrors(['form.tanggal_dokumen', 'form.bulan_pelaksanaan']);
    }
}
