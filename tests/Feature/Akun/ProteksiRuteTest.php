<?php

namespace Tests\Feature\Akun;

use App\Http\Middleware\HanyaAdmin;
use App\Http\Middleware\WajibMasuk;
use App\Livewire\Akun\Masuk;
use App\Support\Akun\PeriksaMasuk;
use App\Support\Akun\SesiAkun;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Tests\TestCase;

class ProteksiRuteTest extends TestCase
{
    public function test_halaman_tamu_dialihkan_otomatis_bila_sudah_masuk(): void
    {
        config(['sigenrabtor.akun.wajib_masuk' => true]);
        $this->get(route('akun.masuk'))->assertOk();
        $this->get(route('akun.daftar'))->assertOk();

        SesiAkun::masukPersona('leading');
        $this->get(route('akun.masuk'))->assertRedirect(route('dashboard'));
        $this->get(route('akun.daftar'))->assertRedirect(route('dashboard'));
        session()->put('url.intended', route('kegiatan.index'));
        $this->get(route('akun.masuk'))->assertRedirect(route('kegiatan.index'));

        // Tanpa wajib masuk (mode demo): halaman Masuk tetap terbuka dengan kartu sudah masuk.
        config(['sigenrabtor.akun.wajib_masuk' => false]);
        $this->get(route('akun.masuk'))->assertOk()->assertSee('data-testid="sudah-masuk"', false);
    }

    public function test_aksi_livewire_tanpa_sesi_dijawab_401(): void
    {
        $this->assertEqualsCanonicalizing([WajibMasuk::class, HanyaAdmin::class], array_values(array_intersect(Livewire::getPersistentMiddleware(), [WajibMasuk::class, HanyaAdmin::class])));
        config(['sigenrabtor.akun.wajib_masuk' => true]);
        $permintaan = Request::create('/kegiatan', 'POST', server: ['HTTP_X_LIVEWIRE' => '1']);
        $permintaan->setLaravelSession(app('session.store'));
        try {
            (new WajibMasuk)->handle($permintaan, fn () => response('lolos'));
            $this->fail('Aksi Livewire tanpa sesi seharusnya ditolak.');
        } catch (HttpResponseException $e) {
            $this->assertSame(401, $e->getResponse()->getStatusCode());
            $this->assertSame(['pesan' => WajibMasuk::PESAN_SESI, 'masuk' => route('akun.masuk')], json_decode($e->getResponse()->getContent(), true));
        }

        SesiAkun::masukPersona('admin');
        $this->assertSame('lolos', (new WajibMasuk)->handle($permintaan, fn () => response('lolos'))->getContent());
    }

    public function test_sesi_berakhir_kembali_ke_halaman_asal(): void
    {
        Livewire::withQueryParams(['sesi' => 'berakhir', 'kembali' => '/kegiatan?status=draf'])->test(Masuk::class)
            ->assertSet('sesiBerakhir', true)
            ->assertSee('data-testid="sesi-berakhir"', false);
        $this->assertSame(url('/kegiatan?status=draf'), session('url.intended'));

        Livewire::test(Masuk::class)->set('surel', 'puskom@example.com')->set('sandi', 'x')->call('masuk')
            ->assertRedirect(url('/kegiatan?status=draf'));

        foreach (['//evil.example', 'https://evil.example/x', '/\\evil.example', 'kegiatan', '/masuk?x=1', '/daftar', "/kegiatan\nx", ''] as $jalur) {
            $this->assertNull(PeriksaMasuk::jalurKembali($jalur), $jalur);
        }
        $this->assertSame('/pemeriksaan/3', PeriksaMasuk::jalurKembali('/pemeriksaan/3'));
        $this->assertNull(PeriksaMasuk::jalurKembali(['/x']));
    }

    public function test_halaman_galat_berbahasa_indonesia(): void
    {
        $this->get(route('pengaturan.index'))->assertForbidden()
            ->assertSee('data-testid="halaman-galat" data-kode="403"', false)
            ->assertSee('Halaman ini khusus admin.')
            ->assertSee('data-testid="galat-dashboard"', false);
        $this->get('/tidak-ada-halaman-ini')->assertNotFound()->assertSee('Halaman tidak ditemukan');
        $this->getJson(route('pengaturan.tahun.data'))->assertForbidden()->assertJson(['message' => 'Halaman ini khusus admin.']);
    }
}
