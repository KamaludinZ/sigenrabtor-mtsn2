<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\NomorHalamanRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Repositories\EloquentNomorHalamanRepository;
use App\Support\Dokumen\NomorHalaman;
use App\Support\Pengaturan\KelolaNomorHalaman;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class KelolaNomorHalamanTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    #[DataProvider('penyimpanan')]
    public function test_simpan_lalu_perbarui_aturan(bool $database): void
    {
        if ($database) {
            RujukanDb::siapkan();
            $this->app->singleton(NomorHalamanRepository::class, EloquentNomorHalamanRepository::class);
        }
        $repo = app(NomorHalamanRepository::class);
        $this->assertSame(NomorHalaman::rapikan(NomorHalaman::BAWAAN), $repo->untuk(2026), 'Belum diubah: aturan bawaan.');

        $baru = KelolaNomorHalaman::simpan(2026, ['format' => '- {x} -', 'posisi' => 'bawah_kanan', 'mulai_dari' => '3'] + NomorHalaman::BAWAAN, self::ADMIN);
        $this->assertSame($baru, $repo->untuk(2026));
        $this->assertSame(['- {x} -', 'bawah_kanan', 3], [$baru['format'], $baru['posisi'], $baru['mulai_dari']]);
        $riwayat = app(RiwayatPengaturanRepository::class)->semua()[0];
        $this->assertSame('ubah_nomor_halaman', $riwayat['aksi']);
        $this->assertStringContainsString('Posisi: Bawah tengah → Bawah kanan', $riwayat['keterangan']);

        // Diperbarui: satu baris per tahun, tahun lain tetap bawaan.
        KelolaNomorHalaman::simpan(2026, ['sampul_tanpa_nomor' => false] + $baru, self::ADMIN);
        $this->assertFalse($repo->untuk(2026)['sampul_tanpa_nomor']);
        $this->assertSame('- {x} -', $repo->untuk(2026)['format']);
        $this->assertSame(NomorHalaman::rapikan(NomorHalaman::BAWAAN), $repo->untuk(2025));
    }

    public function test_format_tidak_sah_bukan_admin_dan_tahun_terkunci(): void
    {
        try {
            KelolaNomorHalaman::simpan(2026, ['format' => 'Halaman'] + NomorHalaman::BAWAAN, self::ADMIN);
            $this->fail('Format tanpa {x} seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame(NomorHalaman::galatFormat('Halaman'), $e->errors()['format'][0]);
        }

        foreach ([fn () => KelolaNomorHalaman::simpan(2026, NomorHalaman::BAWAAN, ['role' => 'leading']), fn () => KelolaNomorHalaman::simpan(2025, NomorHalaman::BAWAAN, self::ADMIN)] as $aksi) {
            try {
                $aksi();
                $this->fail('Seharusnya ditolak.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(NomorHalaman::rapikan(NomorHalaman::BAWAAN), app(NomorHalamanRepository::class)->untuk(2026));
    }
}
