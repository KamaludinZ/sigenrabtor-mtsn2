<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\PejabatRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Livewire\Pengaturan\ProfilMadrasah;
use App\Support\Dokumen\NilaiVariabel;
use App\Support\Dokumen\SusunanKak;
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
            ->assertSee('"Mengetahui" di dokumen DIPA & Komite')
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
            ->assertSeeHtml('href="'.route('pengaturan.madrasah.dasar_hukum').'"');
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

    public function test_ubah_kepala_madrasah_dengan_nip_dan_riwayat(): void
    {
        session(['stub_role' => 'admin']);

        Livewire::withQueryParams(['tab' => 'pejabat'])->test(ProfilMadrasah::class)
            ->call('bukaUbahPejabat', 'kepala')
            ->assertSet('ubahPejabat', 'kepala')
            ->assertSet('isianPejabat.jabatan', 'Kepala MTsN 2 Kota Malang')
            ->assertSeeHtml('data-testid="formulir-pejabat"')
            ->assertSeeHtml('data-komponen="input-pejabat"')
            ->set('isianPejabat.nama', '  Dr. H. Ahmad   Fauzi, M.Pd. ')
            ->call('simpanPejabat')
            ->assertHasErrors(['isianPejabat.nip' => 'required'])
            ->assertSee('NIP Kepala Madrasah wajib diisi')
            ->set('isianPejabat.nip', '1970010119950310')
            ->call('simpanPejabat')
            ->assertHasErrors('isianPejabat.nip')
            ->assertSee('NIP harus 18 angka (sekarang 16).')
            ->set('isianPejabat.nip', '19700101 199503 1 001')
            ->call('simpanPejabat')
            ->assertHasNoErrors()
            ->assertSet('ubahPejabat', null)
            ->assertDispatched('notify', message: 'Kepala Madrasah tersimpan untuk TA 2026.', type: 'success')
            ->assertSeeHtml('data-pejabat="kepala" data-lengkap="ya"')
            ->assertSeeHtmlInOrder(['data-testid="pratinjau-ttd"', 'Menyetujui,', 'Dr. H. Ahmad Fauzi, M.Pd.', 'NIP 19700101 199503 1 001']);

        $this->assertSame(['jabatan' => 'Kepala MTsN 2 Kota Malang', 'nama' => 'Dr. H. Ahmad Fauzi, M.Pd.', 'nip' => '197001011995031001'], StubData::pejabat()['kepala'], 'NIP disimpan tanpa pemisah.');
        $this->assertSame(['Kepala MTsN 2 Kota Malang', 'Dr. H. Ahmad Fauzi, M.Pd.', 'NIP. 19700101 199503 1 001'], NilaiVariabel::untuk([], null)['ttd_kepala']);
        $this->assertSame('Kepala Madrasah TA 2026 — Nama: — → Dr. H. Ahmad Fauzi, M.Pd.; NIP: — → 19700101 199503 1 001', app(RiwayatPengaturanRepository::class)->semua()[0]['keterangan']);
        $this->assertSame('Drs. H. Mahmud Arifin, M.Pd.I.', app(PejabatRepository::class)->untukTahun(2025)['kepala']['nama'], 'Tahun lain menyimpan pejabatnya sendiri.');
    }

    public function test_pejabat_leading_menjadi_pembuat_dokumen(): void
    {
        $kegiatan = ['nama_kegiatan' => 'Uji', 'leading' => '1.6', 'tanggal_dokumen' => '2026-07-01'];
        $this->assertSame(['Ketua Tim Penjamin Mutu', 'Muslimin, M.Pd.'], NilaiVariabel::untuk($kegiatan, 'DIPA')['ttd_leading'], 'Sebelum diisi: nama akun leading.');

        session(['stub_role' => 'admin']);
        $this->assertNull(NilaiVariabel::untuk($kegiatan, 'DIPA')['ttd_leading'], 'Admin bukan pemegang leading → pembuat kosong.');
        Livewire::withQueryParams(['tab' => 'pejabat'])->test(ProfilMadrasah::class)
            ->call('bukaUbahPejabat', 'leading:1.6')
            ->assertSet('isianPejabat.jabatan', 'Ketua Tim Penjamin Mutu')
            ->set('isianPejabat.nama', 'Siti Aminah, S.Pd.')
            ->set('isianPejabat.nip', '19850312 201001 2 004')
            ->set('isianPejabat.jabatan', 'Koordinator Tim Penjamin Mutu')
            ->call('simpanPejabat')
            ->assertHasNoErrors()
            ->assertSeeHtmlInOrder(['data-pejabat-leading="1.6"', 'Koordinator Tim Penjamin Mutu', 'Siti Aminah, S.Pd.', '19850312 201001 2 004'])
            ->call('bukaUbahPejabat', 'leading:9.9')
            ->assertSet('ubahPejabat', null);

        session(['stub_role' => 'leading']);
        $this->assertSame(['Koordinator Tim Penjamin Mutu', 'Siti Aminah, S.Pd.', 'NIP. 19850312 201001 2 004'], NilaiVariabel::untuk($kegiatan, 'DIPA')['ttd_leading'], 'Pejabat leading menggantikan nama akun.');
        $pembuat = collect(SusunanKak::dari($kegiatan + ['id' => null], 'DIPA')->bagian)->firstWhere('nomor', 9)['isi'][0]['kolom'];
        $this->assertSame(['Pembuat,', 'Koordinator Tim Penjamin Mutu', 'Siti Aminah, S.Pd.', '19850312 201001 2 004'], array_values(array_intersect_key(collect($pembuat)->firstWhere('peran', 'Pembuat,'), array_flip(['peran', 'jabatan', 'nama', 'nip']))));
    }

    public function test_komite_nip_opsional_dan_tahun_terkunci_hanya_dilihat(): void
    {
        session(['stub_role' => 'admin']);

        Livewire::test(ProfilMadrasah::class)
            ->call('bukaUbahPejabat', 'komite')
            ->set('isianPejabat.nama', 'H. Bambang Sutrisno')
            ->set('isianPejabat.jabatan', '')
            ->call('simpanPejabat')
            ->assertHasErrors(['isianPejabat.jabatan' => 'required'])
            ->set('isianPejabat.jabatan', 'Ketua Komite MTsN 2 Kota Malang')
            ->call('simpanPejabat')
            ->assertHasNoErrors();
        $this->assertSame(['H. Bambang Sutrisno', null], [StubData::pejabat()['komite']['nama'], StubData::pejabat()['komite']['nip']]);

        app(TahunAnggaranRepository::class)->aktifkan(2025, 'Uji');
        Livewire::withQueryParams(['tab' => 'pejabat'])->test(ProfilMadrasah::class)
            ->assertSeeHtml('data-testid="pejabat-terkunci"')
            ->assertDontSeeHtml("wire:click=\"bukaUbahPejabat('kepala')\"")
            ->call('bukaUbahPejabat', 'kepala')
            ->assertSet('ubahPejabat', null);
    }

    public function test_lihat_pejabat_tahun_lain_terkunci_dan_perbandingan(): void
    {
        session(['stub_role' => 'admin']);

        $halaman = Livewire::withQueryParams(['tab' => 'pejabat'])->test(ProfilMadrasah::class)
            ->assertSet('tahunPejabat', '')
            ->assertSeeHtml('aria-pressed="true" data-tahun-pejabat="2026"')
            ->assertSeeHtml('data-testid="saran-salin-pejabat"')
            ->assertSee('Belum ada pejabat TA 2026. Salin dari TA 2025 lalu ubah yang berganti.')
            ->set('tahunPejabat', '2025')
            ->assertSeeHtml('aria-pressed="true" data-tahun-pejabat="2025"')
            ->assertSeeHtml('data-testid="pejabat-terkunci"')
            ->assertSee('Tahun Anggaran 2025 terkunci — pejabatnya hanya bisa dilihat.')
            ->assertSee('Drs. H. Mahmud Arifin, M.Pd.I.')
            ->assertSee('19680512 199403 1 002')
            ->assertDontSeeHtml('data-testid="saran-salin-pejabat"')
            ->assertSeeHtmlInOrder(['data-testid="pratinjau-ttd"', 'TA 2025', 'H. Sugeng Riyadi, S.E.', 'Drs. H. Mahmud Arifin, M.Pd.I.'])
            ->call('bukaUbahPejabat', 'kepala')
            ->assertSet('ubahPejabat', null);

        $perbandingan = collect($halaman->instance()->perbandinganTahun())->keyBy('kunci');
        $this->assertSame([2026, 2025], array_keys($perbandingan['kepala']['tahun']));
        $this->assertSame([null, 'Drs. H. Mahmud Arifin, M.Pd.I.'], array_column($perbandingan['kepala']['tahun'], 'nama'));
        $this->assertCount(10, $perbandingan, 'Kepala, komite, dan 8 leading.');

        $halaman->set('tahunPejabat', '2026')->assertSet('tahunPejabat', '')
            ->set('tahunPejabat', '1999')->assertSet('tahunPejabat', '');
        Livewire::withQueryParams(['tab' => 'pejabat', 'ta' => '2025'])->test(ProfilMadrasah::class)->assertSet('tahunPejabat', '2025');
    }

    public function test_salin_pejabat_dari_tahun_lalu_tanpa_menimpa_dan_tanda_berganti(): void
    {
        session(['stub_role' => 'admin']);
        app(PejabatRepository::class)->simpan(2026, 'leading', '1.1', ['jabatan' => 'Wakil Kepala Bidang Kurikulum', 'nama' => 'Ahmad Baru, S.Pd.', 'nip' => null]);

        $halaman = Livewire::withQueryParams(['tab' => 'pejabat'])->test(ProfilMadrasah::class)
            ->assertDontSeeHtml('data-testid="saran-salin-pejabat"')
            ->call('salinPejabat', 2025)
            ->assertDispatched('notify', message: '3 pejabat disalin dari TA 2025. Periksa lalu ubah yang berganti.', type: 'success');

        $ta2026 = app(PejabatRepository::class)->untukTahun(2026);
        $this->assertSame(['Drs. H. Mahmud Arifin, M.Pd.I.', '196805121994031002'], [$ta2026['kepala']['nama'], $ta2026['kepala']['nip']]);
        $this->assertSame('H. Sugeng Riyadi, S.E.', $ta2026['komite']['nama']);
        $this->assertSame('Ahmad Baru, S.Pd.', $ta2026['leading']['1.1']['nama'], 'Isian yang sudah ada tidak tertimpa.');
        $this->assertSame('Muslimin, M.Pd.', $ta2026['leading']['1.6']['nama']);
        $this->assertSame('salin_pejabat', app(RiwayatPengaturanRepository::class)->semua()[0]['aksi']);
        $this->assertSame('Drs. H. Mahmud Arifin, M.Pd.I.', StubData::pejabat()['kepala']['nama'], 'Dokumen tahun aktif ikut memakai pejabat salinan.');

        $perbandingan = collect($halaman->instance()->perbandinganTahun())->keyBy('kunci');
        $this->assertTrue($perbandingan['leading:1.1']['tahun'][2026]['berganti'], 'Siti Rohmah (2025) → Ahmad Baru (2026).');
        $this->assertFalse($perbandingan['kepala']['tahun'][2026]['berganti']);
        $halaman->assertSeeHtml('data-berganti');

        $halaman->call('salinPejabat', 2025)->assertDispatched('notify', message: 'Tidak ada yang disalin — pejabat TA 2025 kosong atau TA 2026 sudah terisi.', type: 'info');
        $halaman->set('tahunPejabat', '2025')->call('salinPejabat', 2026);
        $this->assertSame('Siti Rohmah, M.Pd.', app(PejabatRepository::class)->untukTahun(2025)['leading']['1.1']['nama'], 'Tahun terkunci tidak bisa diisi salinan.');
    }

    public function test_pengaturan_blok_mengetahui_per_sumber_dana(): void
    {
        session(['stub_role' => 'admin']);
        $kegiatan = ['nama_kegiatan' => 'Uji', 'leading' => '1.6', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]]];
        $peran = fn (string $sumber) => array_column(collect(SusunanKak::dari($kegiatan, $sumber)->bagian)->firstWhere('nomor', 9)['isi'][0]['kolom'], 'peran');

        $this->assertSame(['DIPA' => true, 'Komite' => true], app(PejabatRepository::class)->aturanMengetahui(2026), 'Bawaan PRD §12: DIPA maupun Komite.');
        $this->assertSame('Mengetahui,', $peran('DIPA')[0]);

        Livewire::withQueryParams(['tab' => 'pejabat'])->test(ProfilMadrasah::class)
            ->assertSet('mengetahui', ['DIPA' => true, 'Komite' => true])
            ->assertSeeHtml('data-testid="aturan-mengetahui"')
            ->set('mengetahui.DIPA', false)
            ->assertDispatched('notify', message: 'Blok "Mengetahui" tampil di dokumen Komite.', type: 'success')
            ->assertSet('mengetahui', ['DIPA' => false, 'Komite' => true])
            ->set('mengetahui.Komite', false)
            ->assertDispatched('notify', message: 'Blok "Mengetahui" tidak ditampilkan di dokumen mana pun.', type: 'success');

        $this->assertSame(['DIPA' => false, 'Komite' => false], app(PejabatRepository::class)->aturanMengetahui(2026));
        $this->assertSame(['Pembuat,', 'Menyetujui,'], $peran('DIPA'));
        $this->assertSame(['Pembuat,', 'Menyetujui,'], $peran('Komite'));
        $this->assertSame('TA 2026 — Mengetahui di dokumen Komite: ya → tidak', app(RiwayatPengaturanRepository::class)->semua()[0]['keterangan']);
        $this->assertSame(['DIPA' => true, 'Komite' => true], app(PejabatRepository::class)->aturanMengetahui(2025), 'Per tahun anggaran.');

        Livewire::withQueryParams(['tab' => 'pejabat', 'ta' => '2025'])->test(ProfilMadrasah::class)
            ->assertSet('mengetahui', ['DIPA' => true, 'Komite' => true])
            ->assertSeeHtml('data-testid="aturan-mengetahui" disabled')
            ->set('mengetahui.DIPA', false)
            ->assertSet('mengetahui', ['DIPA' => true, 'Komite' => true]);
        $this->assertTrue(app(PejabatRepository::class)->aturanMengetahui(2025)['DIPA'], 'Tahun terkunci tidak berubah.');
    }
}
