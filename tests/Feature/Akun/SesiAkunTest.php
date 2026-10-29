<?php

namespace Tests\Feature\Akun;

use App\Contracts\PenggunaRepository;
use App\Http\Middleware\WajibMasuk;
use App\Livewire\Akun\Masuk;
use App\Support\Akun\SesiAkun;
use App\Support\Stub\StubData;
use Livewire\Livewire;
use Tests\TestCase;

class SesiAkunTest extends TestCase
{
    public function test_tanpa_sesi_memakai_persona_demo(): void
    {
        $this->assertFalse(SesiAkun::sudahMasuk());
        $this->assertSame(['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'jabatan' => 'Tim Penjamin Mutu', 'leadings' => ['1.6']], StubData::currentUser());
        session(['stub_role' => 'admin']);
        $this->assertTrue(SesiAkun::sudahMasuk());
        $this->assertSame(['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'jabatan' => 'Admin Aplikasi', 'leadings' => []], StubData::currentUser());
    }

    public function test_masuk_dengan_akun_mana_pun_memakai_nama_peran_dan_leading_akun_itu(): void
    {
        Livewire::test(Masuk::class)
            ->set('surel', 'ahmad.fauzan@example.com')->set('sandi', 'apa-saja')->call('masuk')
            ->assertRedirect(route('dashboard'));
        $this->assertSame(['id' => 4, 'name' => 'Ahmad Fauzan, S.Pd.', 'role' => 'leading', 'jabatan' => 'Wakil Kepala Bidang Kesiswaan', 'leadings' => ['1.2', '1.4']], StubData::currentUser());
        $this->assertSame('Selamat datang kembali, Ahmad Fauzan, S.Pd.', session('notify.message'));
        $this->assertSame('ahmad.fauzan@example.com', SesiAkun::pengguna()['email']);
        $this->assertNotNull(SesiAkun::pengguna()['masuk_pada']);

        // Admin selain persona memakai peran akunnya sendiri.
        Livewire::test(Masuk::class)->set('surel', 'rina.kusumawati@example.com')->set('sandi', 'x')->call('masuk');
        $this->assertSame(['id' => 5, 'role' => 'admin', 'leadings' => []], array_intersect_key(StubData::currentUser(), array_flip(['id', 'role', 'leadings'])));

        $this->get(route('dashboard'))->assertOk()->assertSee('data-testid="sapaan-pengguna"', false)->assertSee('Rina Kusumawati, M.Pd.')
            ->assertSee('data-testid="surel-pengguna"', false)->assertSee('rina.kusumawati@example.com');
        $this->get(route('akun.masuk'))->assertSee('data-testid="sudah-masuk"', false)->assertSee('data-akun="5"', false);

        $this->post(route('akun.keluar'))->assertRedirect(route('akun.masuk'));
        $this->assertFalse(SesiAkun::sudahMasuk());
        $this->get(route('akun.masuk'))->assertDontSee('data-testid="sudah-masuk"', false);
    }

    public function test_sakelar_demo_mengisi_sesi_persona(): void
    {
        $this->get(route('demo.peran', 'admin'))->assertRedirect();
        $this->assertSame([2, 'admin'], [session(SesiAkun::KUNCI_AKUN), StubData::currentUser()['role']]);
        $this->get(route('demo.peran', 'leading'));
        $this->assertSame([1, 'leading'], [session(SesiAkun::KUNCI_AKUN), StubData::currentUser()['role']]);
    }

    public function test_wajib_masuk_mengalihkan_tamu_dan_mengingat_tujuan(): void
    {
        config(['sigenrabtor.akun.wajib_masuk' => true]);

        $this->get(route('kegiatan.index'))->assertRedirect(route('akun.masuk'));
        $this->assertSame(route('kegiatan.index'), session('url.intended'));
        $this->getJson(route('dashboard.data'))->assertStatus(401)->assertJson(['pesan' => WajibMasuk::PESAN_SESI]);
        $this->get(route('akun.masuk'))->assertOk();
        $this->get(route('akun.daftar'))->assertOk();
        $this->get('/up')->assertOk();

        // Masuk → diteruskan ke tujuan semula.
        Livewire::test(Masuk::class)->set('surel', 'siti.rohmah@example.com')->set('sandi', 'x')->call('masuk')
            ->assertRedirect(route('kegiatan.index'));
        $this->get(route('kegiatan.index'))->assertOk();
        $this->assertSame([3, ['1.1']], [StubData::currentUser()['id'], StubData::currentUser()['leadings']]);
    }

    public function test_akun_yang_dinonaktifkan_dikeluarkan(): void
    {
        config(['sigenrabtor.akun.wajib_masuk' => true]);
        SesiAkun::masuk(['id' => 3, 'role' => 'leading']);
        $this->get(route('dashboard'))->assertOk();

        $repo = app(PenggunaRepository::class);
        $repo->simpan(['is_active' => false] + $repo->temukan(3), 3);
        $this->get(route('dashboard'))->assertRedirect(route('akun.masuk'))->assertSessionHas('notify.message', WajibMasuk::PESAN_NONAKTIF);
        $this->assertFalse(SesiAkun::sudahMasuk());
    }
}
