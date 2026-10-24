<?php

namespace Tests\Unit;

use App\Support\Stub\StubAnggaran;
use App\Support\Terbilang;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TerbilangTest extends TestCase
{
    /**
     * Kasus uji dari data tiruan anggaran (StubAnggaran::kasusUji), ditulis ulang agar tes unit mandiri.
     *
     * @return array<string, array{int, string}>
     */
    public static function kasusProvider(): array
    {
        $kasus = [];
        foreach (StubAnggaran::kasusUji() as $k) {
            $kasus[$k['catatan'].' ('.$k['nilai'].')'] = [$k['nilai'], $k['terbilang']];
        }

        return $kasus;
    }

    #[DataProvider('kasusProvider')]
    public function test_terbilang_rupiah_sesuai_kaidah(int $nilai, string $harapan): void
    {
        $this->assertSame($harapan, Terbilang::rupiah($nilai));
    }

    public function test_bentuk_kata_huruf_kecil(): void
    {
        $this->assertSame('satu juta seribu satu', Terbilang::kata(1_001_001));
        $this->assertSame('nol', Terbilang::kata(0));
        $this->assertSame('minus lima ratus ribu', Terbilang::kata(-500_000));
    }

    public function test_kesalahan_dokumen_lama_tidak_terjadi_lagi(): void
    {
        $this->assertNotSame('Sepuluh Juta Puluh', Terbilang::rupiah(10_000_000));
        $this->assertNotSame('Tiga Puluh Juta Rupiah', Terbilang::rupiah(32_000_000));
    }

    public function test_batas_maksimum(): void
    {
        $this->assertStringStartsWith('Sembilan Ratus Sembilan Puluh Sembilan Triliun', Terbilang::rupiah(Terbilang::MAKSIMUM));

        $this->expectException(InvalidArgumentException::class);
        Terbilang::rupiah(Terbilang::MAKSIMUM + 1);
    }
}
