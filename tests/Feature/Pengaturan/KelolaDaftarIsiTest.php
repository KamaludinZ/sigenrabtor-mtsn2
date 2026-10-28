<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\DaftarIsiRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Repositories\EloquentDaftarIsiRepository;
use App\Support\Dokumen\DaftarIsi;
use App\Support\Pengaturan\KelolaDaftarIsi;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class KelolaDaftarIsiTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    private function riwayatTerakhir(): array
    {
        return app(RiwayatPengaturanRepository::class)->semua()[0];
    }

    #[DataProvider('penyimpanan')]
    public function test_bawaan_khusus_dan_kembali_ke_bawaan(bool $database): void
    {
        if ($database) {
            RujukanDb::siapkan();
            $this->app->singleton(DaftarIsiRepository::class, EloquentDaftarIsiRepository::class);
        }
        $repo = app(DaftarIsiRepository::class);
        $this->assertSame(DaftarIsi::rapikan(DaftarIsi::BAWAAN), $repo->bawaan(2026), 'Belum diubah: susunan bawaan aplikasi.');
        $this->assertSame([], $repo->khusus(2026));

        $bawaan = KelolaDaftarIsi::simpan(2026, null, null, ['judul' => '  DAFTAR   KEGIATAN   LEADING '] + DaftarIsi::BAWAAN, self::ADMIN);
        $this->assertSame('DAFTAR KEGIATAN LEADING', $bawaan['judul']);
        $this->assertSame($bawaan, $repo->bawaan(2026));
        $this->assertStringStartsWith('Pengaturan bawaan daftar isi diubah — Judul: ', $this->riwayatTerakhir()['keterangan']);

        $khusus = KelolaDaftarIsi::simpan(2026, '1.6', 'Komite', ['urutan' => 'nama'] + $bawaan, self::ADMIN);
        $this->assertSame(['1.6|Komite' => $khusus], $repo->khusus(2026));
        $this->assertSame($khusus, KelolaDaftarIsi::tersimpan(2026, '1.6', 'Komite'));
        $this->assertSame($bawaan, KelolaDaftarIsi::tersimpan(2026, '1.6', 'DIPA'), 'Buku tanpa pengaturan khusus memakai bawaan.');
        $this->assertStringStartsWith('Pengaturan khusus daftar isi Tim Penjamin Mutu · Komite disimpan — Urutan: ', $this->riwayatTerakhir()['keterangan']);

        KelolaDaftarIsi::simpan(2026, '1.6', 'Komite', $khusus, self::ADMIN);
        $this->assertSame([$khusus], array_values($repo->khusus(2026)), 'Simpan ulang mengganti, tidak menambah.');

        KelolaDaftarIsi::hapusKhusus(2026, '1.6', 'Komite', self::ADMIN);
        $this->assertSame([], $repo->khusus(2026));
        $this->assertSame(['hapus_daftar_isi_khusus', 'Daftar isi Tim Penjamin Mutu · Komite kembali memakai pengaturan bawaan.'], [$this->riwayatTerakhir()['aksi'], $this->riwayatTerakhir()['keterangan']]);
        $this->assertSame(DaftarIsi::rapikan(DaftarIsi::BAWAAN), $repo->bawaan(2025), 'Tahun lain tidak terpengaruh.');
    }

    public function test_buku_tak_dikenal_bukan_admin_dan_tahun_terkunci(): void
    {
        foreach ([['9.9', 'DIPA'], ['1.6', 'BOS'], ['1.9', 'DIPA'], [null, 'DIPA']] as [$leading, $sumber]) {
            try {
                KelolaDaftarIsi::simpan(2026, $leading, $sumber, DaftarIsi::BAWAAN, self::ADMIN);
                $this->fail('Buku tak dikenal seharusnya ditolak: '.json_encode([$leading, $sumber]));
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('buku', $e->errors());
            }
        }

        foreach ([fn () => KelolaDaftarIsi::simpan(2026, null, null, DaftarIsi::BAWAAN, ['role' => 'leading']), fn () => KelolaDaftarIsi::simpan(2025, null, null, DaftarIsi::BAWAAN, self::ADMIN), fn () => KelolaDaftarIsi::hapusKhusus(2025, '1.6', 'DIPA', self::ADMIN)] as $aksi) {
            try {
                $aksi();
                $this->fail('Seharusnya ditolak.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame([], app(DaftarIsiRepository::class)->khusus(2026));
    }
}
