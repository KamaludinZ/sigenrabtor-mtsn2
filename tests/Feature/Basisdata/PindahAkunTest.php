<?php

namespace Tests\Feature\Basisdata;

use App\Contracts\PenggunaRepository;
use App\Repositories\EloquentPenggunaRepository;
use App\Support\Basisdata\PindahAkun;
use App\Support\Basisdata\PindahLeading;
use Database\Seeders\AkunAdminSeeder;
use Database\Seeders\LeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class PindahAkunTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_awal_hanya_bila_belum_ada_admin_aktif(): void
    {
        config(['sigenrabtor.admin_awal' => ['nama' => 'Admin Madrasah', 'email' => 'Admin@MTsN2.example']]);
        $this->seed();
        $this->assertSame([['Admin Madrasah', 'admin@mtsn2.example', 'admin', true]], DB::table('users')->get(['name', 'email', 'role', 'is_active'])->map(fn ($u) => [$u->name, $u->email, $u->role, (bool) $u->is_active])->all());
        $this->assertSame(8, DB::table('leadings')->count(), 'DatabaseSeeder: leading lalu admin.');

        $this->seed(AkunAdminSeeder::class);
        $this->assertSame(1, DB::table('users')->count(), 'Tidak menggandakan.');
        $this->assertNotSame('', (string) DB::table('users')->value('password'), 'Sandi acak tersimpan (tidak pernah ditampilkan).');
    }

    public function test_sinkron_akun_ke_database_berisi(): void
    {
        $this->seed(LeadingSeeder::class);
        PindahLeading::jalankan();
        DB::table('users')->insert(['id' => 50, 'name' => 'Admin Khusus Database', 'email' => 'khusus@example.com', 'password' => '-', 'role' => 'admin']);
        app(PenggunaRepository::class)->catatMasuk(1, '10.0.0.5', 'Peramban Uji');

        $uji = PindahAkun::jalankan(uji: true);
        $this->assertSame([1, 2, 3, 4, 5, 6], $uji['tambah']);
        $this->assertSame(1, DB::table('users')->count(), 'Mode uji tidak menyimpan.');

        $hasil = PindahAkun::jalankan();
        $this->assertSame([[1, 2, 3, 4, 5, 6], [50], 1], [$hasil['tambah'], $hasil['hanya_database'], $hasil['riwayat_masuk']]);
        $db = new EloquentPenggunaRepository;
        foreach (app(PenggunaRepository::class)->semua() as $akun) {
            $this->assertSame($akun, $db->temukan($akun['id']));
        }
        $this->assertSame([['10.0.0.5', 'Peramban Uji']], array_map(fn (array $r) => [$r['alamat_ip'], $r['agen']], $db->riwayatMasuk(1)));
        $this->assertSame(51, $db->simpan(['name' => 'Akun Baru', 'email' => 'baru@example.com', 'leadings' => ['1.1']]), 'Urutan id dilanjutkan.');

        app(PenggunaRepository::class)->simpan(['name' => 'Muslimin, S.Pd., M.Pd.'], 1);
        $lagi = PindahAkun::jalankan();
        $this->assertSame([[], [1], [2, 3, 4, 5, 6], 0], [$lagi['tambah'], $lagi['ubah'], $lagi['tetap'], $lagi['riwayat_masuk']]);
        $this->assertSame('Muslimin, S.Pd., M.Pd.', $db->temukan(1)['name']);

        $this->artisan('sigenrabtor:pindah-akun', ['--uji' => true])->assertSuccessful()->expectsOutputToContain('Uji sinkron akun lolos verifikasi');
    }

    public function test_leading_belum_ada_atau_surel_bentrok_membatalkan(): void
    {
        $this->seed(LeadingSeeder::class);
        app(PenggunaRepository::class)->simpan(['leadings' => ['1.9']], 4);
        try {
            PindahAkun::jalankan();
            $this->fail('Leading 1.9 belum ada di database.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('jalankan sigenrabtor:pindah-leading dulu', $e->getMessage());
        }

        PindahLeading::jalankan();
        DB::table('users')->insert(['id' => 50, 'name' => 'Pemilik Surel', 'email' => 'muslimin@example.com', 'password' => '-', 'role' => 'admin']);
        try {
            PindahAkun::jalankan();
            $this->fail('Surel akun 1 sudah dipakai akun database lain.');
        } catch (RuntimeException) {
            $this->assertSame(1, DB::table('users')->count(), 'Tidak ada yang tersimpan.');
        }
        $this->artisan('sigenrabtor:pindah-akun')->assertFailed();
    }
}
