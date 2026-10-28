<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Rab;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\TabelPagu;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tabel Pagu dan RAB (PRD F12): setiap RAB dibandingkan dengan pagunya sendiri, baris jumlah hanya ringkasan.
 */
class TabelPaguTest extends TestCase
{
    private function tabel(int $kegiatanId): TabelPagu
    {
        return TabelPagu::dari(AnggaranKegiatan::untuk(app(KegiatanRepository::class)->temukan($kegiatanId), sertakanTerlepas: true));
    }

    public function test_persen_pemakaian_hanya_100_bila_tepat_pagu(): void
    {
        foreach ([
            [5_400_000, 6_000_000, 90.0],
            [5_999_999, 6_000_000, 99.9],
            [6_000_000, 6_000_000, 100.0],
            [6_000_001, 6_000_000, 100.1],
            [7_950_000, 7_500_000, 106.0],
            [0, 6_000_000, 0.0],
            [1_000_000_000_001, 1_000_000_000_000, 100.1],
            [100, null, null],
            [100, 0, null],
        ] as [$total, $pagu, $harapan]) {
            $this->assertSame($harapan, TabelPagu::persen($total, $pagu), "{$total} / {$pagu}");
        }
    }

    public function test_baris_dan_jumlah_kegiatan_dua_sumber_dalam_pagu(): void
    {
        $tabel = $this->tabel(2);

        $this->assertSame(
            [['DIPA', 6_000_000, 5_400_000, 600_000, 90.0, 6, 'sisa'], ['Komite', 2_500_000, 2_500_000, 0, 100.0, 3, 'sesuai']],
            array_map(fn ($b) => [$b['sumber'], $b['pagu'], $b['total'], $b['selisih'], $b['persen'], $b['jumlah_item'], $b['status']->status], $tabel->baris()),
        );
        $this->assertSame(['pagu' => 8_500_000, 'total' => 7_900_000, 'selisih' => 600_000, 'persen' => 92.9, 'jumlah_item' => 9, 'pagu_kosong' => 0, 'melebihi' => 0, 'kelebihan' => 0], $tabel->jumlah());
        $this->assertSame(['Semua RAB dalam batas pagu', 'aman'], [$tabel->ringkasan(), $tabel->nada()]);
    }

    public function test_melebihi_dan_pagu_kosong_tidak_saling_menutupi(): void
    {
        $tabel = $this->tabel(3);

        $this->assertSame(['tanpa_pagu', 'melebihi'], array_map(fn ($b) => $b['status']->status, $tabel->baris()));
        $this->assertSame([null, null], [$tabel->baris()[0]['selisih'], $tabel->baris()[0]['persen']]);
        $this->assertSame(['pagu' => 7_500_000, 'total' => 8_850_000, 'selisih' => null, 'persen' => null, 'jumlah_item' => 9, 'pagu_kosong' => 1, 'melebihi' => 1, 'kelebihan' => 450_000], $tabel->jumlah());
        $this->assertSame(['1 RAB melebihi pagu · 1 pagu belum diisi', 'bahaya'], [$tabel->ringkasan(), $tabel->nada()]);
    }

    public function test_rab_sumber_yang_dilepas_tidak_masuk_tabel(): void
    {
        $repo = app(KegiatanRepository::class);
        $kegiatan = $repo->temukan(2);
        $kegiatan['sumber_dana'] = [['sumber' => 'Komite', 'pagu' => 2_500_000]];
        $repo->simpan($kegiatan, 2);

        $this->assertSame(['Komite'], array_column($this->tabel(2)->baris(), 'sumber'));
        $this->assertSame(2_500_000, $this->tabel(2)->jumlah()['total']);
    }

    public function test_halaman_pagu_dan_rab_di_rab_kegiatan(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 3])
            ->assertSee('Pagu &amp; RAB', false)
            ->set('halaman', 'pagu')
            ->assertSet('halaman', 'pagu')
            ->assertSeeHtml('data-testid="tabel-pagu-rab"')
            ->assertSeeHtml('data-testid="ringkasan-pagu-rab" data-nada="bahaya"')
            ->assertSee('1 RAB melebihi pagu · 1 pagu belum diisi')
            ->assertSeeHtml('data-testid="baris-pagu-dipa" data-status="tanpa_pagu"')
            ->assertSeeHtml('data-testid="baris-pagu-komite" data-status="melebihi"')
            ->assertSeeInOrder(['RAB Komite', 'Rp7.500.000', 'Rp7.950.000', '-Rp450.000', '106%', 'Melebihi pagu Rp450.000'])
            ->assertSeeHtml('>Isi pagu</a>')
            ->assertSeeHtml('data-testid="jumlah-pagu-rab"')
            ->assertSeeInOrder(['Jumlah kegiatan', 'Rp7.500.000', '1 pagu belum diisi', 'Rp8.850.000'])
            ->assertSee('Total RAB di atas pagu; kurangi volume/harga atau hapus item sebelum diajukan.')
            // Dari tabel, "Buka rincian" membuka rincian belanja sumber itu.
            ->call('pilihSumber', 'Komite')
            ->assertSet('sumber', 'Komite')
            ->assertSet('halaman', 'rincian');
    }

    public function test_halaman_pagu_satu_sumber_tanpa_baris_jumlah_dan_bisa_dibuka_dari_url(): void
    {
        Livewire::withQueryParams(['halaman' => 'pagu'])
            ->test(Rab::class, ['kegiatan' => 1])
            ->assertSet('halaman', 'pagu')
            ->assertSee('Semua RAB dalam batas pagu')
            ->assertSeeHtml('data-testid="baris-pagu-komite" data-status="sesuai"')
            ->assertSeeInOrder(['Rp10.000.000', 'Rp10.000.000', 'Rp0', '100%', 'Sesuai pagu'])
            ->assertDontSeeHtml('data-testid="jumlah-pagu-rab"')
            ->assertDontSeeHtml('>Isi pagu</a>');
    }
}
