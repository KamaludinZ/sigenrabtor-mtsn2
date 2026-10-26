<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\RiwayatPengaturanRepository;
use App\Livewire\Pengaturan\ProfilMadrasah;
use App\Support\Dokumen\NilaiVariabel;
use App\Support\Pengaturan\KesiapanPengaturan;
use App\Support\Stub\StubData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_formulir_profil_menyimpan_merapikan_dan_mencatat_riwayat(): void
    {
        session(['stub_role' => 'admin']);

        Livewire::test(ProfilMadrasah::class)
            ->call('bukaUbahProfil')
            ->assertSet('ubahProfil', true)
            ->assertSet('isian.nama', 'Madrasah Tsanawiyah Negeri 2 Kota Malang')
            ->assertSet('isian.alamat', '')
            ->assertSeeHtml('data-testid="formulir-profil"')
            ->set('isian.alamat', '  Jl. Cinta   Bakti No. 1,  Kota Malang  ')
            ->set('isian.telepon', '(0341) 551 752')
            ->set('isian.visi', "Unggul dalam   prestasi,\r\n\n\n\nberakhlak mulia.")
            ->call('simpanProfil')
            ->assertHasNoErrors()
            ->assertSet('ubahProfil', false)
            ->assertDispatched('notify', message: 'Profil madrasah tersimpan dan langsung dipakai kop dokumen.', type: 'success')
            ->assertSeeHtml('data-isian="alamat" data-terisi="ya"')
            ->assertSee('Jl. Cinta Bakti No. 1, Kota Malang')
            ->assertSeeHtmlInOrder(['data-testid="pratinjau-kop"', 'Jl. Cinta Bakti No. 1, Kota Malang', 'Telepon (0341) 551 752']);

        $profil = StubData::madrasah();
        $this->assertSame(['Jl. Cinta Bakti No. 1, Kota Malang', '(0341) 551 752', "Unggul dalam prestasi,\n\nberakhlak mulia."], [$profil['alamat'], $profil['telepon'], $profil['visi']]);
        $this->assertSame(['Jl. Cinta Bakti No. 1, Kota Malang', '(0341) 551 752'], array_slice(NilaiVariabel::untuk(['nama_kegiatan' => 'Uji'], 'DIPA')['kop'], 1), 'Kop dokumen langsung memakai profil baru.');
        $this->assertSame('Logo kop belum diisi.', array_column(KesiapanPengaturan::tahunAktif()->butir, 'keterangan', 'kunci')['profil'], 'Tinggal logo yang kurang.');

        $riwayat = app(RiwayatPengaturanRepository::class)->semua();
        $terbaru = $riwayat[0];
        $this->assertSame('ubah_profil', $terbaru['aksi']);
        $this->assertStringContainsString('Alamat: — → Jl. Cinta Bakti No. 1, Kota Malang', $terbaru['keterangan']);
        $this->assertStringContainsString('Telepon: — → (0341) 551 752', $terbaru['keterangan']);

        Livewire::test(ProfilMadrasah::class)->call('bukaUbahProfil')->call('simpanProfil')
            ->assertDispatched('notify', message: 'Tidak ada yang berubah.', type: 'info');
        $this->assertCount(count($riwayat), app(RiwayatPengaturanRepository::class)->semua(), 'Simpan tanpa perubahan tidak dicatat.');
    }

    public function test_validasi_formulir_profil(): void
    {
        session(['stub_role' => 'admin']);

        Livewire::test(ProfilMadrasah::class)
            ->call('bukaUbahProfil')
            ->set('isian.nama', '   ')
            ->set('isian.kota', '')
            ->set('isian.telepon', 'nomor kantor')
            ->set('isian.visi', str_repeat('a', 1001))
            ->call('simpanProfil')
            ->assertHasErrors(['isian.nama' => 'required', 'isian.kota' => 'required', 'isian.telepon' => 'regex', 'isian.visi' => 'max'])
            ->assertSee('Nama madrasah wajib diisi — tercetak di kop dan cover.')
            ->assertSet('ubahProfil', true)
            ->call('batalUbahProfil')
            ->assertSet('ubahProfil', false)
            ->assertHasNoErrors();

        $this->assertSame('Madrasah Tsanawiyah Negeri 2 Kota Malang', StubData::madrasah()['nama'], 'Isian tidak sah tidak tersimpan.');
    }

    public function test_unggah_ganti_dan_hapus_logo(): void
    {
        session(['stub_role' => 'admin']);
        $this->get(route('madrasah.logo'))->assertNotFound();

        Livewire::test(ProfilMadrasah::class)
            ->set('logo', UploadedFile::fake()->image('kecil.png', 120, 120))
            ->assertHasErrors(['logo' => 'dimensions'])
            ->assertSee('Logo terlalu kecil')
            ->call('batalLogo')
            ->set('logo', UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'))
            ->assertHasErrors('logo')
            ->call('batalLogo')
            ->set('logo', UploadedFile::fake()->image('logo-mtsn.png', 400, 400))
            ->assertHasNoErrors()
            ->assertSee('logo-mtsn.png')
            ->assertSee('Logo baru — belum disimpan.')
            ->call('simpanLogo')
            ->assertSet('logo', null)
            ->assertDispatched('notify', message: 'Logo kop tersimpan.', type: 'success')
            ->assertSee('Logo tersimpan')
            ->assertSeeHtml('data-testid="kop-logo"');

        $pertama = StubData::madrasah()['logo_path'];
        $this->assertNotNull($pertama);
        Storage::disk('local')->assertExists($pertama);
        $this->get(route('madrasah.logo'))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame('[Logo madrasah]', NilaiVariabel::untuk([], null)['logo']);

        Livewire::test(ProfilMadrasah::class)
            ->set('logo', UploadedFile::fake()->image('logo-baru.jpg', 300, 300))
            ->call('simpanLogo');
        Storage::disk('local')->assertMissing($pertama);
        $this->assertSame('Logo kop diganti (logo-baru.jpg).', app(RiwayatPengaturanRepository::class)->semua()[0]['keterangan']);

        Livewire::test(ProfilMadrasah::class)
            ->call('$set', 'konfirmasiHapusLogo', true)
            ->assertSee('Hapus logo kop? Dokumen dicetak tanpa logo sampai diunggah lagi.')
            ->call('hapusLogo')
            ->assertSet('konfirmasiHapusLogo', false)
            ->assertSee('Belum diunggah');
        $this->assertNull(StubData::madrasah()['logo_path']);
        $this->assertSame('hapus_logo', app(RiwayatPengaturanRepository::class)->semua()[0]['aksi']);
    }
}
