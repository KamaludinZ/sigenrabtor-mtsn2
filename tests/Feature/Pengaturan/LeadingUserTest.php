<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\LeadingRepository;
use App\Contracts\PejabatRepository;
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
            // Bawaan: urut nama.
            ->assertSeeHtmlInOrder(['data-akun="4"', 'data-akun="6"', 'data-akun="2"', 'data-akun="1"', 'data-akun="5"', 'data-akun="3"'])
            ->assertSeeHtmlInOrder(['data-akun="2"', 'Koordinator PUSKOM', 'Anda', 'puskom@example.com', 'Admin', 'Semua leading', '6 Januari 2026', 'Aktif'])
            ->assertSeeHtmlInOrder(['data-akun="4"', 'Ahmad Fauzan, S.Pd.', 'Leading', '1.2 Waka Kesiswaan', '1.4 Waka Humas', 'Belum pernah'])
            ->assertSeeHtmlInOrder(['data-akun="6"', 'Dewi Kartika, S.E.', '20 November 2025', 'Nonaktif'])
            ->assertSeeHtml('aria-label="Ubah akun Muslimin, M.Pd. (sedang disiapkan)"')
            ->assertSee('Tambah akun')
            ->set('tab', 'ngawur')
            ->assertSet('tab', 'leading');
    }

    public function test_tabel_akun_cari_saring_dan_urut(): void
    {
        session(['stub_role' => 'admin']);
        $id = fn ($komponen) => array_column($komponen->instance()->akunTampil(), 'id');

        $komponen = Livewire::withQueryParams(['tab' => 'akun'])->test(LeadingUser::class);
        $this->assertSame([4, 6, 2, 1, 5, 3], $id($komponen));

        $komponen->set('peranAkun', 'admin');
        $this->assertSame([2, 5], $id($komponen));
        $komponen->set('peranAkun', 'leading')->set('statusAkun', 'aktif');
        $this->assertSame([4, 1, 3], $id($komponen));
        $komponen->assertSee('Menampilkan 3 dari 6 akun.');
        $komponen->set('statusAkun', 'nonaktif');
        $this->assertSame([6], $id($komponen));
        $komponen->call('hapusSaringanAkun')->set('cariAkun', 'kurikulum');
        $this->assertSame([3], $id($komponen), 'Cari di jabatan.');
        $komponen->set('cariAkun', 'PUSKOM@');
        $this->assertSame([2], $id($komponen), 'Cari di surel tanpa memandang huruf besar.');
        $komponen->set('cariAkun', 'tidak ada')->assertSeeHtml('data-testid="akun-kosong"')->call('hapusSaringanAkun');

        // Terakhir masuk: terbaru dulu, yang belum pernah masuk selalu di akhir.
        $komponen->call('urutkanAkun', 'masuk')->assertSet('urutAkun', 'masuk')->assertSet('arahAkun', '');
        $this->assertSame([2, 1, 6, 4, 5, 3], $id($komponen));
        $komponen->call('urutkanAkun', 'masuk')->assertSet('arahAkun', 'asc');
        $this->assertSame([6, 1, 2, 4, 5, 3], $id($komponen));
        $komponen->call('urutkanAkun', 'kegiatan');
        $this->assertSame(1, $id($komponen)[0], 'Pembuat kegiatan terbanyak lebih dulu (akun 1: 2 kegiatan).');
        $komponen->call('urutkanAkun', 'ngawur')->assertSet('urutAkun', 'kegiatan');
        $komponen->set('peranAkun', 'ngawur')->assertSet('peranAkun', '');

        Livewire::withQueryParams(['tab' => 'akun', 'peran_akun' => 'admin', 'urut_akun' => 'masuk'])->test(LeadingUser::class)
            ->assertSet('peranAkun', 'admin')
            ->assertSet('urutAkun', 'masuk');
    }

    public function test_ringkasan_mengikuti_data(): void
    {
        session(['stub_role' => 'admin']);
        app(PenggunaRepository::class)->simpan(['name' => 'Akun Sarpras', 'email' => 'sarpras@example.com', 'role' => 'leading', 'leadings' => ['1.3']]);

        $ringkas = Livewire::test(LeadingUser::class)->instance()->ringkasan();
        $this->assertSame(['leading_aktif' => 8, 'leading_nonaktif' => 1, 'tanpa_akun' => ['1.5', '1.7', '1.8'], 'akun_aktif' => 6, 'akun_nonaktif' => 1, 'per_peran' => ['admin' => 2, 'leading' => 4]], $ringkas);
    }

    public function test_tabel_leading_cari_saring_dan_urut(): void
    {
        session(['stub_role' => 'admin']);
        app(LeadingRepository::class)->simpan(['kode' => '1.10', 'nama_unit' => 'Literasi', 'jabatan_penanda_tangan' => 'Koordinator Literasi', 'urutan' => 0]);
        $kode = fn ($komponen) => array_column($komponen->instance()->leadingTampil(), 'kode');

        $komponen = Livewire::test(LeadingUser::class);
        $this->assertSame(['1.10', '1.1', '1.2', '1.3', '1.4', '1.5', '1.6', '1.7', '1.8', '1.9'], $kode($komponen), 'Bawaan: urutan tampil.');

        $komponen->call('urutkanLeading', 'kode')->assertSet('urutLeading', 'kode')->assertSet('arahLeading', '');
        $this->assertSame(['1.1', '1.2', '1.3', '1.4', '1.5', '1.6', '1.7', '1.8', '1.9', '1.10'], $kode($komponen), 'Kode urut natural.');
        $komponen->call('urutkanLeading', 'kode')->assertSet('arahLeading', 'desc');
        $this->assertSame(['1.10', '1.9', '1.8'], array_slice($kode($komponen), 0, 3));
        $komponen->call('urutkanLeading', 'kegiatan')->assertSet('urutLeading', 'kegiatan')->assertSet('arahLeading', '');
        $this->assertSame('1.6', $kode($komponen)[0], 'Kegiatan terbanyak lebih dulu.');
        $komponen->call('urutkanLeading', 'nama');
        $this->assertSame(['1.5', '1.9', '1.10', '1.7', '1.8'], array_slice($kode($komponen), 0, 5), 'Ka. TU, Laboratorium, Literasi, Mahad, PMM.');
        $komponen->call('urutkanLeading', 'ngawur')->assertSet('urutLeading', 'nama');

        $komponen->set('statusLeading', 'nonaktif');
        $this->assertSame(['1.9'], $kode($komponen));
        $komponen->set('statusLeading', 'aktif')->set('cariLeading', 'wakil kepala');
        $this->assertSame(['1.4', '1.2', '1.1', '1.3'], $kode($komponen), 'Cari di jabatan, tetap terurut nama (Humas, Kesiswaan, Kurikulum, Sarpras).');
        $komponen->assertSee('Menampilkan 4 dari 10 leading.');
        $komponen->set('cariLeading', 'tidak ada')
            ->assertSeeHtml('data-testid="leading-kosong"')
            ->call('hapusSaringanLeading')
            ->assertSet('cariLeading', '')
            ->assertSet('statusLeading', '');
        $komponen->set('statusLeading', 'ngawur')->assertSet('statusLeading', '');

        Livewire::withQueryParams(['urut_leading' => 'kode', 'arah_leading' => 'desc', 'status_leading' => 'aktif'])->test(LeadingUser::class)
            ->assertSet('urutLeading', 'kode')
            ->assertSet('arahLeading', 'desc')
            ->assertSeeHtml('aria-sort="descending"');
    }

    public function test_kolom_pejabat_tahun_aktif_dan_tombol_aksi(): void
    {
        session(['stub_role' => 'admin']);
        app(PejabatRepository::class)->simpan(2026, 'leading', '1.1', ['jabatan' => 'Wakil Kepala Bidang Kurikulum', 'nama' => 'Siti Rohmah, M.Pd.', 'nip' => null]);

        Livewire::test(LeadingUser::class)
            ->assertSee('Pejabat TA 2026')
            ->assertSeeHtmlInOrder(['data-leading="1.1"', 'data-kolom="pejabat"', 'Siti Rohmah, M.Pd.'])
            ->assertSeeHtmlInOrder(['data-leading="1.2"', 'data-kolom="pejabat"', 'Pejabat belum diisi'])
            ->assertSeeHtml('href="'.route('pengaturan.madrasah.index', ['tab' => 'pejabat']).'"')
            ->assertSeeHtml('aria-label="Ubah leading 1.1"')
            ->assertSeeHtml('aria-label="Aktifkan leading 1.9"')
            ->assertSeeHtml('aria-label="Nonaktifkan leading 1.1"');
    }
}
