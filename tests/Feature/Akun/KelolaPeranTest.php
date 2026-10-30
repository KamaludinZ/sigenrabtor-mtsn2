<?php

namespace Tests\Feature\Akun;

use App\Contracts\PenggunaRepository;
use App\Support\Akun\HakAkses;
use App\Support\Akun\KelolaPeran;
use App\Support\Akun\PeranAkun;
use App\Support\Akun\SesiAkun;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KelolaPeranTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    public function test_daftar_peran_beserta_hak_dan_akun(): void
    {
        $data = KelolaPeran::daftar(self::ADMIN);
        $this->assertSame(array_keys(PeranAkun::DAFTAR), array_column($data['peran'], 'kode'));
        [$admin, $leading] = $data['peran'];
        $this->assertSame([2, 0, [2, 5]], [$admin['jumlah_aktif'], $admin['jumlah_nonaktif'], array_column($admin['akun'], 'id')]);
        $this->assertSame([3, 1], [$leading['jumlah_aktif'], $leading['jumlah_nonaktif']]);
        $this->assertSame(HakAkses::perPeran()['leading'], array_column($leading['hak'], 'kunci'));
        $this->assertCount(count(HakAkses::HAK), $admin['hak']);
        $this->assertNotEmpty($data['versi']);

        $this->expectException(AuthorizationException::class);
        KelolaPeran::daftar(['id' => 1, 'role' => 'leading']);
    }

    public function test_ubah_peran_dengan_pengaman(): void
    {
        $repo = app(PenggunaRepository::class);
        $jadiAdmin = KelolaPeran::ubah(4, 'admin', null, self::ADMIN);
        $this->assertSame(['admin', []], [$jadiAdmin['akun']['role'], $jadiAdmin['akun']['leadings']]);
        $this->assertSame('Peran: Leading → Admin; Leading: 1.2, 1.4 → —', $jadiAdmin['ringkasan']);
        $this->assertSame('Akun Ahmad Fauzan, S.Pd. kini berperan Admin.', $jadiAdmin['pesan']);

        // Kembali ke leading wajib dengan leading aktif.
        try {
            KelolaPeran::ubah(4, 'leading', null, self::ADMIN);
            $this->fail('Akun leading tanpa leading seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('leadings', $e->errors());
        }
        $this->assertSame(['leading', ['1.2']], array_values(array_intersect_key(KelolaPeran::ubah(4, 'leading', ['1.2'], self::ADMIN)['akun'], array_flip(['role', 'leadings']))));
        $this->assertSame('Peran Ahmad Fauzan, S.Pd. tidak berubah.', KelolaPeran::ubah(4, 'leading', null, self::ADMIN)['pesan']);

        // Akun yang sedang masuk ikut berganti hak.
        SesiAkun::masuk(['id' => 4, 'role' => 'leading']);
        $this->assertFalse(HakAkses::boleh(StubData::currentUser(), 'area_admin'));

        foreach ([[2, 'leading', 'role'], [4, 'tamu', 'role']] as [$id, $peran, $kunci]) {
            try {
                KelolaPeran::ubah($id, $peran, ['1.6'], self::ADMIN);
                $this->fail("Peran {$peran} untuk akun {$id} seharusnya ditolak.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($kunci, $e->errors());
            }
        }
        $this->assertSame('admin', $repo->temukan(2)['role'], 'Peran akun sendiri terkunci.');

        $this->expectException(PengaturanBerubah::class);
        KelolaPeran::ubah(3, 'admin', null, self::ADMIN, 'versi-lama');
    }

    public function test_endpoint_manajemen_peran(): void
    {
        $this->getJson(route('pengaturan.peran'))->assertForbidden();
        $this->putJson(route('pengaturan.akun.peran', 3), ['peran' => 'admin'])->assertForbidden();

        session(['stub_role' => 'admin']);
        $versi = $this->getJson(route('pengaturan.peran'))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('peran.0.kode', 'admin')->assertJsonPath('peran.1.hak.0.kunci', 'buat_kegiatan')->json('versi');
        $this->putJson(route('pengaturan.akun.peran', 3), ['peran' => 'admin', 'versi' => $versi])
            ->assertOk()->assertJsonPath('akun.role', 'admin')->assertJsonPath('akun.leadings', []);
        $this->putJson(route('pengaturan.akun.peran', 3), ['peran' => 'leading', 'versi' => $versi])->assertStatus(409)->assertJsonStructure(['pesan', 'versi']);
        $this->putJson(route('pengaturan.akun.peran', 3), ['peran' => 'leading'])->assertStatus(422)->assertJsonStructure(['galat' => ['leadings']]);
        $this->putJson(route('pengaturan.akun.peran', 3), ['peran' => 'leading', 'leadings' => ['1.1']])->assertOk()->assertJsonPath('akun.leadings', ['1.1']);
        $this->putJson(route('pengaturan.akun.peran', 99), ['peran' => 'admin'])->assertNotFound();
        $this->putJson(route('pengaturan.akun.peran', 3), [])->assertStatus(422)->assertJsonStructure(['galat' => ['role']]);
    }
}
