<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\LeadingRepository;
use App\Livewire\Pengaturan\LeadingUser;
use App\Repositories\EloquentLeadingRepository;
use App\Support\MasterKode;
use App\Support\Stub\StubLeadingRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class EloquentLeadingRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    private function repo(bool $database): LeadingRepository
    {
        if ($database) {
            RujukanDb::siapkan();
            $this->app->singleton(LeadingRepository::class, EloquentLeadingRepository::class);
        }

        return app(LeadingRepository::class);
    }

    #[DataProvider('penyimpanan')]
    public function test_contoh_tambah_ubah_dan_urutan(bool $database): void
    {
        $repo = $this->repo($database);
        $this->assertSame(StubLeadingRepository::contoh(), $repo->semua());

        $repo->simpan(['kode' => '1.10', 'nama_unit' => 'Literasi', 'jabatan_penanda_tangan' => 'Koordinator Literasi']);
        $baru = collect($repo->semua())->firstWhere('kode', '===', '1.10');
        $this->assertSame(['kode' => '1.10', 'nama_unit' => 'Literasi', 'jabatan_penanda_tangan' => 'Koordinator Literasi', 'urutan' => 10, 'is_active' => true], $baru, 'Urutan bawaan = terbesar + 1.');
        $this->assertNotNull(collect($repo->semua())->firstWhere('kode', '===', '1.1'), '"1.10" ≠ "1.1".');

        $repo->simpan(['kode' => '1.10', 'nama_unit' => 'Literasi Madrasah', 'jabatan_penanda_tangan' => 'Koordinator Literasi', 'urutan' => 0], '1.10');
        $repo->simpan(['kode' => '1.8', 'nama_unit' => 'PMM', 'jabatan_penanda_tangan' => 'Koordinator PMM', 'is_active' => false], '1.8');
        $semua = $repo->semua();
        $this->assertSame(['1.10', '1.1'], array_slice(array_column($semua, 'kode'), 0, 2), 'Urut urutan.');
        $this->assertFalse(collect($semua)->firstWhere('kode', '===', '1.8')['is_active']);
        $this->assertSame(8, collect($semua)->firstWhere('kode', '===', '1.8')['urutan'], 'Urutan lama tetap bila tidak diisi.');
        $this->assertNotContains('1.8', array_column(MasterKode::leadings(), 'kode'), 'Pilihan baru hanya leading aktif.');
    }

    public function test_bentuk_database_dan_aturan_kolom(): void
    {
        $repo = $this->repo(true);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');

        foreach ([
            fn () => $repo->simpan(['kode' => '01.2', 'nama_unit' => 'Unit', 'jabatan_penanda_tangan' => 'Ketua']),
            fn () => $repo->simpan(['kode' => '1.x', 'nama_unit' => 'Unit', 'jabatan_penanda_tangan' => 'Ketua']),
            fn () => $repo->simpan(['kode' => '2.1', 'nama_unit' => 'waka kurikulum', 'jabatan_penanda_tangan' => 'Ketua']),
            fn () => $repo->simpan(['kode' => '2.1', 'nama_unit' => 'Unit  Baru', 'jabatan_penanda_tangan' => 'Ketua']),
            fn () => $repo->simpan(['kode' => '2.1', 'nama_unit' => 'Unit Baru', 'jabatan_penanda_tangan' => ' ']),
            fn () => $repo->simpan(['kode' => '2.1', 'nama_unit' => 'Unit Baru', 'jabatan_penanda_tangan' => 'Ketua', 'urutan' => 1000]),
            fn () => $repo->simpan(['kode' => '1.6', 'nama_unit' => 'Waka Kesiswaan', 'jabatan_penanda_tangan' => 'Ketua'], '1.6'),
        ] as $aksi) {
            try {
                $aksi();
                $this->fail('Seharusnya ditolak database.');
            } catch (RuntimeException $e) {
                $this->assertStringStartsWith('Leading ', $e->getMessage());
            }
        }
        $this->assertSame(StubLeadingRepository::contoh(), $repo->semua(), 'Data lama utuh.');

        // Minimal satu leading aktif: menonaktifkan semuanya ditolak database.
        DB::table('leadings')->where('kode', '!=', '1.1')->update(['is_active' => false]);
        $this->expectException(QueryException::class);
        DB::table('leadings')->where('kode', '1.1')->update(['is_active' => false]);
    }

    public function test_halaman_leading_memakai_repositori_database(): void
    {
        $this->repo(true);
        $this->withSession(['stub_role' => 'admin']);

        Livewire::test(LeadingUser::class)
            ->call('tambahLeading')
            ->set('isianLeading', ['kode' => ' 1 . 10 ', 'nama_unit' => '  Literasi   Madrasah ', 'jabatan_penanda_tangan' => 'Koordinator Literasi', 'urutan' => '10'])
            ->call('simpanLeading')
            ->assertHasNoErrors();
        $this->assertSame(['Literasi Madrasah', 10], [DB::table('leadings')->where('kode', '1.10')->value('nama_unit'), (int) DB::table('leadings')->where('kode', '1.10')->value('urutan')]);
    }
}
