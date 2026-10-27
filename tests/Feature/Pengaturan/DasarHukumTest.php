<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\DasarHukumRepository;
use App\Contracts\KegiatanRepository;
use App\Livewire\Pengaturan\DasarHukum;
use App\Livewire\Pengaturan\ProfilMadrasah;
use App\Support\Dokumen\NilaiVariabel;
use App\Support\Dokumen\SusunanKak;
use App\Support\Kegiatan\KalimatKak;
use App\Support\Pengaturan\KesiapanPengaturan;
use App\Support\Stub\StubDasarHukumRepository;
use App\Support\Stub\StubData;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function test_data_tiruan_per_tahun_teks_mentah_dengan_penanda(): void
    {
        $repo = app(DasarHukumRepository::class);

        $ta2026 = $repo->untukTahun(2026);
        $this->assertTrue($ta2026['diatur']);
        $this->assertSame(['dh-2026-1', 'dh-2026-2', 'dh-2026-3', 'dh-2026-4'], array_column($ta2026['dasar_hukum'], 'id'));
        $this->assertStringEndsWith('MTsN 2 Kota Malang Tahun {tahun}', $ta2026['dasar_hukum'][3]['teks']);
        $this->assertStringContainsString('Tahun Anggaran {tahun} memerlukan', $ta2026['latar_belakang']);
        $this->assertStringEndsWith('adalah kegiatan {nama_kegiatan}.', $ta2026['kalimat_penutup']);
        $this->assertSame(['dh-2025-1', 'dh-2025-2', 'dh-2025-3'], array_column($repo->untukTahun(2025)['dasar_hukum'], 'id'));

        $belum = $repo->untukTahun(2027);
        $this->assertFalse($belum['diatur']);
        $this->assertSame([], $belum['dasar_hukum']);
        $this->assertSame(StubDasarHukumRepository::bawaan()['latar_belakang'], $belum['latar_belakang']);
        $this->assertNull($belum['diperbarui_pada']);

        // Dokumen menerima teks siap cetak: penanda terisi tahunnya sendiri.
        $this->assertSame('Surat Keputusan Kepala MTsN 2 Kota Malang tentang Rencana Kerja Tahunan Madrasah (RKTM) MTsN 2 Kota Malang Tahun 2025.', StubData::dasarHukum(2025)[2]);
        $this->assertStringContainsString('pada Tahun Anggaran 2027 memerlukan', StubData::latarBelakangBaku(2027));
        $this->assertSame([], StubData::dasarHukum(2027));
        $this->assertSame(
            'Salah satu kegiatan yang relevan dan mendukung program Perjanjian Kinerja (Perkin) Kepala Madrasah adalah kegiatan Workshop Kurikulum.',
            KalimatKak::latarBelakangKegiatan('Kegiatan Workshop Kurikulum', 2026),
        );
    }

    public function test_simpan_mengganti_pengaturan_satu_tahun_dan_dipakai_dokumen(): void
    {
        $repo = app(DasarHukumRepository::class);
        $repo->simpan(2026, [
            'dasar_hukum' => [['id' => 'dh-2026-4', 'teks' => 'Keputusan RKTM Tahun {tahun}.'], ['id' => null, 'teks' => 'Peraturan baru;']],
            'latar_belakang' => 'Paragraf baku TA {tahun}.',
            'kalimat_penutup' => 'Kegiatan {nama_kegiatan} mendukung Perkin TA {tahun}.',
        ]);

        $ta2026 = $repo->untukTahun(2026);
        $this->assertSame('dh-2026-4', $ta2026['dasar_hukum'][0]['id']);
        $this->assertTrue(Str::isUuid($ta2026['dasar_hukum'][1]['id']));
        $this->assertSame('Paragraf baku TA {tahun}.', $ta2026['latar_belakang']);
        $this->assertNotNull($ta2026['diperbarui_pada']);
        $this->assertTrue(Storage::disk('local')->exists(config('sigenrabtor.stub.dasar_hukum_file')));
        $this->assertCount(3, $repo->untukTahun(2025)['dasar_hukum']);

        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        $nama = KalimatKak::tanpaAwalanKegiatan($kegiatan['nama_kegiatan']);
        $nilai = NilaiVariabel::untuk($kegiatan, null);
        $this->assertSame(['Keputusan RKTM Tahun 2026;', 'Peraturan baru.'], $nilai['dasar_hukum']);
        $this->assertStringStartsWith('Paragraf baku TA 2026. ', $nilai['latar_belakang']);
        $this->assertStringEndsWith("Kegiatan {$nama} mendukung Perkin TA 2026.", $nilai['latar_belakang']);

        $bagian = collect(SusunanKak::dari($kegiatan)->bagian)->keyBy('nomor');
        $this->assertSame('Paragraf baku TA 2026.', $bagian[1]['isi'][0]['teks']);
        $this->assertSame("Kegiatan {$nama} mendukung Perkin TA 2026.", collect($bagian[1]['isi'])->last()['teks']);
        $this->assertSame(['Keputusan RKTM Tahun 2026;', 'Peraturan baru.'], $bagian[2]['isi'][0]['butir']);

        session(['stub_role' => 'admin']);
        Livewire::test(DasarHukum::class)
            ->assertSee('2 butir · tercetak bernomor')
            ->assertSeeHtmlInOrder(['data-id="dh-2026-4"', 'Keputusan RKTM Tahun 2026;', 'Peraturan baru.'])
            ->assertSeeHtmlInOrder(['data-testid="kalimat-sisip"', 'Kegiatan ', 'nama kegiatan</mark>', ' mendukung Perkin TA 2026.']);
    }

    public function test_tahun_belum_diatur_kosong_dan_belum_siap(): void
    {
        Storage::disk('local')->put(config('sigenrabtor.stub.dasar_hukum_file'), '{}');
        session(['stub_role' => 'admin']);

        Livewire::test(DasarHukum::class)
            ->assertSee('0 butir · tercetak bernomor')
            ->assertSeeHtml('data-testid="dasar-hukum-kosong"')
            ->assertSee('Tahun ini belum diatur — paragraf latar belakang memakai teks bawaan.')
            ->assertSee('pada Tahun Anggaran 2026 memerlukan')
            ->assertSeeHtml('data-siap="tidak"');
        $this->assertFalse(collect(KesiapanPengaturan::tahunAktif()->butir)->firstWhere('kunci', 'dasar_hukum')['siap']);
    }

    public function test_kalimat_penutup_tanpa_penanda_tidak_menandai_sisipan(): void
    {
        app(DasarHukumRepository::class)->simpan(2026, ['dasar_hukum' => [['teks' => 'Butir.']], 'latar_belakang' => 'Paragraf.', 'kalimat_penutup' => 'Kalimat tanpa nama.']);
        session(['stub_role' => 'admin']);

        Livewire::test(DasarHukum::class)
            ->assertSet('pengaturan.kalimat_penutup', 'Kalimat tanpa nama.')
            ->assertSeeHtml('Kalimat tanpa nama.')
            ->assertDontSeeHtml('nama kegiatan</mark>');
    }
}
