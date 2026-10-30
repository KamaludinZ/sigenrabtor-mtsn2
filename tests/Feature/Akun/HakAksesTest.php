<?php

namespace Tests\Feature\Akun;

use App\Contracts\LeadingRepository;
use App\Http\Middleware\HanyaAdmin;
use App\Support\Akun\HakAkses;
use App\Support\Akun\SesiAkun;
use App\Support\Navigation;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Rute;
use InvalidArgumentException;
use Tests\TestCase;

class HakAksesTest extends TestCase
{
    public function test_hak_per_peran_dan_alasan_penolakan(): void
    {
        $admin = ['id' => 2, 'role' => 'admin', 'leadings' => []];
        $leading = ['id' => 1, 'role' => 'leading', 'leadings' => ['1.6']];
        $this->assertSame(array_fill_keys(array_keys(HakAkses::HAK), true), array_map(fn (array $h) => $h['boleh'], HakAkses::daftar($admin)));

        $hak = HakAkses::daftar($leading);
        $this->assertSame(['buat_kegiatan', 'susun_dokumen', 'pakai_ai'], array_keys(array_filter($hak, fn (array $h) => $h['boleh'])));
        $this->assertSame(HakAkses::PESAN_ADMIN, $hak['kelola_pengaturan']['alasan']);
        $this->assertSame(HakAkses::PESAN_TAMU, HakAkses::alasan(null, 'susun_dokumen'));
        $this->assertSame(HakAkses::PESAN_NONAKTIF, HakAkses::alasan(['id' => 6, 'role' => 'leading', 'leadings' => ['1.5']], 'susun_dokumen'), 'Akun 6 nonaktif.');

        // Akun leading tanpa leading aktif tidak bisa membuat kegiatan.
        $repo = app(LeadingRepository::class);
        $repo->simpan(['is_active' => false] + collect($repo->semua())->firstWhere('kode', '1.6'), '1.6');
        $this->assertSame(HakAkses::PESAN_TANPA_LEADING, HakAkses::alasan($leading, 'buat_kegiatan'));
        $this->assertTrue(HakAkses::boleh($leading, 'susun_dokumen'));

        try {
            HakAkses::pastikan($leading, 'monitoring');
            $this->fail('Akun leading tidak boleh membuka monitoring.');
        } catch (AuthorizationException $e) {
            $this->assertSame(HakAkses::PESAN_ADMIN, $e->getMessage());
        }
        $this->expectException(InvalidArgumentException::class);
        HakAkses::boleh($admin, 'hak_asing');
    }

    /** @return list<Route> Rute GET tanpa parameter wajib yang dilindungi HanyaAdmin. */
    private function ruteAdmin(): array
    {
        return array_values(array_filter(Rute::getRoutes()->getRoutes(), fn (Route $r) => in_array('GET', $r->methods(), true)
            && in_array(HanyaAdmin::class, $r->gatherMiddleware(), true)
            && ! preg_match('/\{[^}?]+\}/', $r->uri())));
    }

    public function test_matriks_hak_akses_rute_admin(): void
    {
        $rute = $this->ruteAdmin();
        $this->assertGreaterThan(30, count($rute));

        // Akun leading: semua rute admin ditolak 403 dengan pesan baku.
        foreach ($rute as $r) {
            $this->get('/'.$r->uri())->assertForbidden();
        }
        $this->getJson('/'.$rute[0]->uri())->assertForbidden()->assertJsonPath('message', HakAkses::PESAN_ADMIN);

        // Admin: halaman & data admin terbuka (ekspor/unduhan berkas dilewati — memanggil pengubah berkas).
        SesiAkun::masukPersona('admin');
        foreach ($rute as $r) {
            if (preg_match('/ekspor|unduh|template\/impor/', $r->uri())) {
                continue;
            }
            $status = $this->get('/'.$r->uri())->getStatusCode();
            $this->assertNotSame(403, $status, $r->uri());
            $this->assertLessThan(500, $status, $r->uri());
        }
    }

    public function test_endpoint_hak_dan_menu(): void
    {
        $this->getJson(route('akun.hak'))->assertOk()->assertJsonPath('peran', 'leading')
            ->assertJsonPath('hak.area_admin.boleh', false)->assertJsonPath('hak.buat_kegiatan.boleh', true);
        $this->assertSame([false, false], [collect(Navigation::groups('leading'))->contains('admin_only', true), HakAkses::boleh(StubData::currentUser(), 'area_admin')]);

        session(['stub_role' => 'admin']);
        $this->getJson(route('akun.hak'))->assertJsonPath('peran', 'admin')->assertJsonPath('hak.area_admin.boleh', true);
        $this->assertTrue(collect(Navigation::groups('admin'))->contains('admin_only', true));
    }
}
