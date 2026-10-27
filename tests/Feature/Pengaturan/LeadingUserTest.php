<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\PenggunaRepository;
use App\Livewire\Pengaturan\LeadingUser;
use App\Support\Navigation;
use Livewire\Livewire;
use Tests\TestCase;

class LeadingUserTest extends TestCase
{
    public function test_khusus_admin_dan_menu_aktif(): void
    {
        $this->get(route('pengaturan.leading.index'))->assertForbidden();
        $this->withSession(['stub_role' => 'admin'])->get(route('pengaturan.leading.index'))
            ->assertOk()
            ->assertSeeLivewire(LeadingUser::class)
            ->assertSee('<title>Leading &amp; User · ', false);

        $menu = collect(Navigation::groups('admin'))->flatMap(fn ($g) => $g['items'])->firstWhere('label', 'Leading & User');
        $this->assertTrue($menu['available']);
    }

    public function test_tab_leading_dengan_akun_tertaut_kegiatan_dan_status(): void
    {
        session(['stub_role' => 'admin']);
        $kegiatan16 = count(array_filter(app(KegiatanRepository::class)->semua(), fn ($k) => ($k['leading'] ?? null) === '1.6'));

        Livewire::test(LeadingUser::class)
            ->assertSet('tab', 'leading')
            ->assertSeeHtml('data-testid="daftar-leading"')
            ->assertSee('8 aktif · 1 nonaktif')
            ->assertSeeHtmlInOrder(['data-leading="1.1"', 'Waka Kurikulum', 'Wakil Kepala Bidang Kurikulum', 'Siti Rohmah, M.Pd.', 'Aktif'])
            ->assertSeeHtmlInOrder(['data-leading="1.5"', 'Ka. TU', 'line-through', 'title="Akun nonaktif"', 'Dewi Kartika, S.E.'])
            ->assertSeeHtmlInOrder(['data-leading="1.6"', 'Muslimin, M.Pd.', '>'.$kegiatan16.'<'])
            ->assertSeeHtmlInOrder(['data-leading="1.9"', 'Laboratorium Terpadu', 'Belum ada akun', 'Nonaktif'])
            ->assertSeeHtml('data-testid="leading-tanpa-akun"')
            // Aktif tanpa akun aktif: 1.3, 1.5 (akunnya nonaktif), 1.7, 1.8.
            ->assertSee('Leading tanpa akun aktif: 1.3 Waka Sarpras, 1.5 Ka. TU, 1.7 Mahad, 1.8 PMM')
            ->assertSee('Tambah leading');
    }

    public function test_tab_akun_dengan_peran_tautan_dan_status(): void
    {
        session(['stub_role' => 'admin']);

        Livewire::withQueryParams(['tab' => 'akun'])->test(LeadingUser::class)
            ->assertSet('tab', 'akun')
            ->assertSeeHtml('data-testid="daftar-akun"')
            ->assertSee('5 aktif (2 admin, 3 leading) · 1 nonaktif.')
            ->assertSeeHtmlInOrder(['data-akun="2"', 'Koordinator PUSKOM', 'Admin', 'Anda', 'puskom@example.com', 'Semua leading'])
            ->assertSeeHtmlInOrder(['data-akun="4"', 'Ahmad Fauzan, S.Pd.', 'Leading', '1.2 Waka Kesiswaan', '1.4 Waka Humas', 'Belum pernah masuk'])
            ->assertSeeHtmlInOrder(['data-akun="6"', 'Dewi Kartika, S.E.', 'Nonaktif', 'Masuk 20 November 2025'])
            ->assertSee('Tambah akun')
            ->set('tab', 'ngawur')
            ->assertSet('tab', 'leading');
    }

    public function test_ringkasan_mengikuti_data(): void
    {
        session(['stub_role' => 'admin']);
        app(PenggunaRepository::class)->simpan(['name' => 'Akun Sarpras', 'email' => 'sarpras@example.com', 'role' => 'leading', 'leadings' => ['1.3']]);

        $ringkas = Livewire::test(LeadingUser::class)->instance()->ringkasan();
        $this->assertSame(['leading_aktif' => 8, 'leading_nonaktif' => 1, 'tanpa_akun' => ['1.5', '1.7', '1.8'], 'akun_aktif' => 6, 'akun_nonaktif' => 1, 'per_peran' => ['admin' => 2, 'leading' => 4]], $ringkas);
    }
}
