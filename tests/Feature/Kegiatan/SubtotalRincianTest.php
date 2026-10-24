<?php

namespace Tests\Feature\Kegiatan;

use App\Livewire\Kegiatan\Rab;
use App\Support\Rab\KelompokRincian;
use Livewire\Livewire;
use Tests\TestCase;

class SubtotalRincianTest extends TestCase
{
    /**
     * @return list<array<string, mixed>>
     */
    private function contoh(): array
    {
        return [
            ['id' => 'a', 'kode' => '521213', 'uraian' => 'Honor', 'kategori' => 'jasa', 'jumlah' => 600_000],
            ['id' => 'b', 'kode' => '521211', 'uraian' => 'Nasi', 'kategori' => 'bahan', 'jumlah' => 300_000],
            ['id' => 'c', 'kode' => '', 'uraian' => 'Lain', 'kategori' => 'lain', 'jumlah' => 100_000],
            ['id' => 'd', 'kode' => '521211', 'uraian' => 'Kue', 'kategori' => 'bahan', 'jumlah' => 0],
        ];
    }

    public function test_mode_urutan_satu_kelompok_bernomor_urut(): void
    {
        $hasil = KelompokRincian::kelompokkan($this->contoh(), 'urutan');

        $this->assertCount(1, $hasil);
        $this->assertNull($hasil[0]['label']);
        $this->assertSame([1, 2, 3, 4], array_column($hasil[0]['item'], 'nomor'));
        $this->assertSame(1_000_000, $hasil[0]['subtotal']);
    }

    public function test_per_kategori_urut_bahan_jasa_lain_dengan_persen(): void
    {
        $hasil = KelompokRincian::kelompokkan($this->contoh(), 'kategori');

        $this->assertSame(['bahan', 'jasa', 'lain'], array_column($hasil, 'kunci'));
        $this->assertSame([300_000, 600_000, 100_000], array_column($hasil, 'subtotal'));
        $this->assertSame([30.0, 60.0, 10.0], array_column($hasil, 'persen'));
        $this->assertSame([2, 4], array_column($hasil[0]['item'], 'nomor'), 'Nomor asli tetap terbawa.');
    }

    public function test_per_kode_akun_urut_naik_tanpa_kode_di_akhir(): void
    {
        $hasil = KelompokRincian::kelompokkan($this->contoh(), 'kode');

        $this->assertSame(['521211', '521213', ''], array_column($hasil, 'kunci'));
        $this->assertSame('521211 · Belanja Bahan', $hasil[0]['label']);
        $this->assertSame('Tanpa kode akun', $hasil[2]['label']);
    }

    public function test_total_nol_tidak_membagi_nol(): void
    {
        $hasil = KelompokRincian::kelompokkan([['kode' => '', 'kategori' => 'bahan', 'jumlah' => 0]], 'kategori');

        $this->assertSame(0.0, $hasil[0]['persen']);
    }

    public function test_halaman_rab_menampilkan_subtotal_per_kelompok_belanja(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->assertDontSee('Subtotal Bahan')
            ->set('kelompok', 'kategori')
            ->assertSee('Subtotal Bahan')
            ->assertSee('3.930.000')
            ->assertSee('Subtotal Jasa')
            ->assertSee('5.920.000')
            ->assertSee('Subtotal Lain-lain')
            ->assertSee('(39,3%)', false)
            ->assertSee('10.000.000');
    }

    public function test_halaman_rab_per_kode_akun(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->set('kelompok', 'kode')
            ->assertSee('521211 · Belanja Bahan')
            ->assertSee('521213 · Belanja Honor Output Kegiatan')
            ->assertSee('524113 · Belanja Perjalanan Dinas Dalam Kota');
    }

    public function test_mode_tidak_dikenal_kembali_ke_urutan(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->set('kelompok', 'acak')
            ->assertSet('kelompok', 'urutan');

        Livewire::withQueryParams(['tampil' => 'acak'])
            ->test(Rab::class, ['kegiatan' => 1])
            ->assertSet('kelompok', 'urutan');

        Livewire::withQueryParams(['tampil' => 'kategori'])
            ->test(Rab::class, ['kegiatan' => 1])
            ->assertSet('kelompok', 'kategori');
    }
}
