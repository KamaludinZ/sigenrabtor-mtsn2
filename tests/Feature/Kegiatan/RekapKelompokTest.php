<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\Rab;
use App\Support\Rab\KalkulatorRab;
use App\Support\Rab\RekapKelompok;
use Livewire\Livewire;
use Tests\TestCase;

class RekapKelompokTest extends TestCase
{
    public function test_susun_rekap_lintas_sumber_dana(): void
    {
        $dipa = KalkulatorRab::hitung([
            ['kode' => '521213', 'uraian' => 'Honor', 'kategori' => 'jasa', 'volume' => 2, 'harga_satuan' => 500_000],
            ['kode' => '521211', 'uraian' => 'ATK', 'kategori' => 'bahan', 'volume' => 10, 'harga_satuan' => 10_000],
        ], 2_000_000);
        $komite = KalkulatorRab::hitung([
            ['kode' => '521211', 'uraian' => 'Konsumsi', 'kategori' => 'bahan', 'volume' => 20, 'harga_satuan' => 25_000],
        ], 500_000);

        $rekap = RekapKelompok::susun(['DIPA' => $dipa, 'Komite' => $komite]);

        $this->assertSame(1_600_000, $rekap['total']);
        $this->assertSame(['DIPA' => 1_100_000, 'Komite' => 500_000], $rekap['totalPerSumber']);
        $this->assertSame(3, $rekap['jumlahItem']);

        $bahan = $rekap['kelompok'][0];
        $this->assertSame('bahan', $bahan['kunci']);
        $this->assertSame(['DIPA' => 100_000, 'Komite' => 500_000], $bahan['perSumber']);
        $this->assertSame(600_000, $bahan['total']);
        $this->assertSame(37.5, $bahan['persen']);
        $this->assertSame(['Konsumsi', 'ATK'], array_column($bahan['item'], 'uraian'), 'Item urut dari jumlah terbesar.');
        $this->assertSame('Komite', $bahan['item'][0]['sumber']);

        $this->assertSame(0, $rekap['kelompok'][2]['total'], 'Kelompok kosong tetap tampil.');
        $this->assertSame(['521211', '521213'], array_column($rekap['kode'], 'kode'));
        $this->assertSame(['DIPA' => 100_000, 'Komite' => 500_000], $rekap['kode'][0]['perSumber']);
    }

    public function test_halaman_rekap_menampilkan_komposisi_tabel_dan_kartu(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->assertSee('Rekap per kelompok')
            ->set('halaman', 'rekap')
            ->assertSee('Komposisi per kelompok belanja')
            ->assertSee('Rekap per kelompok belanja')
            ->assertSee('Rekap per kode akun')
            ->assertSee('Total anggaran kegiatan')
            ->assertSee('Sesuai pagu Rp10.000.000')
            ->assertSee('3.930.000')
            ->assertSee('(59,2%)', false)
            ->assertSee('+5 item lainnya')
            ->assertSeeHtml('role="tooltip"')
            ->assertDontSee('Tambah item belanja');
    }

    public function test_tautan_kartu_membuka_rincian_per_kelompok(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->set('halaman', 'rekap')
            ->call('lihatKelompokDiRincian')
            ->assertSet('halaman', 'rincian')
            ->assertSet('kelompok', 'kategori');
    }

    public function test_halaman_tidak_dikenal_kembali_ke_rincian(): void
    {
        Livewire::withQueryParams(['halaman' => 'aneh'])
            ->test(Rab::class, ['kegiatan' => 1])
            ->assertSet('halaman', 'rincian');
    }

    public function test_dua_sumber_dana_menampilkan_baris_gabungan(): void
    {
        $id = app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Dua Sumber',
            'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000], ['sumber' => 'Komite', 'pagu' => null]],
        ]);
        app(RabRepository::class)->tambahItem($id, 'DIPA', ['kode' => '', 'uraian' => 'ATK', 'kategori' => 'bahan', 'volume' => 1, 'satuan' => 'pack', 'harga_satuan' => 100_000]);

        Livewire::test(Rab::class, ['kegiatan' => $id])
            ->set('halaman', 'rekap')
            ->assertSee('Gabungan')
            ->assertSee('DIPA (Rp)')
            ->assertSee('Komite (Rp)')
            ->assertSee('Pagu belum diisi')
            ->assertSee('Belum ada item');
    }
}
