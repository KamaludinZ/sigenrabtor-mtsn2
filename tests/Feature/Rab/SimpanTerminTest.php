<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentRabRepository;
use App\Support\Rab\SimpanTermin;
use App\Support\Stub\StubKegiatanRepository;
use App\Support\Stub\StubRab;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SimpanTerminTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    /** Kegiatan contoh 1 (RAB Komite Rp10.000.000) di penyimpanan pilihan; mengembalikan id. */
    private function kegiatan(bool $database): int
    {
        if (! $database) {
            return 1;
        }
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(RabRepository::class, EloquentRabRepository::class);
        $id = app(KegiatanRepository::class)->simpan(Arr::except(StubKegiatanRepository::contoh(), ['id', 'status']));
        foreach (StubRab::rincian(1, 'Komite') as $item) {
            app(RabRepository::class)->tambahItem($id, 'Komite', $item);
        }

        return $id;
    }

    private function galat(callable $aksi): array
    {
        try {
            $aksi();
        } catch (ValidationException $e) {
            return $e->errors();
        }
        $this->fail('Seharusnya ditolak.');
    }

    #[DataProvider('penyimpanan')]
    public function test_termin_pas_disimpan_dan_baris_kosong_diabaikan(bool $database): void
    {
        $id = $this->kegiatan($database);

        $hasil = SimpanTermin::simpan($id, 'Komite', [
            ['tanggal' => '2026-07-01', 'nominal' => '6.000.000'],
            ['tanggal' => '', 'nominal' => ''],
            ['tanggal' => '2026-07-15', 'nominal' => 'Rp4.000.000'],
        ]);

        $this->assertSame([1, 'pas'], [$hasil['diabaikan'], $hasil['mekanisme']->status()]);
        $this->assertSame([['tanggal' => '2026-07-01', 'nominal' => 6_000_000], ['tanggal' => '2026-07-15', 'nominal' => 4_000_000]], app(RabRepository::class)->termin($id, 'Komite'));

        // Daftar kosong = dibayar sekaligus.
        $this->assertSame('sekaligus', SimpanTermin::simpan($id, 'Komite', [])['mekanisme']->status());
        $this->assertSame([], app(RabRepository::class)->termin($id, 'Komite'));
    }

    #[DataProvider('penyimpanan')]
    public function test_jumlah_harus_sama_dengan_total_rab(bool $database): void
    {
        $id = $this->kegiatan($database);
        $sebelum = app(RabRepository::class)->termin($id, 'Komite');

        $this->assertSame(
            ['termin' => ['Jumlah termin harus sama dengan total RAB Komite (Rp10.000.000); termin kurang Rp1.000.000 dari total RAB.']],
            $this->galat(fn () => SimpanTermin::simpan($id, 'Komite', [['tanggal' => '2026-07-01', 'nominal' => '9.000.000']])),
        );
        $this->assertSame($sebelum, app(RabRepository::class)->termin($id, 'Komite'), 'Termin lama tidak berubah.');
    }

    public function test_aturan_baris_dan_kegiatan_terkunci(): void
    {
        $this->assertSame(['baris.0.tanggal' => ['Tanggal termin harus di Tahun Anggaran 2026.']], $this->galat(fn () => SimpanTermin::simpan(1, 'Komite', [['tanggal' => '2027-01-04', 'nominal' => '10.000.000']])));
        $this->assertSame(['baris.1.tanggal' => ['Tanggal termin 2 tidak boleh sebelum termin 1.']], $this->galat(fn () => SimpanTermin::simpan(1, 'Komite', [['tanggal' => '2026-07-15', 'nominal' => '5.000.000'], ['tanggal' => '2026-07-01', 'nominal' => '5.000.000']])));
        $this->assertArrayHasKey('baris.0.nominal', $this->galat(fn () => SimpanTermin::simpan(1, 'Komite', [['tanggal' => '2026-07-01', 'nominal' => '0']])));
        $this->assertArrayHasKey('baris', $this->galat(fn () => SimpanTermin::simpan(1, 'Komite', array_fill(0, 13, ['tanggal' => '2026-07-01', 'nominal' => '1']))));

        $repo = app(KegiatanRepository::class);
        $repo->simpan(['status' => 'disetujui'] + $repo->temukan(1), 1);
        $this->expectException(AuthorizationException::class);
        SimpanTermin::simpan(1, 'Komite', []);
    }
}
