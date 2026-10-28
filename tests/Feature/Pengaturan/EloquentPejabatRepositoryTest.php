<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\PejabatRepository;
use App\Repositories\EloquentPejabatRepository;
use App\Support\Pengaturan\PenandaTangan;
use App\Support\Stub\StubData;
use App\Support\Stub\StubPejabatRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class EloquentPejabatRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    /** Repositori pilihan; database diisi contoh tiruan TA 2025 lewat simpan(). */
    private function repo(bool $database): PejabatRepository
    {
        if (! $database) {
            return app(PejabatRepository::class);
        }
        RujukanDb::siapkan();
        $this->app->singleton(PejabatRepository::class, EloquentPejabatRepository::class);
        $repo = app(PejabatRepository::class);
        foreach (StubPejabatRepository::contoh() as $tahun => $pejabat) {
            foreach (['kepala', 'komite'] as $peran) {
                $repo->simpan((int) $tahun, $peran, null, $pejabat[$peran]);
            }
            foreach ($pejabat['leading'] as $kode => $p) {
                $repo->simpan((int) $tahun, 'leading', $kode, $p);
            }
        }

        return $repo;
    }

    public function test_bentuk_database_sama_dengan_tiruan(): void
    {
        $tiruan = [app(PejabatRepository::class)->untukTahun(2025), app(PejabatRepository::class)->untukTahun(2026), app(PejabatRepository::class)->aturanMengetahui(2025)];
        $repo = $this->repo(true);

        $this->assertSame($tiruan, [$repo->untukTahun(2025), $repo->untukTahun(2026), $repo->aturanMengetahui(2025)]);
        $this->assertSame('Drs. H. Mahmud Arifin, M.Pd.I.', $repo->untukTahun(2025)['kepala']['nama']);
        $this->assertNull($repo->untukTahun(2026)['kepala']['nama'], 'TA 2026 sengaja kosong.');
    }

    #[DataProvider('penyimpanan')]
    public function test_simpan_pejabat_dan_aturan_mengetahui(bool $database): void
    {
        $repo = $this->repo($database);

        $repo->simpan(2026, 'kepala', null, ['jabatan' => 'Kepala MTsN 2 Kota Malang', 'nama' => 'Dra. Hj. Siti Aminah, M.Pd.', 'nip' => '197001011995032001']);
        $repo->simpan(2026, 'leading', '1.6', ['jabatan' => 'Ketua Tim Penjamin Mutu', 'nama' => 'Muslimin, M.Pd.', 'nip' => '']);
        $repo->simpan(2026, 'kepala', null, ['jabatan' => 'Kepala MTsN 2 Kota Malang', 'nama' => 'Dra. Hj. Siti Aminah, M.Pd.', 'nip' => '197001011995032002']);

        $pejabat = $repo->untukTahun(2026);
        $this->assertSame('197001011995032002', $pejabat['kepala']['nip'], 'Simpan ulang mengganti, tidak menambah.');
        $this->assertSame(['jabatan' => 'Ketua Tim Penjamin Mutu', 'nama' => 'Muslimin, M.Pd.', 'nip' => null], $pejabat['leading']['1.6']);
        $this->assertNull($repo->untukTahun(2025)['leading']['1.2']['nama'] ?? null);

        $this->assertSame(['DIPA' => true, 'Komite' => true], $repo->aturanMengetahui(2026), 'Bawaan PRD §12: tampil di kedua sumber dana.');
        $repo->simpanAturanMengetahui(2026, ['DIPA' => false, 'BOS' => true]);
        $repo->simpanAturanMengetahui(2026, ['DIPA' => false, 'Komite' => true]);
        $this->assertSame(['DIPA' => false, 'Komite' => true], $repo->aturanMengetahui(2026));

        foreach ([fn () => $repo->simpan(2026, 'bendahara', null, ['jabatan' => 'X', 'nama' => null, 'nip' => null]), fn () => $repo->simpan(2026, 'leading', '9.9', ['jabatan' => 'X', 'nama' => null, 'nip' => null])] as $aksi) {
            try {
                $aksi();
                $this->fail('Peran tak dikenal seharusnya ditolak.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_dokumen_membaca_pejabat_dari_database(): void
    {
        $repo = $this->repo(true);
        $repo->simpan(2026, 'kepala', null, ['jabatan' => 'Kepala MTsN 2 Kota Malang', 'nama' => 'Dra. Hj. Siti Aminah, M.Pd.', 'nip' => '197001011995032001']);

        $this->assertSame('Dra. Hj. Siti Aminah, M.Pd.', StubData::pejabat()['kepala']['nama']);
        $this->assertSame('19700101 199503 2 001', PenandaTangan::pimpinan()['kepala']['nip']);

        try {
            DB::transaction(fn () => $repo->simpan(2026, 'komite', null, ['jabatan' => 'Ketua Komite', 'nama' => 'X', 'nip' => '123']));
            $this->fail('NIP bukan 18 angka seharusnya ditolak database.');
        } catch (QueryException) {
            $this->assertNull($repo->untukTahun(2026)['komite']['nama']);
        }
    }
}
