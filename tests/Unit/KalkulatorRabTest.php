<?php

namespace Tests\Unit;

use App\Support\Rab\KalkulatorRab;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class KalkulatorRabTest extends TestCase
{
    /**
     * @return array<string, array{int|float, int, int}>
     */
    public static function jumlahProvider(): array
    {
        return [
            'bulat' => [6, 600_000, 3_600_000],
            'desimal koma lima' => [1.5, 40_000, 60_000],
            'galat float 0,29 × 100' => [0.29, 100, 29],
            'galat float 0,1 × 3 tidak relevan (dua desimal)' => [0.3, 10, 3],
            'pembulatan setengah ke atas' => [0.5, 3, 2],
            'pembulatan ke bawah' => [0.25, 3, 1],
            'angka besar tanpa overflow' => [99_999.99, 10_000_000, 999_999_900_000],
        ];
    }

    #[DataProvider('jumlahProvider')]
    public function test_jumlah_volume_kali_harga_tanpa_galat_float(int|float $volume, int $harga, int $harapan): void
    {
        $this->assertSame($harapan, KalkulatorRab::jumlah($volume, $harga));
    }

    public function test_hitung_total_subtotal_dan_status_pagu(): void
    {
        $rincian = [
            ['kode' => '521213', 'kategori' => 'jasa', 'volume' => 6, 'harga_satuan' => 600_000],
            ['kode' => '521211', 'kategori' => 'bahan', 'volume' => 1.5, 'harga_satuan' => 40_000],
            ['kode' => '', 'kategori' => 'lain', 'volume' => 1, 'harga_satuan' => 150_000],
        ];

        $hasil = KalkulatorRab::hitung($rincian, 4_000_000);

        $this->assertSame([3_600_000, 60_000, 150_000], array_column($hasil->rincian, 'jumlah'));
        $this->assertSame(3_810_000, $hasil->total);
        $this->assertSame(['bahan' => 60_000, 'jasa' => 3_600_000, 'lain' => 150_000], $hasil->perKategori);
        $this->assertSame(['' => 150_000, '521211' => 60_000, '521213' => 3_600_000], $hasil->perKode);
        $this->assertSame(190_000, $hasil->selisih());
        $this->assertSame('sisa', $hasil->statusPagu());
        $this->assertSame(95, $hasil->persenPagu());
        $this->assertSame(3, $hasil->jumlahItem());
    }

    public function test_status_pagu_sesuai_melebihi_dan_tanpa_pagu(): void
    {
        $rincian = [['kode' => '', 'kategori' => 'bahan', 'volume' => 2, 'harga_satuan' => 500_000]];

        $this->assertSame('sesuai', KalkulatorRab::hitung($rincian, 1_000_000)->statusPagu());
        $this->assertSame('melebihi', KalkulatorRab::hitung($rincian, 900_000)->statusPagu());
        $this->assertSame(-100_000, KalkulatorRab::hitung($rincian, 900_000)->selisih());
        $this->assertSame(100, KalkulatorRab::hitung($rincian, 900_000)->persenPagu());
        $this->assertSame('tanpa_pagu', KalkulatorRab::hitung($rincian)->statusPagu());
        $this->assertNull(KalkulatorRab::hitung($rincian)->selisih());
    }

    public function test_rab_kosong(): void
    {
        $hasil = KalkulatorRab::hitung([], 5_000_000);

        $this->assertSame(0, $hasil->total);
        $this->assertSame('sisa', $hasil->statusPagu());
        $this->assertSame(0, $hasil->persenPagu());
    }

    public function test_gabung_beberapa_rab(): void
    {
        $a = KalkulatorRab::hitung([
            ['kode' => '521213', 'kategori' => 'jasa', 'volume' => 6, 'harga_satuan' => 600_000],
            ['kode' => '521211', 'kategori' => 'bahan', 'volume' => 1.5, 'harga_satuan' => 40_000],
        ], 10_000_000);
        $b = KalkulatorRab::hitung([['kode' => '', 'kategori' => 'lain', 'volume' => 1, 'harga_satuan' => 150_000], ['kode' => '521211', 'kategori' => 'bahan', 'volume' => 2, 'harga_satuan' => 5_000]]);

        $gabung = KalkulatorRab::gabung([$a, $b]);

        $this->assertSame([3_820_000, 4, null], [$gabung->total, $gabung->jumlahItem(), $gabung->pagu]);
        $this->assertSame(['bahan' => 70_000, 'jasa' => 3_600_000, 'lain' => 150_000], $gabung->perKategori);
        $this->assertSame(['' => 150_000, '521211' => 70_000, '521213' => 3_600_000], $gabung->perKode);
        $this->assertSame(['bahan' => 0, 'jasa' => 0, 'lain' => 0], KalkulatorRab::gabung([])->perKategori);
    }
}
