<?php

namespace Tests\Feature\Pengaturan;

use App\Livewire\Pengaturan\Ikhtisar;
use App\Support\MasterKode;
use App\Support\Pengaturan\BagianPengaturan;
use App\Support\Pengaturan\KesiapanPengaturan;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

class IkhtisarPengaturanTest extends TestCase
{
    public function test_hanya_admin_yang_bisa_membuka_pengaturan(): void
    {
        $this->get(route('pengaturan.index'))->assertForbidden()->assertSee('Halaman ini khusus admin.');

        $this->withSession(['stub_role' => 'admin'])
            ->get(route('pengaturan.index'))
            ->assertOk()
            ->assertSeeLivewire(Ikhtisar::class)
            ->assertSee('<title>Pengaturan · ', false);
    }

    public function test_menu_ikhtisar_pengaturan_aktif_untuk_admin(): void
    {
        $this->get(route('kegiatan.index'))->assertDontSee('Ikhtisar Pengaturan');

        $this->withSession(['stub_role' => 'admin'])
            ->get(route('pengaturan.index'))
            ->assertSeeHtml('href="'.route('pengaturan.index').'"')
            ->assertSeeInOrder(['Ikhtisar Pengaturan', 'Tahun Anggaran', 'Profil &amp; Pejabat'], false)
            ->assertSeeHtml('aria-current="page"');
    }

    public function test_peta_bagian_dikelompokkan_dan_aktif_bila_rute_ada(): void
    {
        $this->assertSame(['tahun', 'template', 'format', 'madrasah', 'leading', 'master', 'ai'], array_column(BagianPengaturan::semua(), 'kunci'));
        $this->assertSame(['dokumen', 'dokumen', 'dokumen', 'madrasah', 'madrasah', 'kode', 'kode'], array_column(BagianPengaturan::semua(), 'kelompok'));
        $this->assertTrue(BagianPengaturan::cari('tahun')['tersedia']);
        $this->assertTrue(BagianPengaturan::cari('madrasah')['tersedia']);
        $this->assertTrue(BagianPengaturan::cari('leading')['tersedia']);
        $this->assertTrue(BagianPengaturan::cari('ai')['tersedia']);
        $this->assertNull(BagianPengaturan::cari('tidak-ada'));

        Livewire::test(Ikhtisar::class)
            ->assertSeeInOrder(['Tahun &amp; dokumen', 'Tahun Anggaran', 'Template Dokumen', 'Data madrasah', 'Profil &amp; Pejabat', 'Leading &amp; User', 'Master kode &amp; AI', 'Master Kode', 'Pengaturan AI'], false)
            ->assertSeeHtml('data-testid="bagian-tahun" data-tersedia="ya"')
            ->assertSeeHtml('href="'.route('pengaturan.tahun.index').'" wire:navigate class="btn-secondary')
            ->assertSeeHtml('data-testid="bagian-madrasah" data-tersedia="ya"')
            ->assertSeeHtml('data-testid="bagian-leading" data-tersedia="ya"')
            ->assertSeeHtml('data-testid="bagian-ai" data-tersedia="ya"')
            ->assertDontSee('Segera disiapkan')
            ->assertSee('Desain cover + posisi teks')
            ->assertSee('NIP dan nama pejabat tidak pernah dikirim.');
    }

    public function test_kesiapan_tahun_anggaran_dari_data_tiruan(): void
    {
        $kesiapan = KesiapanPengaturan::tahunAktif();
        $status = array_column($kesiapan->butir, 'siap', 'kunci');

        $this->assertSame(2026, $kesiapan->tahun);
        $this->assertSame(
            ['tahun' => true, 'sk_rktm' => false, 'profil' => false, 'pejabat' => false, 'dasar_hukum' => true, 'leading' => true, 'master' => true, 'cover' => false, 'kak' => false, 'ai' => false],
            $status,
        );
        $this->assertSame([4, 7], [$kesiapan->jumlahWajibSiap(), $kesiapan->jumlahWajib()]);
        $this->assertSame(['sk_rktm', 'tahun'], collect($kesiapan->untukBagian('tahun'))->pluck('kunci')->sort()->values()->all());

        $keterangan = array_column($kesiapan->butir, 'keterangan', 'kunci');
        $this->assertSame('Alamat, telepon, logo kop belum diisi.', $keterangan['profil']);
        $this->assertSame('Nama & NIP Kepala Madrasah dan Ketua Komite belum diisi.', $keterangan['pejabat']);
        $this->assertSame('8 leading · 5 akun aktif.', $keterangan['leading']);
        $this->assertStringStartsWith(count(MasterKode::kodeSk(2026)).' SK · ', $keterangan['master']);

        Livewire::test(Ikhtisar::class)
            ->assertSee('Kesiapan Tahun Anggaran 2026')
            ->assertSeeHtml('data-testid="jumlah-wajib-siap">4/7 wajib siap')
            ->assertSeeHtml('data-kesiapan="sk_rktm" data-siap="tidak"')
            ->assertSeeHtml('data-kesiapan="master" data-siap="ya"')
            ->assertSeeHtml('href="'.route('pengaturan.ai.index').'" wire:navigate')
            ->assertSeeHtml('href="'.route('pengaturan.leading.index').'" wire:navigate')
            ->assertSeeHtml('href="'.route('master.index').'" wire:navigate')
            ->assertSeeHtml('data-status="perlu"')
            ->assertSee('Opsional · belum diatur');
    }

    public function test_kepala_pengaturan_dengan_remah_dan_slot_aksi(): void
    {
        $html = Blade::render('<x-pengaturan.kepala judul="Tahun Anggaran" deskripsi="Atur tahun."><x-slot:aksi><button>Tambah</button></x-slot:aksi></x-pengaturan.kepala>');

        $this->assertStringContainsString('aria-label="Breadcrumb"', $html);
        $this->assertStringContainsString('href="'.route('pengaturan.index').'"', $html);
        $this->assertStringContainsString('Berlaku TA 2026', $html);
        $this->assertStringContainsString('<button>Tambah</button>', $html);

        $tanpaRemah = Blade::render('<x-pengaturan.kepala judul="Pengaturan" :remah="false" />');
        $this->assertStringNotContainsString('aria-label="Breadcrumb"', $tanpaRemah);
    }
}
