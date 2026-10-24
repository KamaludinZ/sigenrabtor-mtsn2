<?php

namespace Tests\Unit;

use App\Rules\KalimatUtuh;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class KalimatUtuhTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function kalimatProvider(): array
    {
        return [
            'terpotong "oleh" (kasus nyata data TOR)' => ['dokumen Perencanaan yang Handal dan Akurat sesuai rencana Riil yang dibutuhkan oleh', false],
            'terpotong "dan" dengan titik' => ['Meningkatkan mutu guru dan.', false],
            'terpotong "untuk" huruf kapital' => ['Menyusun modul UNTUK', false],
            'kalimat utuh' => ['dokumen perencanaan yang handal dan akurat sesuai kebutuhan riil madrasah.', true],
            'kata berakhiran mirip' => ['Kegiatan ini diikuti oleh guru dan pegawai', true],
            'kosong' => ['', true],
        ];
    }

    #[DataProvider('kalimatProvider')]
    public function test_mendeteksi_kalimat_terpotong(string $kalimat, bool $lulus): void
    {
        $gagal = false;

        (new KalimatUtuh)->validate('sasaran', $kalimat, function () use (&$gagal) {
            $gagal = true;
        });

        $this->assertSame($lulus, ! $gagal);
    }
}
