<?php

namespace Tests\Feature\Akun;

use App\Contracts\PenggunaRepository;
use App\Support\Akun\SesiAkun;
use App\Support\Akun\UbahProfil;
use Tests\TestCase;

class ProfilApiTest extends TestCase
{
    public function test_baca_dan_ubah_sebagian_profil(): void
    {
        SesiAkun::masuk(['id' => 4, 'role' => 'leading']);
        $versi = $this->getJson(route('akun.profil.data'))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('nama', 'Ahmad Fauzan, S.Pd.')
            ->assertJsonPath('surel', 'ahmad.fauzan@example.com')
            ->assertJsonPath('leading.0.kode', '1.2')
            ->json('versi');

        // Hanya jabatan dikirim → nama tetap.
        $jawab = $this->patchJson(route('akun.profil.ubah'), ['jabatan' => ' Waka  Kesiswaan ', 'versi' => $versi])
            ->assertOk()
            ->assertJsonPath('pesan', 'Profil disimpan.')
            ->assertJsonPath('ringkasan', 'Jabatan: Wakil Kepala Bidang Kesiswaan → Waka Kesiswaan')
            ->assertJsonPath('akun.nama', 'Ahmad Fauzan, S.Pd.')
            ->assertJsonPath('akun.jabatan', 'Waka Kesiswaan');
        $this->assertNotSame($versi, $jawab->json('versi'));
        $this->assertSame($jawab->json('versi'), $jawab->json('akun.versi'));

        // Tanpa perubahan.
        $this->patchJson(route('akun.profil.ubah'), ['jabatan' => 'Waka Kesiswaan'])->assertOk()->assertJsonPath('pesan', 'Tidak ada perubahan profil.')->assertJsonPath('ringkasan', null);
    }

    public function test_galat_terlarang_dan_bentrok(): void
    {
        SesiAkun::masuk(['id' => 4, 'role' => 'leading']);
        $this->patchJson(route('akun.profil.ubah'), ['nama' => '  '])->assertStatus(422)->assertJsonPath('galat.nama', 'Nama lengkap wajib diisi.');
        $this->patchJson(route('akun.profil.ubah'), ['nama' => 'Ahmad', 'email' => 'lain@example.com', 'role' => 'admin'])
            ->assertStatus(422)->assertJsonPath('galat.isian', UbahProfil::PESAN_TERLARANG.' (email, role)');
        $this->assertSame(['ahmad.fauzan@example.com', 'leading'], [app(PenggunaRepository::class)->temukan(4)['email'], app(PenggunaRepository::class)->temukan(4)['role']]);

        $versi = $this->getJson(route('akun.profil.data'))->json('versi');
        app(PenggunaRepository::class)->simpan(['name' => 'Ahmad Fauzan (admin)'], 4);
        $this->patchJson(route('akun.profil.ubah'), ['nama' => 'Ahmad F.', 'versi' => $versi])
            ->assertStatus(409)
            ->assertJsonPath('pesan', UbahProfil::PESAN_BENTROK)
            ->assertJsonPath('akun.nama', 'Ahmad Fauzan (admin)')
            ->assertJsonStructure(['versi']);
        $this->assertSame('Ahmad Fauzan (admin)', app(PenggunaRepository::class)->temukan(4)['name']);
    }
}
