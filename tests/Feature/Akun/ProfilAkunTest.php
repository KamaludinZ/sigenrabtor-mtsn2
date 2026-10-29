<?php

namespace Tests\Feature\Akun;

use App\Contracts\PejabatRepository;
use App\Livewire\Akun\Profil;
use App\Support\Akun\ProfilAkun;
use App\Support\Akun\SesiAkun;
use App\Support\Stub\StubData;
use Livewire\Livewire;
use Tests\TestCase;

class ProfilAkunTest extends TestCase
{
    public function test_kegiatan_dokumen_tercetak_dan_kuota_akun_leading(): void
    {
        $r = ProfilAkun::untuk(StubData::currentUser());

        $this->assertSame(['semua' => 2, 'tahun_aktif' => 2], array_intersect_key($r['kegiatan'], array_flip(['semua', 'tahun_aktif'])));
        $this->assertSame(2, $r['kegiatan']['per_status']['draf']);
        $this->assertSame(8, $r['dokumen']['jumlah']);
        $this->assertSame(6, $r['dokumen']['tahun_aktif']);
        $this->assertSame(1, $r['dokumen']['terakhir']['user_id']);
        $this->assertSame([['1.6', 'akun', 'Muslimin, M.Pd.']], array_map(fn (array $c) => [$c['kode'], $c['sumber'], $c['nama']], $r['tercetak']));
        $this->assertSame(20, $r['kuota']['sisa']);
        $this->assertNull($r['sesi']['masuk_pada']);

        // Pejabat leading yang diisi admin menggantikan nama akun.
        app(PejabatRepository::class)->simpan(2026, 'leading', '1.6', ['jabatan' => 'Ketua Tim Penjamin Mutu', 'nama' => 'Dra. Hj. Siti Aminah', 'nip' => '197001011995032001']);
        $c = ProfilAkun::untuk(StubData::currentUser())['tercetak'][0];
        $this->assertSame(['pejabat', 'Dra. Hj. Siti Aminah', '19700101 199503 2 001'], [$c['sumber'], $c['nama'], $c['nip']]);
    }

    public function test_akun_lain_dan_admin(): void
    {
        SesiAkun::masuk(['id' => 4, 'role' => 'leading']);
        $r = ProfilAkun::untuk(StubData::currentUser());
        $this->assertSame(0, $r['kegiatan']['semua']);
        $this->assertSame(['1.2', '1.4'], array_column($r['tercetak'], 'kode'));
        $this->assertNotNull($r['sesi']['masuk_pada']);

        SesiAkun::masukPersona('admin');
        $this->assertSame([], ProfilAkun::untuk(StubData::currentUser())['tercetak']);
    }

    public function test_tab_profil_menampilkan_data(): void
    {
        Livewire::test(Profil::class)
            ->assertSee('data-testid="tercetak-akun"', false)
            ->assertSee('data-testid="tercetak-leading" data-leading="1.6" data-sumber="akun"', false)
            ->assertSee('data-testid="kegiatan-buatan" data-jumlah="2"', false)
            ->assertSee('data-testid="dokumen-buatan" data-jumlah="8"', false)
            ->assertSee('data-testid="kuota-akun"', false)
            ->assertSee('Sisa 20 dari 20 permintaan AI hari ini.')
            ->assertSee('Mode demo (tanpa masuk)');

        session(['stub_role' => 'admin']);
        Livewire::test(Profil::class)->assertDontSee('data-testid="tercetak-akun"', false)->assertSee('data-testid="kerja-akun"', false);
    }
}
