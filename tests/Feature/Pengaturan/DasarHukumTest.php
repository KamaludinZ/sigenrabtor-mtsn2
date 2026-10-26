<?php

namespace Tests\Feature\Pengaturan;

use App\Livewire\Pengaturan\DasarHukum;
use App\Livewire\Pengaturan\ProfilMadrasah;
use Livewire\Livewire;
use Tests\TestCase;

class DasarHukumTest extends TestCase
{
    public function test_khusus_admin_menu_profil_aktif_dan_tab_terhubung(): void
    {
        $this->get(route('pengaturan.madrasah.dasar_hukum'))->assertForbidden();

        $this->withSession(['stub_role' => 'admin'])->get(route('pengaturan.madrasah.dasar_hukum'))
            ->assertOk()
            ->assertSeeLivewire(DasarHukum::class)
            ->assertSee('<title>Dasar Hukum &amp; Latar Belakang · ', false)
            ->assertSeeHtml('href="'.route('pengaturan.madrasah.index').'"')
            ->assertSeeHtml('aria-current="page"')
            ->assertSeeHtml('href="'.route('pengaturan.madrasah.index', ['tab' => 'pejabat']).'"');

        session(['stub_role' => 'admin']);
        Livewire::test(ProfilMadrasah::class)
            ->assertSeeHtml('href="'.route('pengaturan.madrasah.dasar_hukum').'" wire:navigate data-tab="dasar_hukum"');
    }

    public function test_layout_dasar_hukum_latar_belakang_dan_pratinjau(): void
    {
        session(['stub_role' => 'admin']);

        Livewire::test(DasarHukum::class)
            ->assertSet('tahun', '')
            ->assertSee('4 butir · tercetak bernomor di bagian 2 KAK/TOR sesuai urutan ini.')
            ->assertSeeHtmlInOrder(['data-butir="1"', 'Undang-Undang Republik Indonesia Nomor 20 Tahun 2003', 'data-butir="4"', 'Rencana Kerja Tahunan Madrasah (RKTM) MTsN 2 Kota Malang Tahun 2026.'])
            ->assertSeeHtmlInOrder(['data-testid="paragraf-baku"', 'Madrasah Tsanawiyah Negeri 2 Kota Malang, sebagai lembaga pendidikan Islam', 'Tahun Anggaran 2026'])
            ->assertSeeHtmlInOrder(['data-testid="kalimat-sisip"', 'Salah satu kegiatan yang relevan dan mendukung program Perjanjian Kinerja (Perkin) Kepala Madrasah adalah kegiatan ', '<mark', 'nama kegiatan', '</mark>.'])
            ->assertSeeHtmlInOrder(['data-testid="pratinjau-dasar-hukum"', '1. Latar Belakang', 'adalah kegiatan', '2. Dasar Hukum', 'Undang-Undang Republik Indonesia Nomor 20 Tahun 2003'])
            ->assertSee('${latar_belakang}', false)
            ->assertSeeHtml('data-testid="kesiapan-dasar-hukum"');
    }

    public function test_tahun_lain_terkunci_hanya_dilihat(): void
    {
        session(['stub_role' => 'admin']);

        Livewire::test(DasarHukum::class)
            ->set('tahun', '2025')
            ->assertSeeHtml('aria-pressed="true" data-tahun="2025"')
            ->assertSeeHtml('data-testid="dasar-hukum-terkunci"')
            ->assertSee('Rencana Kerja Tahunan Madrasah (RKTM) MTsN 2 Kota Malang Tahun 2025.')
            ->assertSee('Tahun Anggaran 2025')
            ->set('tahun', '2026')
            ->assertSet('tahun', '')
            ->set('tahun', '1999')
            ->assertSet('tahun', '');
        Livewire::withQueryParams(['ta' => '2025'])->test(DasarHukum::class)->assertSet('tahun', '2025');
    }
}
