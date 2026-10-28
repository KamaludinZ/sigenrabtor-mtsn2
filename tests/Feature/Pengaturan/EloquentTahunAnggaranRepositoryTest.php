<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\TahunAnggaranRepository;
use App\Repositories\EloquentTahunAnggaranRepository;
use App\Support\Pengaturan\KontrolTahunAktif;
use App\Support\Stub\StubData;
use App\Support\Stub\StubTahunAnggaranRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class EloquentTahunAnggaranRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    /** Repositori pilihan berisi contoh tiruan (2026 aktif tanpa SK, 2025 terkunci ber-SK). */
    private function repo(bool $database): TahunAnggaranRepository
    {
        if ($database) {
            foreach (StubTahunAnggaranRepository::contoh() as $t) {
                DB::table('tahun_anggaran')->insert(Arr::except($t, ['id']));
            }
            $this->app->singleton(TahunAnggaranRepository::class, EloquentTahunAnggaranRepository::class);
        }

        return app(TahunAnggaranRepository::class);
    }

    /** @param  list<array<string, mixed>>  $daftar */
    private function tanpaId(array $daftar): array
    {
        return array_map(fn (array $t) => Arr::except($t, ['id']), $daftar);
    }

    public function test_bentuk_dan_urutan_database_sama_dengan_tiruan(): void
    {
        $tiruan = $this->tanpaId($this->repo(false)->semua());
        $database = $this->repo(true);

        $this->assertSame($tiruan, $this->tanpaId($database->semua()));
        $this->assertSame([2026, 2025], array_column($database->semua(), 'tahun'), 'Terbaru dulu.');
        $this->assertSame(Arr::except($this->repo(false)->aktif(), ['id']), Arr::except($database->aktif(), ['id']));
        $this->assertSame(['id', 'tahun', 'is_active', 'is_locked', 'no_sk_rktm', 'tanggal_sk_rktm', 'diaktifkan_oleh', 'diaktifkan_pada'], array_keys($database->aktif()));
    }

    #[DataProvider('penyimpanan')]
    public function test_aktifkan_dan_simpan_sk(bool $database): void
    {
        $repo = $this->repo($database);

        $repo->aktifkan(2025, 'Rina Kusumawati, M.Pd.');
        $aktif = $repo->aktif();
        $this->assertSame([2025, 'Rina Kusumawati, M.Pd.'], [$aktif['tahun'], $aktif['diaktifkan_oleh']]);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+07:00$/', $aktif['diaktifkan_pada']);
        $this->assertSame([false, true], array_column($repo->semua(), 'is_active'), 'Tepat satu tahun aktif.');
        $this->assertTrue($aktif['is_locked'], 'Mengaktifkan tidak membuka kunci tahun.');

        $repo->simpanSk(2026, '012/SK/RKTM/2026', '2026-01-07');
        $tahun2026 = collect($repo->semua())->firstWhere('tahun', 2026);
        $this->assertSame(['012/SK/RKTM/2026', '2026-01-07'], [$tahun2026['no_sk_rktm'], $tahun2026['tanggal_sk_rktm']]);
        $repo->simpanSk(2026, null, null);
        $this->assertNull(collect($repo->semua())->firstWhere('tahun', 2026)['no_sk_rktm']);

        foreach ([fn () => $repo->aktifkan(2031, 'Admin'), fn () => $repo->simpanSk(2031, null, null)] as $aksi) {
            try {
                $aksi();
                $this->fail('Tahun yang belum dibuat seharusnya ditolak.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_database_menolak_sk_tanpa_tanggal_dan_dipakai_kontrol_tahun_aktif(): void
    {
        $repo = $this->repo(true);
        try {
            DB::transaction(fn () => $repo->simpanSk(2026, '012/SK/RKTM/2026', null));
            $this->fail('SK tanpa tanggal seharusnya ditolak.');
        } catch (RuntimeException) {
            $this->assertNull($repo->aktif()['no_sk_rktm']);
        }

        $this->assertTrue(KontrolTahunAktif::aktifkan(2025, StubData::currentUser()));
        $this->assertSame(2025, StubData::tahunAktif()['tahun'], 'Tahun aktif aplikasi dibaca dari database.');
        $this->assertFalse(KontrolTahunAktif::aktifkan(2025, StubData::currentUser()), 'Tahun yang sudah aktif tidak diaktifkan ulang.');
    }
}
