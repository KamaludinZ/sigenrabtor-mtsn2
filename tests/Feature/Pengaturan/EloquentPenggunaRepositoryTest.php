<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\PenggunaRepository;
use App\Livewire\Pengaturan\LeadingUser;
use App\Repositories\EloquentPenggunaRepository;
use App\Support\Stub\StubLeadingRepository;
use App\Support\Stub\StubPenggunaRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class EloquentPenggunaRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    /** Repositori pilihan; database diisi leading & akun contoh lewat simpan() (id, waktu dibuat & masuk sama). */
    private function repo(bool $database): PenggunaRepository
    {
        if (! $database) {
            return app(PenggunaRepository::class);
        }
        DB::table('leadings')->insert(StubLeadingRepository::contoh());
        $this->app->singleton(PenggunaRepository::class, EloquentPenggunaRepository::class);
        foreach (StubPenggunaRepository::contoh() as $akun) {
            app(PenggunaRepository::class)->simpan($akun, $akun['id']);
        }
        DB::statement("SELECT setval(pg_get_serial_sequence('users', 'id'), (SELECT max(id) FROM users))");

        return app(PenggunaRepository::class);
    }

    #[DataProvider('penyimpanan')]
    public function test_akun_contoh_simpan_dan_riwayat_masuk(bool $database): void
    {
        $repo = $this->repo($database);
        $this->assertSame((new StubPenggunaRepository)->semua(), $repo->semua(), 'Bentuk sama dengan tiruan.');
        $this->assertSame('Ahmad Fauzan, S.Pd.', $repo->temukan(4)['name']);
        $this->assertNull($repo->temukan(99));

        $id = $repo->simpan(['name' => 'Laila Fitri, S.Pd.', 'email' => 'laila@example.com', 'role' => 'leading', 'nip' => null, 'jabatan' => 'Koordinator Literasi', 'leadings' => ['1.4', '1.2'], 'is_active' => true]);
        $this->assertSame(7, $id);
        $baru = $repo->temukan(7);
        $this->assertSame(['1.2', '1.4'], $baru['leadings'], 'Tautan urut alami.');
        $this->assertNull($baru['terakhir_masuk']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+07:00$/', $baru['dibuat_pada']);

        $repo->simpan(['role' => 'admin', 'jabatan' => 'Admin Literasi'], 7);
        $this->assertSame(['admin', [], 'Laila Fitri, S.Pd.', 'Admin Literasi'], [$repo->temukan(7)['role'], $repo->temukan(7)['leadings'], $repo->temukan(7)['name'], $repo->temukan(7)['jabatan']], 'Admin tanpa tautan; isian lain tetap.');

        $this->assertSame([], $repo->riwayatMasuk(1));
        $repo->catatMasuk(1, '10.0.0.5', 'Mozilla/5.0 Uji');
        $this->travel(5)->minutes();
        $repo->catatMasuk(1, '10.0.0.6', null);
        $riwayat = $repo->riwayatMasuk(1);
        $this->assertSame([['10.0.0.6', null], ['10.0.0.5', 'Mozilla/5.0 Uji']], array_map(fn (array $r) => [$r['alamat_ip'], $r['agen']], $riwayat), 'Terbaru dulu.');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}\+07:00$/', $riwayat[0]['waktu']);
        $this->assertSame(now()->toIso8601String(), $repo->temukan(1)['terakhir_masuk']);
        $this->assertCount(1, $repo->riwayatMasuk(1, 1));
        $repo->catatMasuk(99);
        $this->assertSame([], $repo->riwayatMasuk(99));
    }

    public function test_aturan_akun_di_database(): void
    {
        $repo = $this->repo(true);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');

        foreach ([
            fn () => $repo->simpan(['name' => 'Ganda', 'email' => 'MUSLIMIN@example.com', 'leadings' => ['1.6']]),
            fn () => $repo->simpan(['name' => '  ', 'email' => 'kosong@example.com', 'leadings' => ['1.6']]),
            fn () => $repo->simpan(['name' => 'Nama  Ganda Spasi', 'email' => 'spasi@example.com', 'leadings' => ['1.6']]),
            fn () => $repo->simpan(['name' => 'Tanpa Leading', 'email' => 'x@example.com', 'leadings' => ['9.9']]),
        ] as $aksi) {
            try {
                $aksi();
                $this->fail('Seharusnya ditolak database.');
            } catch (RuntimeException $e) {
                $this->assertStringStartsWith('Akun ', $e->getMessage());
            }
        }

        $repo->simpan(['is_active' => false], 2);
        try {
            $repo->simpan(['is_active' => false], 5);
            $this->fail('Admin aktif terakhir tidak boleh dinonaktifkan.');
        } catch (RuntimeException) {
            $this->assertTrue($repo->temukan(5)['is_active']);
        }

        $this->expectException(QueryException::class);
        DB::table('user_leading')->insert(['user_id' => 5, 'kode_leading' => '1.1']);
    }

    public function test_halaman_akun_dan_masuk_memakai_repositori_database(): void
    {
        $this->repo(true);
        $this->withSession(['stub_role' => 'admin']);

        Livewire::test(LeadingUser::class)
            ->call('tambahAkun')
            ->set('isianAkun', ['name' => 'Laila Fitri, S.Pd.', 'email' => 'Laila@Example.com', 'role' => 'leading', 'jabatan' => '', 'nip' => '', 'leadings' => ['1.6']])
            ->call('simpanAkun')
            ->assertHasNoErrors();
        $this->assertSame(['laila@example.com', '1.6'], [DB::table('users')->where('name', 'Laila Fitri, S.Pd.')->value('email'), DB::table('user_leading')->where('user_id', DB::table('users')->where('name', 'Laila Fitri, S.Pd.')->value('id'))->value('kode_leading')]);

        $this->get('/demo/peran/leading', ['User-Agent' => 'Peramban Uji'])->assertRedirect();
        $this->assertSame([['Muslimin, M.Pd.', 'Peramban Uji']], DB::table('riwayat_masuk')->get(['pengguna', 'agen'])->map(fn ($r) => [$r->pengguna, $r->agen])->all());
    }

    #[DataProvider('penyimpanan')]
    public function test_sandi_dan_ganti_sandi(bool $database): void
    {
        $repo = $this->repo($database);
        // Akun database selalu bersandi (acak saat dibuat); akun contoh tiruan belum punya sandi.
        $this->assertSame($database, $repo->sandi(4) !== null);
        $this->assertNull($repo->sandi(99));

        $repo->gantiSandi(4, Hash::make('Madrasah#2026'));
        $sandi = $repo->sandi(4);
        $this->assertTrue(Hash::check('Madrasah#2026', $sandi['hash']));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}\+07:00$/', $sandi['diganti_pada']);

        $repo->simpan(['name' => 'Ahmad Fauzan, S.Pd., M.Pd.'], 4);
        $this->assertTrue(Hash::check('Madrasah#2026', $repo->sandi(4)['hash']), 'Mengubah akun tidak mengganti sandi.');

        $this->expectException(RuntimeException::class);
        $repo->gantiSandi(99, Hash::make('x'));
    }
}
