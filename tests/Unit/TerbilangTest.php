<?php

namespace Tests\Unit;

use App\Support\Stub\StubAnggaran;
use App\Support\Terbilang;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TerbilangTest extends TestCase
{
    /** @return array<string, mixed> Kasus bersama server & klien (tests/fixtures/terbilang.json). */
    private static function fixture(): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/../fixtures/terbilang.json'), true);
    }

    /**
     * @return array<string, array{int, string, string}>
     */
    public static function kasusProvider(): array
    {
        $kasus = [];
        foreach (self::fixture()['kasus'] as $k) {
            $kasus[$k['catatan'].' ('.$k['nilai'].')'] = [$k['nilai'], $k['rupiah'], $k['kata']];
        }

        return $kasus;
    }

    #[DataProvider('kasusProvider')]
    public function test_terbilang_rupiah_sesuai_kaidah(int $nilai, string $rupiah, string $kata): void
    {
        $this->assertSame([$rupiah, $kata], [Terbilang::rupiah($nilai), Terbilang::kata($nilai)]);
        $this->assertSame($nilai === 0 ? null : $rupiah, Terbilang::tampil($nilai));
    }

    public function test_kasus_data_tiruan_tercakup_fixture(): void
    {
        $fixture = array_column(self::fixture()['kasus'], 'rupiah', 'nilai');

        foreach (StubAnggaran::kasusUji() as $k) {
            $this->assertSame($k['terbilang'], $fixture[$k['nilai']] ?? null, (string) $k['nilai']);
        }
    }

    public function test_di_luar_batas_tidak_ditampilkan(): void
    {
        foreach (self::fixture()['di_luar_batas'] as $nilai) {
            $this->assertNull(Terbilang::tampil($nilai));
        }
        $this->assertNull(Terbilang::tampil(PHP_INT_MAX));
        $this->assertNull(Terbilang::tampil(null));
    }

    public function test_dengan_angka_gaya_dokumen(): void
    {
        $this->assertSame('Rp10.000.000,- (Sepuluh Juta Rupiah)', Terbilang::denganAngka(10_000_000));
        $this->assertSame('Rp0,- (Nol Rupiah)', Terbilang::denganAngka(0));
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
