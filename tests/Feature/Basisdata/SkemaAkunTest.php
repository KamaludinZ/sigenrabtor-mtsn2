<?php

namespace Tests\Feature\Basisdata;

use App\Contracts\PermohonanAkunRepository;
use App\Repositories\EloquentPermohonanAkunRepository;
use App\Support\Akun\PendaftaranAkun;
use App\Support\Akun\PeranAkun;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SkemaAkunTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_30_000048_create_peran_dan_permohonan_akun_tables.php';

    private const ISIAN = [
        'nama' => 'Hasan Basri, S.Pd.', 'jabatan' => 'Koordinator Adiwiyata', 'nip' => '198503122010011004', 'leadings' => ['1.4', '1.2'],
        'surel' => 'hasan.basri@example.com', 'sandi' => 'Madrasah#2026', 'sandi_ulang' => 'Madrasah#2026', 'catatan' => 'Untuk Adiwiyata.', 'setuju' => true,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        RujukanDb::siapkan();
    }

    /** @param  array<string, mixed>  $ubah */
    private function permohonan(array $ubah = []): array
    {
        return $ubah + ['id' => (string) Str::uuid(), 'nama' => 'Hasan Basri, S.Pd.', 'surel' => 'hasan@example.com', 'sandi_hash' => Hash::make('Madrasah#2026'), 'status' => 'menunggu', 'diajukan_pada' => now()->format('Y-m-d H:i:s.uP')];
    }

    /** @param  array<string, mixed>  $baris */
    private function ditolak(string $tabel, array $baris, string $pesan): void
    {
        try {
            DB::transaction(fn () => DB::table($tabel)->insert($baris));
            $this->fail('Seharusnya ditolak: '.json_encode($baris));
        } catch (QueryException $e) {
            $this->assertStringContainsString($pesan, $e->getMessage());
        }
    }

    public function test_rujukan_peran_sama_dengan_peran_akun(): void
    {
        $this->assertSame(
            array_map(fn (array $p) => [$p['label'], $p['keterangan'], $p['perlu_leading']], PeranAkun::DAFTAR),
            DB::table('peran')->orderBy('urutan')->get()->mapWithKeys(fn ($r) => [$r->kode => [$r->label, $r->keterangan, (bool) $r->perlu_leading]])->all(),
        );
        $this->assertSame(PeranAkun::LABEL, array_map(fn (array $p) => $p['label'], PeranAkun::DAFTAR));
        try {
            DB::transaction(fn () => DB::table('users')->where('id', 3)->update(['role' => 'tamu']));
            $this->fail('Peran tak terdaftar seharusnya ditolak.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('users_role_foreign', $e->getMessage());
        }
    }

    public function test_batasan_permohonan_akun(): void
    {
        DB::table('permohonan_akun')->insert($p = $this->permohonan());
        DB::table('permohonan_akun_leading')->insert([['permohonan_id' => $p['id'], 'kode_leading' => '1.2'], ['permohonan_id' => $p['id'], 'kode_leading' => '1.4']]);

        $this->ditolak('permohonan_akun', $this->permohonan(), 'permohonan_akun_satu_menunggu');
        $this->ditolak('permohonan_akun', $this->permohonan(['surel' => 'puskom@example.com']), 'sudah terdaftar sebagai akun');
        $this->ditolak('permohonan_akun', $this->permohonan(['surel' => 'Besar@Example.com']), 'permohonan_akun_isian_check');
        $this->ditolak('permohonan_akun', $this->permohonan(['surel' => 'bukan-surel']), 'permohonan_akun_isian_check');
        $this->ditolak('permohonan_akun', $this->permohonan(['surel' => 'a@b.id', 'nama' => 'Dua  Spasi']), 'permohonan_akun_isian_check');
        $this->ditolak('permohonan_akun', $this->permohonan(['surel' => 'a@b.id', 'nama' => '12345']), 'permohonan_akun_isian_check');
        $this->ditolak('permohonan_akun', $this->permohonan(['surel' => 'a@b.id', 'nip' => '1985']), 'permohonan_akun_isian_check');
        $this->ditolak('permohonan_akun', $this->permohonan(['surel' => 'a@b.id', 'sandi_hash' => 'Madrasah#2026']), 'permohonan_akun_isian_check');
        $this->ditolak('permohonan_akun', $this->permohonan(['surel' => 'a@b.id', 'catatan' => str_repeat('a', PendaftaranAkun::MAKS_CATATAN + 1)]), 'permohonan_akun_isian_check');
        $this->ditolak('permohonan_akun', $this->permohonan(['surel' => 'a@b.id', 'status' => 'disetujui']), 'permohonan_akun_status_check');
        $this->ditolak('permohonan_akun', $this->permohonan(['surel' => 'a@b.id', 'akun_id' => 3]), 'permohonan_akun_status_check');
        $this->ditolak('permohonan_akun_leading', ['permohonan_id' => $p['id'], 'kode_leading' => '9.9'], 'foreign key');

        // Permohonan yang sudah diputuskan tidak menghalangi permohonan baru bersurel sama.
        DB::table('permohonan_akun')->where('id', $p['id'])->update(['status' => 'ditolak', 'diputuskan_pada' => now()->format('Y-m-d H:i:s.uP'), 'diputuskan_oleh' => 2, 'alasan' => 'Bukan pegawai.']);
        DB::table('permohonan_akun')->insert($this->permohonan());
        $this->assertSame(2, DB::table('permohonan_akun')->where('surel', 'hasan@example.com')->count());

        // Hapus permohonan → leading ikut terhapus; kode leading ikut berganti.
        DB::table('leadings')->where('kode', '1.2')->update(['kode' => '1.20']);
        $this->assertSame(['1.20', '1.4'], DB::table('permohonan_akun_leading')->orderBy('kode_leading')->pluck('kode_leading')->all());
        DB::table('permohonan_akun')->where('id', $p['id'])->delete();
        $this->assertSame(0, DB::table('permohonan_akun_leading')->count());
    }

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    #[DataProvider('penyimpanan')]
    public function test_repositori_permohonan_bentuk_sama(bool $database): void
    {
        if ($database) {
            $this->app->singleton(PermohonanAkunRepository::class, EloquentPermohonanAkunRepository::class);
        }
        $hasil = PendaftaranAkun::ajukan(self::ISIAN);
        $tersimpan = app(PermohonanAkunRepository::class)->temukan($hasil['id']);

        $this->assertSame(['id', 'nama', 'surel', 'jabatan', 'nip', 'leadings', 'catatan', 'sandi_hash', 'status', 'diajukan_pada', 'diputuskan_pada', 'diputuskan_oleh', 'alasan', 'akun_id'], array_keys($tersimpan));
        $this->assertSame(['Hasan Basri, S.Pd.', 'hasan.basri@example.com', ['1.2', '1.4'], 'menunggu', null], [$tersimpan['nama'], $tersimpan['surel'], $tersimpan['leadings'], $tersimpan['status'], $tersimpan['diputuskan_oleh']]);
        $this->assertTrue(Hash::check('Madrasah#2026', $tersimpan['sandi_hash']));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}\+07:00$/', $tersimpan['diajukan_pada']);
        $this->assertSame($hasil['id'], app(PermohonanAkunRepository::class)->semua()[0]['id']);
        $this->assertNull(app(PermohonanAkunRepository::class)->temukan('bukan-uuid'));

        // Surel menunggu dikenali aturan pendaftaran (galat), dan database menolaknya juga.
        $this->assertArrayHasKey('surel', PendaftaranAkun::galat(PendaftaranAkun::rapikan(self::ISIAN)));
        if ($database) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('sedang menunggu persetujuan admin');
            app(PermohonanAkunRepository::class)->ajukan(['nama' => 'Hasan', 'surel' => 'hasan.basri@example.com', 'leadings' => []]);
        }
    }

    public function test_migrasi_bisa_dibatalkan(): void
    {
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('permohonan_akun'));
        $this->assertFalse(Schema::hasTable('peran'));
        $this->assertNotNull(DB::selectOne("SELECT 1 AS ada FROM pg_constraint WHERE conname = 'users_role_check'"));
        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('permohonan_akun'));
    }
}
