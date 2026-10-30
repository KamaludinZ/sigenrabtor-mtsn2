<?php

namespace Tests\Feature\Akun;

use App\Contracts\LeadingRepository;
use App\Contracts\PenggunaRepository;
use App\Contracts\PermohonanAkunRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Livewire\Pengaturan\PermohonanAkun;
use App\Support\Akun\KeputusanPendaftaran;
use App\Support\Akun\PendaftaranAkun;
use App\Support\Pengaturan\PengaturanBerubah;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class KeputusanPendaftaranTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    private const ISIAN = [
        'nama' => 'Hasan Basri, S.Pd.', 'jabatan' => 'Koordinator Adiwiyata', 'nip' => '', 'leadings' => ['1.4'],
        'surel' => 'hasan.basri@example.com', 'sandi' => 'Madrasah#2026', 'sandi_ulang' => 'Madrasah#2026', 'catatan' => '', 'setuju' => true,
    ];

    private function aksi(string $aksi): array
    {
        return array_values(array_filter(app(RiwayatPengaturanRepository::class)->semua(), fn (array $r) => $r['aksi'] === $aksi));
    }

    public function test_endpoint_kirim_pendaftaran(): void
    {
        $this->postJson(route('akun.daftar.kirim'), self::ISIAN)
            ->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('permohonan.status', 'menunggu')
            ->assertJsonPath('permohonan.surel', 'hasan.basri@example.com')
            ->assertJsonMissingPath('permohonan.sandi_hash');
        $this->postJson(route('akun.daftar.kirim'), self::ISIAN)->assertStatus(422)->assertJsonPath('galat.surel', 'Permohonan dengan surel ini sedang menunggu persetujuan admin.');
        $this->postJson(route('akun.daftar.kirim'), [])->assertStatus(422)->assertJsonStructure(['pesan', 'galat' => ['nama', 'surel', 'leadings', 'sandi', 'sandi_ulang', 'setuju']]);

        // Dibatasi 6 permintaan per menit.
        for ($i = 0; $i < 3; $i++) {
            $this->postJson(route('akun.daftar.kirim'), []);
        }
        $this->postJson(route('akun.daftar.kirim'), [])->assertStatus(429);
    }

    public function test_setujui_membuat_akun_dengan_sandi_permohonan(): void
    {
        $p = PendaftaranAkun::ajukan(self::ISIAN);
        $hasil = KeputusanPendaftaran::setujui($p['id'], self::ADMIN);

        $akun = $hasil['akun'];
        $this->assertSame(['Hasan Basri, S.Pd.', 'hasan.basri@example.com', 'leading', ['1.4'], true], [$akun['name'], $akun['email'], $akun['role'], $akun['leadings'], $akun['is_active']]);
        $this->assertTrue(Hash::check('Madrasah#2026', app(PenggunaRepository::class)->sandi($akun['id'])['hash']));
        $this->assertSame(['disetujui', $akun['id'], 2], [$hasil['permohonan']['status'], $hasil['permohonan']['akun_id'], $hasil['permohonan']['diputuskan_oleh']]);
        $this->assertCount(1, $this->aksi('setujui_permohonan_akun'));
        $this->assertStringContainsString('akun leading dibuat, leading 1.4', $this->aksi('setujui_permohonan_akun')[0]['keterangan']);

        // Sudah diputuskan → tidak bisa diputuskan lagi; surel kini akun (pendaftaran ditolak aturan).
        $this->expectException(PengaturanBerubah::class);
        KeputusanPendaftaran::tolak($p['id'], 'Ganda', self::ADMIN);
    }

    public function test_tolak_beralasan_dan_pengaman(): void
    {
        $p = PendaftaranAkun::ajukan(self::ISIAN);
        try {
            KeputusanPendaftaran::tolak($p['id'], '   ', self::ADMIN);
            $this->fail('Alasan wajib.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('alasan', $e->errors());
        }
        $hasil = KeputusanPendaftaran::tolak($p['id'], ' Surel bukan  surel madrasah. ', self::ADMIN);
        $this->assertSame(['ditolak', 'Surel bukan surel madrasah.', null], [$hasil['permohonan']['status'], $hasil['permohonan']['alasan'], $hasil['permohonan']['akun_id']]);
        $this->assertNull(app(PenggunaRepository::class)->temukan(7));
        // Setelah ditolak, surel yang sama boleh mengajukan lagi.
        $this->assertSame('menunggu', PendaftaranAkun::ajukan(self::ISIAN)['status']);

        try {
            KeputusanPendaftaran::daftar(['id' => 1, 'name' => 'Muslimin', 'role' => 'leading']);
            $this->fail('Bukan admin.');
        } catch (AuthorizationException) {
        }
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(KeputusanPendaftaran::PESAN_TIDAK_ADA);
        KeputusanPendaftaran::setujui('tidak-ada', self::ADMIN);
    }

    public function test_setujui_ditolak_bila_leading_kini_nonaktif(): void
    {
        $contoh = app(PermohonanAkunRepository::class)->semua()[0];
        $this->assertSame(['1.3'], $contoh['leadings']);
        $repo = app(LeadingRepository::class);
        $repo->simpan(['is_active' => false] + collect($repo->semua())->firstWhere('kode', '1.3'), '1.3');
        try {
            KeputusanPendaftaran::setujui($contoh['id'], self::ADMIN);
            $this->fail('Leading nonaktif seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('leadings', $e->errors());
        }
        // Admin bisa mengganti leadingnya saat menyetujui.
        $this->assertSame(['1.6'], KeputusanPendaftaran::setujui($contoh['id'], self::ADMIN, ['1.6'])['akun']['leadings']);
        $this->assertNull(app(PenggunaRepository::class)->sandi(7), 'Permohonan contoh tanpa sandi.');
    }

    public function test_endpoint_admin_dan_panel(): void
    {
        $p = PendaftaranAkun::ajukan(self::ISIAN);
        $this->getJson(route('pengaturan.akun.permohonan'))->assertForbidden();

        session(['stub_role' => 'admin']);
        $this->getJson(route('pengaturan.akun.permohonan'))->assertOk()->assertJsonPath('jumlah_menunggu', 2)->assertJsonPath('menunggu.0.surel', 'nur.laili@example.com');
        $this->postJson(route('pengaturan.akun.permohonan.putuskan', [$p['id'], 'tolak']), ['alasan' => ''])->assertStatus(422)->assertJsonStructure(['galat' => ['alasan']]);
        $this->postJson(route('pengaturan.akun.permohonan.putuskan', [$p['id'], 'setujui']), ['leadings' => ['1.4', '1.2']])
            ->assertOk()->assertJsonPath('permohonan.status', 'disetujui')->assertJsonPath('akun.leadings', ['1.2', '1.4']);
        $this->postJson(route('pengaturan.akun.permohonan.putuskan', [$p['id'], 'tolak']), ['alasan' => 'x'])->assertStatus(409);
        $this->postJson(route('pengaturan.akun.permohonan.putuskan', ['00000000-0000-0000-0000-000000000000', 'setujui']))->assertNotFound();
        $this->postJson('/pengaturan/akun/permohonan/'.$p['id'].'/hapus')->assertNotFound();

        $contoh = app(PermohonanAkunRepository::class)->semua();
        $nurLaili = collect($contoh)->firstWhere('surel', 'nur.laili@example.com')['id'];
        Livewire::test(PermohonanAkun::class)
            ->assertSee('data-testid="permohonan-akun" data-jumlah="1"', false)
            ->assertSee('nur.laili@example.com')
            ->call('mulaiTolak', $nurLaili)
            ->assertSee('data-testid="form-tolak-permohonan"', false)
            ->call('tolak')
            ->assertHasErrors('alasan')
            ->set('alasan', 'Belum terdaftar sebagai guru.')
            ->call('tolak')
            ->assertSet('tolakId', null)
            ->assertDispatched('permohonan-akun-diputuskan')
            ->assertDispatched('notify', type: 'success')
            ->assertSee('data-testid="permohonan-kosong"', false);
    }
}
