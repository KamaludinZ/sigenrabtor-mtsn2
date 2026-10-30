<?php

namespace Tests\Feature\Akun;

use App\Contracts\PenggunaRepository;
use App\Support\Akun\Autentikasi;
use App\Support\Akun\SandiKonsol;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class SandiKonsolTest extends TestCase
{
    public function test_atur_sandi_akun_yang_ada_lalu_bisa_masuk_tanpa_mode_demo(): void
    {
        config(['sigenrabtor.akun.mode_demo' => false]);
        $repo = app(PenggunaRepository::class);
        $this->assertSame('Akun ini belum punya sandi — minta admin mengatur sandi akun Anda.', Autentikasi::periksa('puskom@example.com', 'Server#2026', '10.0.0.1')['galat']['sandi']);

        $hasil = SandiKonsol::atur('  PUSKOM@example.com ', 'Server#2026');

        $this->assertFalse($hasil['dibuat']);
        $this->assertSame(['id' => 2, 'nama' => 'Koordinator PUSKOM', 'surel' => 'puskom@example.com', 'peran' => 'admin', 'aktif' => true], $hasil['akun']);
        $this->assertTrue(Hash::check('Server#2026', $repo->sandi(2)['hash']));
        $this->assertStringNotContainsString('Server#2026', (string) Storage::disk('local')->get('stub/pengguna.json'));
        $this->assertSame([], Autentikasi::periksa('puskom@example.com', 'Server#2026', '10.0.0.1')['galat']);
    }

    public function test_buat_admin_pertama_dan_aturan_isian(): void
    {
        $repo = app(PenggunaRepository::class);
        $jumlah = count($repo->semua());

        try {
            SandiKonsol::atur('kepala@madrasah.sch.id', 'Server#2026');
            $this->fail('Akun yang tidak ada tanpa --buat-admin harus ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('--buat-admin', $e->getMessage());
        }
        $galat = function (callable $aksi): array {
            try {
                $aksi();
            } catch (ValidationException $e) {
                return array_map(fn (array $pesan) => $pesan[0], $e->errors());
            }

            return [];
        };
        $this->assertSame(['nama' => 'Isi nama admin dengan --nama="…".'], $galat(fn () => SandiKonsol::atur('kepala@madrasah.sch.id', 'Server#2026', buatAdmin: true)));
        $this->assertSame(['surel' => 'Surel tidak sah, mis. nama@madrasah.sch.id.'], $galat(fn () => SandiKonsol::atur('bukan-surel', 'Server#2026', 'Admin', true)));
        $this->assertSame(['sandi' => 'Sandi minimal 8 karakter.'], $galat(fn () => SandiKonsol::atur('kepala@madrasah.sch.id', 'Ab1', 'Admin Madrasah', true)));
        $this->assertSame(['sandi' => 'Sandi tidak boleh memuat nama atau surel Anda.'], $galat(fn () => SandiKonsol::atur('kepala@madrasah.sch.id', 'kepala2026', 'Admin Madrasah', true)));
        $this->assertCount($jumlah, $repo->semua());

        $hasil = SandiKonsol::atur('Kepala@Madrasah.sch.id', 'Server#2026', '  Admin   Madrasah ', true);

        $this->assertTrue($hasil['dibuat']);
        $this->assertSame(['nama' => 'Admin Madrasah', 'surel' => 'kepala@madrasah.sch.id', 'peran' => 'admin', 'aktif' => true], array_diff_key($hasil['akun'], ['id' => 0]));
        $akun = $repo->temukan($hasil['akun']['id']);
        $this->assertSame([], $akun['leadings']);
        $this->assertTrue(Hash::check('Server#2026', $repo->sandi($akun['id'])['hash']));
    }

    public function test_perintah_artisan_ketik_sandi_dan_sandi_acak(): void
    {
        $this->artisan('sigenrabtor:atur-sandi', ['surel' => 'puskom@example.com'])
            ->expectsQuestion('Sandi baru (min. 8 karakter, huruf & angka)', 'Server#2026')
            ->expectsQuestion('Ulangi sandi baru', 'Lain#2026')
            ->expectsOutput('Sandi ulang tidak sama.')
            ->assertExitCode(1);
        $this->assertNull(app(PenggunaRepository::class)->sandi(2));

        $this->artisan('sigenrabtor:atur-sandi', ['surel' => 'puskom@example.com'])
            ->expectsQuestion('Sandi baru (min. 8 karakter, huruf & angka)', 'Server#2026')
            ->expectsQuestion('Ulangi sandi baru', 'Server#2026')
            ->expectsOutput('Sandi diatur: Koordinator PUSKOM <puskom@example.com> (admin).')
            ->assertExitCode(0);
        $this->assertTrue(Hash::check('Server#2026', app(PenggunaRepository::class)->sandi(2)['hash']));

        $this->artisan('sigenrabtor:atur-sandi', ['surel' => 'tidak.ada@example.com', '--acak' => true])
            ->expectsOutputToContain('tidak ada — tambahkan --buat-admin')
            ->assertExitCode(1);

        $this->artisan('sigenrabtor:atur-sandi', ['surel' => 'admin@madrasah.sch.id', '--acak' => true, '--buat-admin' => true, '--nama' => 'Admin Madrasah'])
            ->expectsOutput('Akun admin dibuat dan sandinya diatur: Admin Madrasah <admin@madrasah.sch.id> (admin).')
            ->expectsOutputToContain('Sandi: ')
            ->assertExitCode(0);

        $this->artisan('sigenrabtor:atur-sandi', ['surel' => 'dewi.kartika@example.com', '--acak' => true])
            ->expectsOutputToContain('Akun ini nonaktif')
            ->assertExitCode(0);
    }
}
