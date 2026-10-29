<?php

namespace Tests\Feature\Akun;

use App\Contracts\PenggunaRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Livewire\Akun\Profil;
use App\Support\Akun\GantiSandi;
use App\Support\Akun\SesiAkun;
use App\Support\Stub\StubData;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class GantiSandiTest extends TestCase
{
    private const BARU = ['sandi_lama' => '', 'sandi_baru' => 'Madrasah#2026', 'sandi_ulang' => 'Madrasah#2026'];

    public function test_akun_contoh_tanpa_sandi_lalu_sandi_lama_wajib(): void
    {
        $repo = app(PenggunaRepository::class);
        $this->assertSame(['ada' => false, 'diganti_pada' => null], GantiSandi::status(1));

        GantiSandi::simpan(StubData::currentUser(), self::BARU);

        $sandi = $repo->sandi(1);
        $this->assertTrue(Hash::check('Madrasah#2026', $sandi['hash']));
        $this->assertNotNull($sandi['diganti_pada']);
        $this->assertTrue(GantiSandi::status(1)['ada']);
        $this->assertStringNotContainsString('Madrasah#2026', (string) Storage::disk('local')->get('stub/pengguna.json'));
        $riwayat = array_values(array_filter(app(RiwayatPengaturanRepository::class)->semua(), fn (array $r) => $r['aksi'] === 'ganti_sandi_akun'));
        $this->assertSame(['muslimin@example.com — sandi diganti'], array_column($riwayat, 'keterangan'));

        $akun = $repo->temukan(1);
        $galat = fn (array $isian) => GantiSandi::galat($isian, $akun);
        $this->assertSame('Isi sandi Anda saat ini.', $galat(['sandi_baru' => 'Baru#2027x', 'sandi_ulang' => 'Baru#2027x'])['sandi_lama']);
        $this->assertSame('Sandi saat ini salah.', $galat(['sandi_lama' => 'keliru', 'sandi_baru' => 'Baru#2027x', 'sandi_ulang' => 'Baru#2027x'])['sandi_lama']);
        $this->assertSame('Sandi baru harus berbeda dari sandi saat ini.', $galat(['sandi_lama' => 'Madrasah#2026'] + self::BARU)['sandi_baru']);
        $this->assertSame([], $galat(['sandi_lama' => 'Madrasah#2026', 'sandi_baru' => 'Baru#2027x', 'sandi_ulang' => 'Baru#2027x']));

        // Mengubah profil tidak menghapus sandi maupun riwayat masuk tiruan.
        $repo->catatMasuk(1, '127.0.0.1', 'uji');
        $repo->simpan(['name' => 'Muslimin, S.Pd.'], 1);
        $this->assertNotNull($repo->sandi(1));
        $this->assertCount(1, $repo->riwayatMasuk(1));
    }

    public function test_syarat_dan_galat_sandi_baru(): void
    {
        $akun = app(PenggunaRepository::class)->temukan(1);
        $this->assertSame(['panjang' => true, 'campur' => true, 'pribadi' => true, 'beda' => false], GantiSandi::syarat('Madrasah#2026', 'Madrasah#2026', 'Muslimin', 'muslimin@example.com'));
        $this->assertFalse(GantiSandi::syarat('Muslimin2026', '', 'Muslimin, M.Pd.', 'muslimin@example.com')['pribadi']);

        $galat = GantiSandi::galat(['sandi_lama' => '', 'sandi_baru' => 'pendek', 'sandi_ulang' => 'lain'], $akun);
        $this->assertSame(['sandi_baru' => 'Sandi minimal 8 karakter.', 'sandi_ulang' => 'Sandi ulang tidak sama dengan sandi baru.'], $galat);
        $this->assertSame('Sandi tidak boleh memuat nama atau surel Anda.', GantiSandi::galat(['sandi_baru' => 'muslimin99', 'sandi_ulang' => 'muslimin99'], $akun)['sandi_baru']);
        $this->assertArrayHasKey('sandi_baru', GantiSandi::galat([], $akun));
    }

    public function test_sandi_salah_berulang_dijeda(): void
    {
        GantiSandi::simpan(StubData::currentUser(), self::BARU);
        $isian = ['sandi_lama' => 'keliru', 'sandi_baru' => 'Baru#2027x', 'sandi_ulang' => 'Baru#2027x'];
        for ($i = 0; $i < GantiSandi::MAKS_SALAH; $i++) {
            try {
                GantiSandi::simpan(StubData::currentUser(), $isian);
            } catch (ValidationException) {
            }
        }
        $this->assertStringStartsWith('Terlalu banyak percobaan sandi salah', GantiSandi::galat(['sandi_lama' => 'Madrasah#2026'] + $isian, app(PenggunaRepository::class)->temukan(1))['sandi_lama']);
    }

    public function test_tab_sandi_mengganti_sandi(): void
    {
        SesiAkun::masuk(['id' => 4, 'role' => 'leading']);
        Livewire::withQueryParams(['tab' => 'sandi'])->test(Profil::class)
            ->assertSee('data-testid="form-sandi"', false)
            ->assertSee('data-testid="sandi-belum-ada"', false)
            ->assertSee('data-testid="status-sandi" data-ada="tidak"', false)
            ->assertSee('data-syarat="beda"', false)
            ->call('gantiSandi')
            ->assertHasErrors(['isianSandi.sandi_baru', 'isianSandi.sandi_ulang'])
            ->set('isianSandi', self::BARU)
            ->call('gantiSandi')
            ->assertHasNoErrors()
            ->assertSet('isianSandi', ['sandi_lama' => '', 'sandi_baru' => '', 'sandi_ulang' => ''])
            ->assertDispatched('sandi-diganti')
            ->assertDispatched('notify', type: 'success')
            ->assertSee('data-testid="status-sandi" data-ada="ya"', false)
            ->assertDontSee('data-testid="sandi-belum-ada"', false)
            ->set('isianSandi', ['sandi_lama' => 'keliru', 'sandi_baru' => 'Baru#2027x', 'sandi_ulang' => 'Baru#2027x'])
            ->call('gantiSandi')
            ->assertHasErrors(['isianSandi.sandi_lama'])
            ->assertSee('Sandi saat ini salah.');
        $this->assertTrue(Hash::check('Madrasah#2026', app(PenggunaRepository::class)->sandi(4)['hash']));
    }
}
