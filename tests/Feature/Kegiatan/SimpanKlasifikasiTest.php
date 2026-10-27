<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Support\Kegiatan\RingkasanKode;
use App\Support\Kegiatan\SimpanKlasifikasi;
use App\Support\Stub\StubData;
use App\Support\Stub\StubKegiatanRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SimpanKlasifikasiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    private function kegiatan(bool $database): int
    {
        if ($database) {
            $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        }

        return app(KegiatanRepository::class)->simpan(Arr::except(StubKegiatanRepository::contoh(), ['id', 'status']) + [
            'kode_hilang' => ['kode_edm' => ['kode' => 'Z9', 'asal' => 'impor', 'tahun' => 2026]],
        ]);
    }

    #[DataProvider('penyimpanan')]
    public function test_simpan_kode_mencatat_riwayat_dan_menggugurkan_kode_hilang(bool $database): void
    {
        $id = $this->kegiatan($database);
        $repo = app(KegiatanRepository::class);
        $dimuat = RingkasanKode::nilaiBaku($repo->temukan($id));

        $hasil = SimpanKlasifikasi::simpan($id, ['kode_sk' => 'SK.11', 'kode_iksk' => 'IKSK 11.5', 'kode_edm' => 'A3'] + $dimuat, $dimuat, StubData::currentUser());

        $this->assertSame(['status' => 'tersimpan', 'perubahan' => ['kode_sk' => ['SK.10', 'SK.11'], 'kode_iksk' => ['IKSK 10.6', 'IKSK 11.5'], 'kode_edm' => ['C1', 'A3']]], $hasil);
        $k = $repo->temukan($id);
        $this->assertSame(['SK.11', 'IKSK 11.5', 'A3', [], 'Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran'], [$k['kode_sk'], $k['kode_iksk'], $k['kode_edm'], $k['kode_hilang'], $k['nama_kegiatan']]);
        $this->assertSame([StubData::currentUser()['name'], $hasil['perubahan']], [$k['riwayat_kode'][0]['oleh'], $k['riwayat_kode'][0]['perubahan']]);

        // Tanpa perubahan: tidak disimpan ulang.
        $versi = $k['diperbarui_pada'];
        $this->assertSame('tanpa_perubahan', SimpanKlasifikasi::simpan($id, RingkasanKode::nilaiBaku($k), RingkasanKode::nilaiBaku($k), StubData::currentUser())['status']);
        $this->assertSame($versi, $repo->temukan($id)['diperbarui_pada']);
    }

    #[DataProvider('penyimpanan')]
    public function test_bentrok_dengan_perubahan_di_tempat_lain_dan_timpa(bool $database): void
    {
        $id = $this->kegiatan($database);
        $repo = app(KegiatanRepository::class);
        $dimuat = RingkasanKode::nilaiBaku($repo->temukan($id));
        SimpanKlasifikasi::simpan($id, ['kode_edm' => 'A3'] + $dimuat, $dimuat, StubData::currentUser());

        $hasil = SimpanKlasifikasi::simpan($id, ['kode_rktm' => '6.19.1'] + $dimuat, $dimuat, StubData::currentUser());
        $this->assertSame(['status' => 'bentrok', 'perubahan' => ['kode_edm' => ['C1', 'A3']]], $hasil);
        $this->assertSame('6.17.12', $repo->temukan($id)['kode_rktm']);

        // Timpa: kode di layar menang.
        $this->assertSame('tersimpan', SimpanKlasifikasi::simpan($id, ['kode_rktm' => '6.19.1'] + $dimuat, null, StubData::currentUser())['status']);
        $this->assertSame(['6.19.1', 'C1'], [$repo->temukan($id)['kode_rktm'], $repo->temukan($id)['kode_edm']]);
    }

    public function test_riwayat_bertambah_dan_kode_tidak_sah_ditolak(): void
    {
        $id = $this->kegiatan(false);
        $repo = app(KegiatanRepository::class);
        foreach (['A1', 'A2', 'A3', 'A4'] as $edm) {
            $dimuat = RingkasanKode::nilaiBaku($repo->temukan($id));
            SimpanKlasifikasi::simpan($id, ['kode_edm' => $edm] + $dimuat, $dimuat, StubData::currentUser());
        }
        $this->assertCount(4, $repo->temukan($id)['riwayat_kode']);

        $dimuat = RingkasanKode::nilaiBaku($repo->temukan($id));
        foreach ([
            'kode_sk' => ['kode_sk' => null, 'kode_iksk' => null],
            'kode_iksk' => ['kode_iksk' => 'IKSK 11.5'],
            'leading' => ['leading' => '1.1'],
            'kode_rktm' => ['kode_rktm' => '9.9.9'],
        ] as $isian => $ubah) {
            try {
                SimpanKlasifikasi::simpan($id, $ubah + $dimuat, $dimuat, StubData::currentUser());
                $this->fail("{$isian} seharusnya ditolak.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($isian, $e->errors(), $isian);
            }
        }
        $this->assertSame('A4', $repo->temukan($id)['kode_edm']);

        $this->expectException(RuntimeException::class);
        SimpanKlasifikasi::simpan(999, $dimuat, null, StubData::currentUser());
    }
}
