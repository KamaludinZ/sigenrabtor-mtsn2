<?php

namespace Tests\Feature\Pengaturan;

use App\Livewire\Pengaturan\ProfilMadrasah;
use Livewire\Livewire;
use Tests\TestCase;

class ProfilMadrasahTest extends TestCase
{
    public function test_khusus_admin_dan_menu_aktif(): void
    {
        $this->get(route('pengaturan.madrasah.index'))->assertForbidden();

        $this->withSession(['stub_role' => 'admin'])->get(route('pengaturan.madrasah.index'))
            ->assertOk()
            ->assertSeeLivewire(ProfilMadrasah::class)
            ->assertSee('<title>Profil &amp; Pejabat · ', false)
            ->assertSeeHtml('href="'.route('pengaturan.madrasah.index').'"')
            ->assertSeeHtml('aria-current="page"')
            ->assertSee('Berlaku TA 2026');
    }

    public function test_tab_profil_menandai_isian_kop_yang_belum_diisi(): void
    {
        session(['stub_role' => 'admin']);

        Livewire::test(ProfilMadrasah::class)
            ->assertSet('tab', 'profil')
            ->assertSeeHtml('data-isian="nama" data-terisi="ya"')
            ->assertSee('Madrasah Tsanawiyah Negeri 2 Kota Malang')
            ->assertSeeHtml('data-isian="alamat" data-terisi="tidak"')
            ->assertSeeHtml('data-isian="telepon" data-terisi="tidak"')
            ->assertSeeHtml('data-isian="visi" data-terisi="tidak"')
            ->assertSee('Belum diunggah')
            ->assertSeeHtmlInOrder(['data-testid="pratinjau-kop"', 'Madrasah Tsanawiyah Negeri 2 Kota Malang', '[Alamat]', '[telepon]', '${nama_madrasah}', '${kop}', '${kota}', '${logo}'])
            ->assertSeeHtmlInOrder(['data-tab="profil"', '3', 'data-tab="pejabat"', '10'])
            ->assertSeeHtml('data-kesiapan="profil" data-siap="tidak"')
            ->assertSee('Alamat, telepon, logo kop belum diisi.');

        $komponen = Livewire::test(ProfilMadrasah::class)->instance();
        $this->assertSame(['alamat', 'telepon', 'logo_path'], $komponen->kurang()['profil'], 'Visi opsional.');
    }

    public function test_tab_pejabat_pimpinan_leading_dan_blok_tanda_tangan(): void
    {
        session(['stub_role' => 'admin']);

        $komponen = Livewire::withQueryParams(['tab' => 'pejabat'])->test(ProfilMadrasah::class)
            ->assertSet('tab', 'pejabat')
            ->assertSeeHtml('data-pejabat="kepala" data-lengkap="tidak"')
            ->assertSeeHtml('data-pejabat="komite" data-lengkap="tidak"')
            ->assertSee('Kepala MTsN 2 Kota Malang')
            ->assertSee('"Mengetahui" di dokumen DIPA maupun Komite')
            ->assertSeeHtmlInOrder(['data-pejabat-leading="1.1"', 'Waka Kurikulum', 'Wakil Kepala Bidang Kurikulum', 'Belum diisi'])
            ->assertSeeHtmlInOrder(['data-pejabat-leading="1.6"', 'Tim Penjamin Mutu', 'Muslimin, M.Pd.'])
            ->assertSeeHtmlInOrder(['data-testid="pratinjau-ttd"', 'Mengetahui,', 'Ketua Komite MTsN 2 Kota Malang', '[Nama Ketua Komite]', 'Malang, [tanggal dokumen]', 'Menyetujui,', '[Nama Kepala Madrasah]'])
            ->assertSee('Nama dan NIP pejabat tidak pernah dikirim ke penyedia AI.')
            ->instance();

        $this->assertCount(8, $komponen->pejabat['leading']);
        $this->assertSame(['Kepala Madrasah', 'Ketua Komite'], array_slice($komponen->kurang()['pejabat'], 0, 2));
    }

    public function test_tab_tidak_dikenal_atau_belum_tersedia_kembali_ke_profil(): void
    {
        session(['stub_role' => 'admin']);

        Livewire::withQueryParams(['tab' => 'dasar_hukum'])->test(ProfilMadrasah::class)->assertSet('tab', 'profil');
        Livewire::test(ProfilMadrasah::class)
            ->set('tab', 'pejabat')->assertSeeHtml('data-testid="pejabat-leading"')
            ->set('tab', 'acak')->assertSet('tab', 'profil')
            ->assertSee('Dasar hukum & latar belakang')
            ->assertSee('Segera');
    }
}
