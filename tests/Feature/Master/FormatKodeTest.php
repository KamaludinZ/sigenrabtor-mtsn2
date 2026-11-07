<?php

namespace Tests\Feature\Master;

use App\Support\Master\FormatKode;
use App\Support\Master\Impor\PemeriksaIksk;
use App\Support\Master\Impor\PemeriksaSk;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FormatKodeTest extends TestCase
{
    /** @return array<string, array{string, string|null}> */
    public static function kodeSk(): array
    {
        return [
            'gaya lama' => ['SK.10', 'SK.10'],
            'tanpa titik' => ['sk10', 'SK.10'],
            'tanda hubung' => ['SK-10', 'SK.10'],
            'kemenag' => ['SK.1.2133', 'SK.1.2133'],
            'kemenag berspasi' => ['SK 6708.1', 'SK.6708.1'],
            'nol di depan' => ['SK.01.02', 'SK.1.2'],
            'huruf akhir ditolak' => ['SK.1.a', null],
            'bukan kode' => ['SASARAN', null],
            'kosong' => ['', null],
        ];
    }

    #[DataProvider('kodeSk')]
    public function test_bentuk_baku_sk(string $masukan, ?string $baku): void
    {
        $this->assertSame($baku, FormatKode::sk($masukan));
        $this->assertSame($baku, PemeriksaSk::normalkanKode($masukan));
        if ($baku !== null) {
            $this->assertMatchesRegularExpression('/'.FormatKode::POLA_SK.'/', $baku);
        }
    }

    /** @return array<string, array{string, string|null}> */
    public static function kodeIksk(): array
    {
        return [
            'gaya lama' => ['IKSK 10.2', 'IKSK 10.2'],
            'gaya lama rapat' => ['iksk10-15', 'IKSK 10.15'],
            'dua bagian bertitik tetap gaya lama' => ['IKSK.10.2', 'IKSK 10.2'],
            'kemenag' => ['IKSK.1.2133.1', 'IKSK.1.2133.1'],
            'kemenag berspasi' => ['IKSK 6708.1.4', 'IKSK.6708.1.4'],
            'kemenag berhuruf' => ['IKSK.4422.1.1.a', 'IKSK.4422.1.1.a'],
            'huruf kapital dirapikan' => ['IKSK.4422.1.1.B', 'IKSK.4422.1.1.b'],
            'satu bagian ditolak' => ['IKSK.12', null],
            'tanpa nomor' => ['IKSK', null],
            'sk bukan iksk' => ['SK.10', null],
        ];
    }

    #[DataProvider('kodeIksk')]
    public function test_bentuk_baku_iksk(string $masukan, ?string $baku): void
    {
        $this->assertSame($baku, FormatKode::iksk($masukan));
        $this->assertSame($baku, PemeriksaIksk::normalkanKode($masukan));
        if ($baku !== null) {
            $this->assertMatchesRegularExpression('/'.FormatKode::POLA_IKSK.'/', $baku);
        }
    }

    public function test_iksk_sejalan_dengan_sk_induk_bila_sk_menjadi_awalannya(): void
    {
        $this->assertTrue(FormatKode::sejalan('IKSK 10.2', 'SK.10'));
        $this->assertTrue(FormatKode::sejalan('IKSK.1.2133.1', 'SK.1.2133'));
        $this->assertTrue(FormatKode::sejalan('IKSK.4422.1.1.a', 'SK.4422.1'));
        $this->assertFalse(FormatKode::sejalan('IKSK 10.9', 'SK.11'));
        $this->assertFalse(FormatKode::sejalan('IKSK.2129.5.6', 'SK.2129.2'));
        $this->assertFalse(FormatKode::sejalan('IKSK.1.2133', 'SK.1.2133'), 'IKSK harus lebih panjang dari SK-nya.');
    }
}
