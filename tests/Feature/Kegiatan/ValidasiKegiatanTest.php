<?php

namespace Tests\Feature\Kegiatan;

use App\Livewire\Kegiatan\Form;
use App\Support\Format;
use App\Support\Kegiatan\Impor\PemeriksaImporKegiatan;
use App\Support\Kegiatan\ValidasiKegiatan;
use App\Support\MasterKode;
use App\Support\Stub\StubKegiatanRepository;
use Livewire\Livewire;
use Tests\TestCase;

class ValidasiKegiatanTest extends TestCase
{
    public function test_contoh_kegiatan_lolos_validasi(): void
    {
        foreach (StubKegiatanRepository::contohSemua() as $contoh) {
            $this->assertSame([], ValidasiKegiatan::galat($contoh), "Contoh kegiatan {$contoh['id']}");
        }
    }

    public function test_galat_data_kegiatan(): void
    {
        $sah = StubKegiatanRepository::contohDuaSumber();
        $kasus = [
            'Kolom nama kegiatan minimal 5 karakter.' => ['nama_kegiatan' => 'Abc'],
            'Sumber dana harus DIPA atau Komite.' => ['sumber_dana' => [['sumber' => 'BOS', 'pagu' => 1_000_000]]],
            'Sumber dana DIPA dipilih lebih dari sekali.' => ['sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000], ['sumber' => 'DIPA', 'pagu' => 2_000]]],
            'Pagu terlalu besar (maksimal '.Format::rupiah(ValidasiKegiatan::MAKS_PAGU).'). Periksa jumlah digit.' => ['sumber_dana' => [['sumber' => 'Komite', 'pagu' => ValidasiKegiatan::MAKS_PAGU + 1]]],
            'Pagu harus lebih dari Rp0.' => ['sumber_dana' => [['sumber' => 'Komite', 'pagu' => 0]]],
            'Status kegiatan tidak dikenal.' => ['status' => 'dihapus'],
            'Asal kegiatan tidak dikenal.' => ['asal' => 'tebakan'],
            'Tautan Data Pagu tidak valid.' => ['data_pagu_id' => '12'],
            'IKSK 10.6 bukan indikator dari SK.11.' => ['kode_iksk' => 'IKSK 10.6'],
            'Tanggal mulai harus di Tahun Anggaran 2026.' => ['tanggal_mulai' => '2027-01-04'],
            'Kegiatan selesai 2 Januari 2027, melewati akhir Tahun Anggaran 2026.' => ['tanggal_mulai' => '2026-12-31', 'lama_hari' => 3, 'hari_kerja' => false, 'bulan_pelaksanaan' => [12], 'tanggal_laporan' => null],
            'Pilihan leading tidak ada di master tahun aktif.' => ['leading' => '9.9'],
            'Susunan tim maksimal 50 orang.' => ['panitia' => array_fill(0, 51, ['nama' => 'Anggota Tim', 'jabatan' => '', 'peran' => 'Anggota'])],
        ];

        foreach ($kasus as $pesan => $ubah) {
            $this->assertContains($pesan, ValidasiKegiatan::galat(array_replace($sah, $ubah)), $pesan);
        }

        // Isian yang sudah diperiksa pemanggil bisa dikecualikan.
        $this->assertSame([], ValidasiKegiatan::galat(array_replace($sah, ['leading' => '9.9']), kecuali: ['leading']));
    }

    public function test_form_dan_impor_memakai_aturan_yang_sama(): void
    {
        Livewire::test(Form::class, ['kegiatan' => 2])
            ->set('form.nama_kegiatan', 'Abc')
            ->assertHasErrors('form.nama_kegiatan')
            ->assertSee('Kolom nama kegiatan minimal 5 karakter.');

        $hasil = PemeriksaImporKegiatan::periksa([['baris' => 2, 'nilai' => ['nama_kegiatan' => 'Abc', 'leading' => '1.6']]], [], MasterKode::leadings(), MasterKode::leadings());
        $this->assertSame(['gagal', ['Kolom nama kegiatan minimal 5 karakter.']], [$hasil[0]['status'], $hasil[0]['pesan']]);
    }
}
