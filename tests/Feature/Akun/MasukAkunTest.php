<?php

namespace Tests\Feature\Akun;

use App\Contracts\PenggunaRepository;
use App\Livewire\Akun\Masuk;
use App\Livewire\Akun\Profil;
use App\Support\Akun\DataAkun;
use App\Support\Akun\PeriksaMasuk;
use Livewire\Livewire;
use Tests\TestCase;

class MasukAkunTest extends TestCase
{
    public function test_halaman_masuk_dengan_layout_tamu(): void
    {
        $this->get(route('akun.masuk'))
            ->assertOk()
            ->assertSee('data-testid="panel-merek"', false)
            ->assertSee('data-testid="form-masuk"', false)
            ->assertSee('muslimin@example.com')
            ->assertSee('puskom@example.com')
            ->assertDontSee('data-testid="kepala-pengaturan"', false);
        $this->assertSame([1, 2], array_column(DataAkun::persona(), 'id'));
        $this->assertSame(4, DataAkun::cariSurel(' AHMAD.FAUZAN@example.com ')['id']);
        $this->assertNull(DataAkun::cariSurel('tidak@ada.id'));
    }

    public function test_masuk_dengan_surel_di_mode_demo(): void
    {
        Livewire::test(Masuk::class)
            ->call('masuk')
            ->assertHasErrors(['surel', 'sandi'])->assertSee('Isi surel akun Anda.')->assertSee('Isi sandi akun Anda.')
            ->set('surel', 'bukan-surel')->set('sandi', 'x')->call('masuk')
            ->assertHasErrors('surel')->assertSee('Surel tidak sah')
            ->set('surel', 'tidak@ada.id')->set('sandi', 'rahasia')->call('masuk')
            ->assertHasErrors('surel')->assertSee('belum terdaftar')->assertSet('sandi', '')
            ->set('surel', 'dewi.kartika@example.com')->set('sandi', 'rahasia')->call('masuk')
            ->assertSee('Akun ini nonaktif');

        Livewire::test(Masuk::class)
            ->set('surel', 'Puskom@Example.com')->set('sandi', 'apa-saja')
            ->call('masuk')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));
        $this->assertSame('admin', session('stub_role'));
        $this->assertNotNull(app(PenggunaRepository::class)->temukan(2)['terakhir_masuk']);

        Livewire::test(Masuk::class)->call('masukSebagai', 1)->assertRedirect(route('dashboard'));
        $this->assertSame('leading', session('stub_role'));
        Livewire::test(Masuk::class)->call('masukSebagai', 3)->assertNoRedirect();
    }

    public function test_surel_diperiksa_saat_blur_dan_diisi_dari_url(): void
    {
        Livewire::test(Masuk::class)
            ->set('surel', 'bukan-surel')->assertHasErrors('surel')->assertSee('Surel tidak sah')
            // Keberadaan akun tidak dibocorkan sebelum dikirim.
            ->set('surel', 'tidak@ada.id')->assertHasNoErrors('surel')->assertDontSee('belum terdaftar')
            ->set('surel', '')->assertHasNoErrors('surel');

        Livewire::withQueryParams(['surel' => '  Nur.Laili@Example.com '])->test(Masuk::class)
            ->assertSet('surel', 'nur.laili@example.com');
        // Surel terisi → fokus awal pindah ke isian sandi.
        $html = $this->get(route('akun.masuk', ['surel' => 'puskom@example.com']))->assertOk()
            ->assertSee('&quot;surel&quot;:&quot;puskom@example.com&quot;', false)->getContent();
        $this->assertMatchesRegularExpression('/id="sandi"[^>]*\\sautofocus\\s/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="surel"[^>]*\\sautofocus\\s/', $html);
    }

    public function test_percobaan_gagal_dibatasi_lalu_dijeda(): void
    {
        $uji = Livewire::test(Masuk::class)->set('surel', 'tidak@ada.id');
        for ($i = 1; $i <= PeriksaMasuk::MAKS_PERCOBAAN - PeriksaMasuk::PERINGATAN_SISA - 1; $i++) {
            $uji->set('sandi', 'salah')->call('masuk')->assertSet('peringatan', null);
        }
        $uji->set('sandi', 'salah')->call('masuk')->assertSet('peringatan', 'Sisa 2 percobaan sebelum masuk dijeda 60 detik.')
            ->set('sandi', 'salah')->call('masuk')->assertSee('Sisa 1 percobaan')
            ->set('sandi', 'salah')->call('masuk')->assertDispatched('masuk-dijeda')
            ->set('sandi', 'salah')->call('masuk')->assertSee('Terlalu banyak percobaan masuk')->assertDispatched('masuk-dijeda');
        $this->assertGreaterThan(0, PeriksaMasuk::tunggu(' TIDAK@ada.id', '127.0.0.1'));
        // Jeda per surel: surel lain tetap bisa dicoba, dan berhasil masuk mengosongkan hitungan.
        $this->assertSame(0, PeriksaMasuk::tunggu('puskom@example.com', '127.0.0.1'));
        PeriksaMasuk::gagal('puskom@example.com', '127.0.0.1');
        Livewire::test(Masuk::class)->set('surel', 'puskom@example.com')->set('sandi', 'apa-saja')->call('masuk')->assertRedirect(route('dashboard'));
        $this->assertSame(PeriksaMasuk::MAKS_PERCOBAAN - 1, PeriksaMasuk::gagal('puskom@example.com', '127.0.0.1'));
    }

    public function test_keluar_dan_menu_pengguna(): void
    {
        $this->get(route('dashboard'))->assertOk()->assertSee('data-testid="menu-akun"', false)->assertSee('data-testid="menu-keluar"', false);
        session(['stub_role' => 'admin']);
        $this->post(route('akun.keluar'))->assertRedirect(route('akun.masuk'))->assertSessionHas('notify.message', 'Anda sudah keluar.');
        $this->assertNull(session('stub_role'));
    }

    public function test_halaman_akun_saya(): void
    {
        $akun = DataAkun::untuk(['id' => 1]);
        $this->assertSame(['muslimin@example.com', 'leading', 'Leading / penanggung jawab'], [$akun['surel'], $akun['peran'], $akun['label_peran']]);
        $this->assertSame([['kode' => '1.6', 'nama_unit' => 'Tim Penjamin Mutu', 'aktif' => true]], $akun['leading']);
        $this->assertNull(DataAkun::untuk(['id' => 999]));

        $this->get(route('akun.profil'))->assertOk()->assertSee('Akun saya', false);
        app(PenggunaRepository::class)->catatMasuk(1, '10.0.0.7', 'Peramban Uji');
        Livewire::test(Profil::class)
            ->assertSeeHtml('data-profil="surel"')
            ->assertSee('muslimin@example.com')
            ->assertSeeHtml('data-leading="1.6"')
            ->set('tab', 'sandi')
            ->assertSee('data-testid="syarat-sandi"', false)
            ->set('tab', 'aktivitas')
            ->assertSee('10.0.0.7 · Peramban Uji')
            ->set('tab', 'asing')
            ->assertSet('tab', 'profil');

        session(['stub_role' => 'admin']);
        Livewire::test(Profil::class)->assertSeeHtml('data-peran="admin"')->assertSee('puskom@example.com')->assertDontSee('data-testid="leading-akun"', false);
    }
}
