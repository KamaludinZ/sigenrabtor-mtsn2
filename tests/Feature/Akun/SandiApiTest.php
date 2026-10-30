<?php

namespace Tests\Feature\Akun;

use App\Contracts\PenggunaRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Livewire\Pengaturan\LeadingUser;
use App\Support\Akun\AturUlangSandi;
use App\Support\Akun\Autentikasi;
use App\Support\Akun\GantiSandi;
use App\Support\Akun\PendaftaranAkun;
use App\Support\Akun\SesiAkun;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SandiApiTest extends TestCase
{
    public function test_endpoint_ganti_sandi(): void
    {
        SesiAkun::masuk(['id' => 4, 'role' => 'leading']);
        $this->putJson(route('akun.sandi.ganti'), ['sandi_baru' => 'pendek', 'sandi_ulang' => 'lain'])
            ->assertStatus(422)->assertJsonPath('galat.sandi_baru', 'Sandi minimal 8 karakter.')->assertJsonPath('galat.sandi_ulang', 'Sandi ulang tidak sama dengan sandi baru.');
        $this->putJson(route('akun.sandi.ganti'), ['sandi_baru' => 'Madrasah#2026', 'sandi_ulang' => 'Madrasah#2026'])
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('pesan', 'Sandi diganti. Gunakan sandi baru saat masuk berikutnya.')->assertJsonStructure(['diganti_pada']);
        $this->assertTrue(Hash::check('Madrasah#2026', app(PenggunaRepository::class)->sandi(4)['hash']));

        // Perangkat yang diingat: cookie diterbitkan ulang dengan sidik sandi baru.
        $this->withCredentials()->withCookie(Autentikasi::COOKIE_INGAT, json_encode(['id' => 4, 'sidik' => 'lama']))
            ->putJson(route('akun.sandi.ganti'), ['sandi_lama' => 'Madrasah#2026', 'sandi_baru' => 'Baru#2027x', 'sandi_ulang' => 'Baru#2027x'])
            ->assertOk()->assertCookie(Autentikasi::COOKIE_INGAT);

        // Sandi saat ini salah berulang → 429.
        for ($i = 0; $i < GantiSandi::MAKS_SALAH; $i++) {
            $jawab = $this->putJson(route('akun.sandi.ganti'), ['sandi_lama' => 'keliru', 'sandi_baru' => 'Lain#2028x', 'sandi_ulang' => 'Lain#2028x']);
        }
        $jawab->assertStatus(429)->assertHeader('Retry-After')->assertJsonStructure(['pesan', 'galat' => ['sandi_lama'], 'tunggu']);
    }

    public function test_atur_ulang_sandi_oleh_admin(): void
    {
        $admin = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin'];
        $hasil = AturUlangSandi::aturUlang(3, $admin);
        $this->assertSame(AturUlangSandi::PANJANG, strlen($hasil['sandi']));
        $this->assertSame(['panjang' => true, 'campur' => true, 'pribadi' => true], PendaftaranAkun::syaratSandi($hasil['sandi'], 'Siti Rohmah, M.Pd.', 'siti.rohmah@example.com'));
        $this->assertTrue(Hash::check($hasil['sandi'], app(PenggunaRepository::class)->sandi(3)['hash']));
        $riwayat = array_values(array_filter(app(RiwayatPengaturanRepository::class)->semua(), fn (array $r) => $r['aksi'] === 'atur_ulang_sandi'));
        $this->assertStringNotContainsString($hasil['sandi'], $riwayat[0]['keterangan']);
        $this->assertSame(3, Autentikasi::periksa('siti.rohmah@example.com', $hasil['sandi'], null)['akun']['id']);

        try {
            AturUlangSandi::aturUlang(2, $admin);
            $this->fail('Akun sendiri lewat Akun saya.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('akun', $e->errors());
        }

        // Endpoint: bukan admin 403, akun tak ada 404.
        $this->postJson(route('pengaturan.akun.sandi', 3))->assertForbidden();
        session(['stub_role' => 'admin']);
        $this->postJson(route('pengaturan.akun.sandi', 4))->assertOk()->assertJsonPath('akun.surel', 'ahmad.fauzan@example.com')->assertJsonStructure(['sandi_sementara']);
        $this->postJson(route('pengaturan.akun.sandi', 99))->assertNotFound();
        $this->postJson(route('pengaturan.akun.sandi', 2))->assertStatus(422);
    }

    public function test_formulir_akun_mengatur_ulang_sandi(): void
    {
        session(['stub_role' => 'admin']);
        Livewire::withQueryParams(['tab' => 'akun'])->test(LeadingUser::class)
            ->call('ubahAkun', 2)
            ->assertDontSee('data-testid="sandi-akun-admin"', false)
            ->call('ubahAkun', 5)
            ->assertSee('data-testid="atur-ulang-sandi"', false)
            ->call('mintaAturUlangSandi')
            ->assertSee('data-testid="konfirmasi-atur-ulang"', false)
            ->call('aturUlangSandi')
            ->assertDispatched('notify', type: 'success')
            ->assertSee('data-testid="sandi-sementara"', false)
            ->assertSet('sandiSementara.surel', 'rina.kusumawati@example.com')
            ->call('batalAkun')
            ->assertSet('sandiSementara', null);
        $this->assertNotNull(app(PenggunaRepository::class)->sandi(5));
    }
}
