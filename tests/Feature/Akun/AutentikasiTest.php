<?php

namespace Tests\Feature\Akun;

use App\Contracts\PenggunaRepository;
use App\Livewire\Akun\Masuk;
use App\Support\Akun\Autentikasi;
use App\Support\Akun\SesiAkun;
use App\Support\Stub\StubData;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AutentikasiTest extends TestCase
{
    private function bersandi(int $id = 4, string $sandi = 'Madrasah#2026'): void
    {
        app(PenggunaRepository::class)->gantiSandi($id, Hash::make($sandi));
    }

    public function test_sandi_diperiksa_bila_akun_bersandi(): void
    {
        $this->assertSame(4, Autentikasi::periksa('ahmad.fauzan@example.com', 'apa-saja', '10.0.0.1')['akun']['id'], 'Mode demo: akun tanpa sandi boleh masuk.');
        config(['sigenrabtor.akun.mode_demo' => false]);
        $this->assertSame(['sandi' => Autentikasi::PESAN_TANPA_SANDI], Autentikasi::periksa('ahmad.fauzan@example.com', 'apa-saja', '10.0.0.1')['galat']);

        $this->bersandi();
        $salah = Autentikasi::periksa('Ahmad.Fauzan@example.com', 'Madrasah#2025', '10.0.0.1');
        $this->assertSame(['sandi' => Autentikasi::PESAN_SANDI], $salah['galat']);
        $this->assertNull($salah['peringatan'], 'Dua kegagalan: sisa 3, belum diperingatkan.');
        $this->assertSame('Sisa 2 percobaan sebelum masuk dijeda 60 detik.', Autentikasi::periksa('ahmad.fauzan@example.com', 'keliru', '10.0.0.1')['peringatan']);
        $this->assertSame(4, Autentikasi::periksa('ahmad.fauzan@example.com', 'Madrasah#2026', '10.0.0.1')['akun']['id']);
        $this->assertSame(['surel' => 'Akun ini nonaktif — hubungi admin untuk mengaktifkannya kembali.'], Autentikasi::periksa('dewi.kartika@example.com', 'x', '10.0.0.1')['galat']);
        $this->assertSame(['surel' => 'Isi surel akun Anda.', 'sandi' => 'Isi sandi akun Anda.'], Autentikasi::periksa('', '', null)['galat']);
    }

    public function test_endpoint_masuk_json(): void
    {
        $this->bersandi();
        $this->postJson(route('akun.masuk.kirim'), ['surel' => 'ahmad.fauzan@example.com', 'sandi' => 'salah'])
            ->assertStatus(422)->assertJsonPath('galat.sandi', Autentikasi::PESAN_SANDI)->assertJsonPath('pesan', Autentikasi::PESAN_SANDI);
        session()->put('url.intended', route('kegiatan.index'));
        $this->postJson(route('akun.masuk.kirim'), ['surel' => 'ahmad.fauzan@example.com', 'sandi' => 'Madrasah#2026'])
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('akun.nama', 'Ahmad Fauzan, S.Pd.')
            ->assertJsonPath('akun.leading', ['1.2', '1.4'])
            ->assertJsonPath('tujuan', route('kegiatan.index'))
            ->assertCookieExpired(Autentikasi::COOKIE_INGAT);
        $this->assertSame(4, StubData::currentUser()['id']);
        $this->getJson(route('akun.sesi'))->assertOk()->assertJsonPath('masuk', true)->assertJsonPath('akun.surel', 'ahmad.fauzan@example.com')->assertJsonPath('mode_demo', true);

        $this->postJson(route('akun.keluar'))->assertOk()->assertJsonPath('pesan', 'Anda sudah keluar.')->assertCookieExpired(Autentikasi::COOKIE_INGAT);
        $this->getJson(route('akun.sesi'))->assertJsonPath('masuk', false)->assertJsonPath('akun', null);

        // Lima kegagalan → 429 dengan Retry-After.
        for ($i = 0; $i < 4; $i++) {
            $this->postJson(route('akun.masuk.kirim'), ['surel' => 'siti.rohmah@example.com', 'sandi' => 'x'])->assertOk();
            $this->postJson(route('akun.keluar'));
        }
        $this->bersandi(3);
        for ($i = 0; $i < 5; $i++) {
            $jawab = $this->postJson(route('akun.masuk.kirim'), ['surel' => 'siti.rohmah@example.com', 'sandi' => 'salah']);
        }
        $jawab->assertStatus(429)->assertHeader('Retry-After')->assertJsonStructure(['pesan', 'galat' => ['sandi'], 'tunggu']);
        $this->postJson(route('akun.masuk.kirim'), ['surel' => 'siti.rohmah@example.com', 'sandi' => 'Madrasah#2026'])
            ->assertStatus(429)->assertJsonStructure(['galat' => ['surel']]);
    }

    public function test_ingat_saya_memulihkan_sesi_sampai_sandi_diganti(): void
    {
        config(['sigenrabtor.akun.wajib_masuk' => true]);
        $this->bersandi();
        $jawab = $this->postJson(route('akun.masuk.kirim'), ['surel' => 'ahmad.fauzan@example.com', 'sandi' => 'Madrasah#2026', 'ingat' => true])
            ->assertOk()->assertCookie(Autentikasi::COOKIE_INGAT);
        $cookie = $jawab->getCookie(Autentikasi::COOKIE_INGAT, decrypt: true)->getValue();

        // Sesi berakhir (mis. peramban ditutup) → dipulihkan dari cookie.
        session()->flush();
        $this->withCookie(Autentikasi::COOKIE_INGAT, $cookie)->get(route('dashboard'))->assertOk();
        $this->assertSame(4, session(SesiAkun::KUNCI_AKUN));

        // Sandi diganti → cookie lama tidak berlaku lagi.
        session()->flush();
        $this->bersandi(4, 'Baru#2027x');
        $this->withCookie(Autentikasi::COOKIE_INGAT, $cookie)->get(route('dashboard'))->assertRedirect(route('akun.masuk'));
        // Cookie palsu diabaikan.
        $this->withCookie(Autentikasi::COOKIE_INGAT, json_encode(['id' => 2, 'sidik' => 'tebakan']))->get(route('dashboard'))->assertRedirect(route('akun.masuk'));
    }

    public function test_halaman_masuk_memakai_autentikasi_dan_mode_demo(): void
    {
        $this->bersandi();
        Livewire::test(Masuk::class)
            ->set('surel', 'ahmad.fauzan@example.com')->set('sandi', 'salah')->call('masuk')
            ->assertHasErrors('sandi')->assertSee(Autentikasi::PESAN_SANDI)->assertSet('sandi', '')
            ->set('sandi', 'Madrasah#2026')->set('ingat', true)->call('masuk')
            ->assertRedirect(route('dashboard'));
        $this->assertSame(4, session(SesiAkun::KUNCI_AKUN));

        config(['sigenrabtor.akun.mode_demo' => false]);
        SesiAkun::keluar();
        Livewire::test(Masuk::class)->assertDontSee('data-testid="akun-demo"', false)->call('masukSebagai', 1)->assertNoRedirect();
        $this->assertFalse(SesiAkun::sudahMasuk());
    }
}
