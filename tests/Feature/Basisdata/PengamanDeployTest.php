<?php

namespace Tests\Feature\Basisdata;

use App\Support\Basisdata\PengamanDeploy;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class PengamanDeployTest extends TestCase
{
    use RefreshDatabase;

    private string $rilis;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->rilis = tempnam(sys_get_temp_dir(), 'rilis');
        file_put_contents($this->rilis, "20261031T010000Z abc123\n");
        config(['sigenrabtor.deploy.berkas_rilis' => $this->rilis, 'sigenrabtor.deploy.cadangan_pra_migrasi' => true]);
    }

    protected function tearDown(): void
    {
        @unlink($this->rilis);
        parent::tearDown();
    }

    /** pg_dump/tar palsu: membuat berkas tujuan (--file=…) lalu berhasil. */
    private function prosesBerhasil(): void
    {
        Process::fake(function (PendingProcess $proses) {
            foreach ((array) $proses->command as $argumen) {
                if (str_starts_with($argumen, '--file=')) {
                    file_put_contents(substr($argumen, 7), 'isi');
                }
            }

            return Process::result();
        });
    }

    public function test_instalasi_baru_ditandai_lalu_redeploy_biasa_lolos(): void
    {
        $this->assertSame([], PengamanDeploy::periksa());
        $this->assertNull(PengamanDeploy::perluCadangan(), 'Tanpa migrasi tertunda & tanpa rilis tercatat.');

        $id = PengamanDeploy::tandai();
        $this->assertSame($id, DB::table('instalasi')->value('id'));
        $penanda = PengamanDeploy::penanda();
        $this->assertSame([$id, PengamanDeploy::sidikAppKey(), '20261031T010000Z abc123'], [$penanda['id'], $penanda['sidik_app_key'], $penanda['rilis']]);
        $this->assertStringNotContainsString((string) config('app.key'), (string) Storage::disk('local')->get(PengamanDeploy::PENANDA), 'Hanya sidik, bukan APP_KEY.');

        // Redeploy rilis yang sama: aman, tanpa cadangan; id tetap.
        $this->assertSame([], PengamanDeploy::periksa());
        $this->assertNull(PengamanDeploy::perluCadangan());
        $this->assertSame($id, PengamanDeploy::tandai());
        $this->assertSame(1, DB::table('instalasi')->count());
    }

    public function test_app_key_berganti_dihentikan(): void
    {
        PengamanDeploy::tandai();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
        $this->assertStringStartsWith('APP_KEY berbeda dengan saat data di volume ini dibuat', PengamanDeploy::periksa()[0]);

        config(['sigenrabtor.deploy.izinkan_ganti_app_key' => true]);
        $this->assertSame([], PengamanDeploy::periksa());
        PengamanDeploy::tandai();
        config(['sigenrabtor.deploy.izinkan_ganti_app_key' => false]);
        $this->assertSame([], PengamanDeploy::periksa(), 'Sidik baru tercatat sesudah deploy yang diizinkan.');
    }

    public function test_volume_atau_database_yang_tidak_berpasangan_dihentikan(): void
    {
        $id = PengamanDeploy::tandai();

        // Volume baru/kosong di atas database lama (volume tidak terpasang atau namanya berubah).
        Storage::disk('local')->delete(PengamanDeploy::PENANDA);
        $this->assertStringStartsWith('Volume storage kosong/baru, padahal database sudah dipakai instalasi '.$id, PengamanDeploy::periksa()[0]);
        config(['sigenrabtor.deploy.izinkan_data_baru' => true]);
        $this->assertSame([], PengamanDeploy::periksa());
        $this->assertSame($id, PengamanDeploy::tandai(), 'Volume yang dipulihkan mengikuti id database.');
        config(['sigenrabtor.deploy.izinkan_data_baru' => false]);

        // Volume & database dari instalasi berbeda.
        Storage::disk('local')->put(PengamanDeploy::PENANDA, json_encode(['id' => 'instalasi-lain', 'sidik_app_key' => PengamanDeploy::sidikAppKey()]));
        $this->assertStringContainsString('berasal dari instalasi berbeda', PengamanDeploy::periksa()[0]);

        // Database kosong di bawah volume lama.
        PengamanDeploy::tandai();
        Storage::disk('local')->put(PengamanDeploy::PENANDA, json_encode(['id' => $id, 'sidik_app_key' => PengamanDeploy::sidikAppKey()]));
        DB::table('instalasi')->delete();
        $this->assertSame([], PengamanDeploy::periksa(), 'Database berisi tanpa id (versi lama) dianggap pembaruan biasa.');
        DB::statement('ALTER TABLE migrations RENAME TO migrations_sementara');
        DB::statement('ALTER TABLE instalasi RENAME TO instalasi_sementara');
        $this->assertStringStartsWith('Database kosong/baru, padahal volume storage berisi data instalasi '.$id, PengamanDeploy::periksa()[0]);
        DB::statement('ALTER TABLE instalasi_sementara RENAME TO instalasi');
        DB::statement('ALTER TABLE migrations_sementara RENAME TO migrations');
    }

    public function test_cadangan_saat_migrasi_tertunda_atau_rilis_baru(): void
    {
        PengamanDeploy::tandai();
        file_put_contents($this->rilis, "20261101T010000Z def456\n");
        $this->assertSame('rilis-baru', PengamanDeploy::perluCadangan());

        $migrasi = DB::table('migrations')->orderByDesc('id')->first();
        DB::table('migrations')->where('id', $migrasi->id)->delete();
        $this->assertSame(1, PengamanDeploy::migrasiTertunda());
        $this->assertSame('pra-migrasi', PengamanDeploy::perluCadangan());

        config(['sigenrabtor.deploy.cadangan_pra_migrasi' => false]);
        $this->assertNull(PengamanDeploy::perluCadangan());
    }

    public function test_cadangan_database_dan_data_dengan_retensi(): void
    {
        $this->prosesBerhasil();
        Storage::disk('local')->put('stub/kegiatan.json', '[]');
        config(['sigenrabtor.deploy.cadangan_simpan' => 2]);

        $hasil = PengamanDeploy::cadangan('Pra Migrasi');
        $this->assertMatchesRegularExpression('#^cadangan/\d{8}-\d{6}-pra-migrasi$#', $hasil['folder']);
        $this->assertSame(['database.dump', 'data.tgz'], array_keys($hasil['berkas']));
        $info = json_decode((string) Storage::disk('local')->get($hasil['folder'].'/info.json'), true);
        $this->assertSame(['pra-migrasi', '20261031T010000Z abc123'], [$info['alasan'], $info['rilis']]);

        $db = config('database.connections.'.config('database.default'));
        Process::assertRan(fn (PendingProcess $p) => $p->command[0] === 'pg_dump' && in_array('--format=custom', $p->command, true)
            && in_array('--dbname='.$db['database'], $p->command, true) && $p->environment['PGPASSWORD'] === (string) $db['password']);
        Process::assertRan(fn (PendingProcess $p) => $p->command[0] === 'tar' && in_array('--exclude=./private/cadangan', $p->command, true));

        $this->travel(2)->seconds();
        PengamanDeploy::cadangan('harian');
        $this->travel(2)->seconds();
        PengamanDeploy::cadangan('harian');
        $daftar = PengamanDeploy::daftar();
        $this->assertCount(2, $daftar, 'Set terlama dibuang.');
        $this->assertStringEndsWith('-harian', $daftar[0]['folder']);
        $this->assertFalse(Storage::disk('local')->exists($hasil['folder']));
    }

    public function test_cadangan_gagal_menghentikan_migrasi(): void
    {
        Process::fake(fn () => Process::result(errorOutput: 'pg_dump: error: server version mismatch', exitCode: 1));
        try {
            PengamanDeploy::cadangan('pra-migrasi');
            $this->fail('Cadangan gagal harus dilaporkan.');
        } catch (RuntimeException $e) {
            $this->assertSame('Cadangan gagal (pg_dump): pg_dump: error: server version mismatch', $e->getMessage());
        }
        $this->assertSame([], Storage::disk('local')->directories(PengamanDeploy::FOLDER_CADANGAN), 'Set yang gagal dibuang.');

        // Lewat perintah: migrasi tidak dijalankan.
        PengamanDeploy::tandai();
        DB::table('migrations')->where('id', DB::table('migrations')->max('id'))->delete();
        $this->artisan('sigenrabtor:siapkan-deploy')
            ->expectsOutputToContain('Cadangan gagal (pg_dump)')
            ->expectsOutputToContain('Migrasi tidak dijalankan')
            ->assertFailed();
    }

    public function test_perintah_siapkan_deploy_dan_cadangan(): void
    {
        $this->prosesBerhasil();
        $this->artisan('sigenrabtor:siapkan-deploy')->expectsOutputToContain('siap.')->assertSuccessful();
        $id = DB::table('instalasi')->value('id');
        $this->assertSame($id, PengamanDeploy::penanda()['id']);

        // Rilis baru → cadangan dulu, lalu migrasi.
        file_put_contents($this->rilis, "20261101T010000Z def456\n");
        $this->artisan('sigenrabtor:siapkan-deploy')->expectsOutputToContain('Cadangan rilis-baru dibuat')->assertSuccessful();
        $this->assertSame('20261101T010000Z def456', PengamanDeploy::penanda()['rilis']);

        // Pengaman: volume kosong di atas database lama → berhenti sebelum migrasi.
        Storage::disk('local')->delete(PengamanDeploy::PENANDA);
        $this->artisan('sigenrabtor:siapkan-deploy')->expectsOutputToContain('Volume storage kosong/baru')->assertFailed();

        $this->artisan('sigenrabtor:cadangan', ['--alasan' => 'manual'])->expectsOutputToContain('Cadangan dibuat: storage/app/private/cadangan/')->assertSuccessful();
        $this->artisan('sigenrabtor:cadangan', ['--daftar' => true])->expectsOutputToContain('manual')->assertSuccessful();
    }

    public function test_cadangan_harian_terjadwal_bila_diaktifkan(): void
    {
        $acara = collect(app(Schedule::class)->events())->first(fn ($a) => str_contains((string) $a->command, 'sigenrabtor:cadangan --alasan=harian'));
        $this->assertNotNull($acara);
        $this->assertSame('30 1 * * *', $acara->expression);
        config(['sigenrabtor.deploy.cadangan_harian' => false]);
        $this->assertFalse($acara->filtersPass($this->app));
        config(['sigenrabtor.deploy.cadangan_harian' => true]);
        $this->assertTrue($acara->filtersPass($this->app));
    }
}
