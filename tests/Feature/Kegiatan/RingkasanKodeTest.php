<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Form;
use App\Livewire\Kegiatan\Klasifikasi;
use App\Support\Kegiatan\RingkasanKode;
use Livewire\Livewire;
use Tests\TestCase;

class RingkasanKodeTest extends TestCase
{
    public function test_susun_memuat_kode_uraian_dan_detail_berurutan_seperti_header(): void
    {
        $ringkasan = RingkasanKode::susun([
            'leading' => '1.6', 'kode_rktm' => '6.17.12', 'kode_sk' => 'SK.10',
            'kode_iksk' => 'IKSK 10.6', 'kode_edm' => 'C1', 'budaya_mutu' => 2,
        ]);

        $this->assertSame(['leading', 'rktm', 'sk', 'iksk', 'edm', 'budaya'], array_column($ringkasan, 'kunci'));
        $this->assertSame(6, RingkasanKode::jumlahTerisi($ringkasan));

        $perKunci = array_column($ringkasan, null, 'kunci');
        $this->assertSame('Tim Penjamin Mutu', $perKunci['leading']['uraian']);
        $this->assertSame('Penanda tangan: Ketua Tim Penjamin Mutu', $perKunci['leading']['detail']);
        $this->assertSame('Kegiatan Workshop penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran', $perKunci['rktm']['uraian']);
        $this->assertSame('PENGEMBANGAN STANDAR PENGELOLAAN · Kegiatan Pengembangan Manajemen Sekolah', $perKunci['rktm']['detail']);
        $this->assertStringStartsWith('Meningkatnya layanan pendidikan', $perKunci['sk']['uraian']);
        $this->assertStringStartsWith('Persentase perangkat kurikulum', $perKunci['iksk']['uraian']);
        $this->assertSame('Komponen C · Proses Pembelajaran', $perKunci['edm']['detail']);
        $this->assertSame(['2', 'Profesionalitas'], [$perKunci['budaya']['kode'], $perKunci['budaya']['uraian']]);
    }

    public function test_kode_kosong_atau_tak_dikenal_ditandai_belum_dipilih(): void
    {
        $ringkasan = RingkasanKode::susun(['leading' => '1.6', 'kode_sk' => 'SK.99', 'budaya_mutu' => '']);

        $this->assertSame(1, RingkasanKode::jumlahTerisi($ringkasan));
        $this->assertStringContainsString("Kode SK: (belum dipilih)\n", RingkasanKode::teks($ringkasan));
        $this->assertStringStartsWith('Leading sektor: 1.6 — Tim Penjamin Mutu', RingkasanKode::teks($ringkasan));
    }

    public function test_halaman_klasifikasi_menampilkan_ringkasan_yang_mengikuti_pilihan(): void
    {
        $id = app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Kegiatan Ringkasan',
            'nama_kegiatan_cover' => 'KEGIATAN RINGKASAN',
            'leading' => '1.6',
        ]);

        Livewire::test(Klasifikasi::class, ['kegiatan' => $id])
            ->assertSeeHtml('data-testid="ringkasan-kode-terpilih"')
            ->assertSee('1/6 dipilih')
            ->set('form.kode_sk', 'SK.12')
            ->assertSee('3/6 dipilih')
            ->assertSee('Nilai Kinerja Anggaran')
            ->set('form.kode_edm', 'C1')
            ->assertSee('4/6 dipilih')
            ->assertSee('Komponen C · Proses Pembelajaran');
    }

    public function test_data_kegiatan_menampilkan_uraian_lengkap_kode_terpilih(): void
    {
        Livewire::test(Form::class, ['kegiatan' => 1])
            ->assertSee('6/6 dipilih')
            ->assertSeeInOrder(['6.17.12', 'Kegiatan Workshop penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran'])
            ->assertSeeInOrder(['IKSK 10.6', 'Persentase perangkat kurikulum yang dievaluasi'])
            ->assertSee('Salin ringkasan');
    }
}
