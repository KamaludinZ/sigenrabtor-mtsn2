<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\DasarHukumRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Livewire\Pengaturan\DasarHukum;
use App\Support\Dokumen\SusunanKak;
use App\Support\Kegiatan\KalimatKak;
use App\Support\Pengaturan\LatarBelakangBaku;
use App\Support\Pengaturan\TeksBaku;
use App\Support\Stub\StubDasarHukumRepository;
use App\Support\Stub\StubData;
use Livewire\Livewire;
use Tests\TestCase;

class LatarBelakangBakuTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['stub_role' => 'admin']);
    }

    public function test_kasus_bersama_teks_baku(): void
    {
        $kasus = json_decode(file_get_contents(base_path('tests/fixtures/teks-baku.json')), true);

        foreach ($kasus as $k) {
            $this->assertSame($k['isi'], TeksBaku::isi($k['teks'], $k['tahun'], $k['nama']), $k['teks']);
            $this->assertSame($k['asing'], TeksBaku::penandaAsing($k['teks'], [TeksBaku::TAHUN, TeksBaku::NAMA_KEGIATAN]), $k['teks']);
        }
    }

    public function test_aturan_paragraf_dan_kalimat_penutup(): void
    {
        $this->assertSame('Satu paragraf saja.', LatarBelakangBaku::rapikan("  Satu \n\n paragraf   saja. "));

        $this->assertSame('Paragraf latar belakang baku wajib diisi.', LatarBelakangBaku::galatParagraf(''));
        $this->assertSame('Terlalu panjang (maksimal 3000 karakter).', LatarBelakangBaku::galatParagraf(str_repeat('a', 3001)));
        $this->assertSame('Penanda {nama_kegiatan} tidak dikenal — di paragraf baku hanya {tahun} yang bisa dipakai; nama kegiatan ada di kalimat penutup.', LatarBelakangBaku::galatParagraf('Visi {tahun} untuk {nama_kegiatan}'));
        $this->assertNull(LatarBelakangBaku::galatParagraf('Visi madrasah Tahun Anggaran {tahun}.'));

        $this->assertSame('Kalimat penutup wajib diisi.', LatarBelakangBaku::galatPenutup(''));
        $this->assertSame('Terlalu panjang (maksimal 500 karakter).', LatarBelakangBaku::galatPenutup(str_repeat('a', 501).'{nama_kegiatan}'));
        $this->assertSame('Penanda {nama} tidak dikenal — gunakan {nama_kegiatan} atau {tahun}.', LatarBelakangBaku::galatPenutup('Kegiatan {nama}.'));
        $this->assertSame('Sisipkan {nama_kegiatan} di tempat nama kegiatan tercetak.', LatarBelakangBaku::galatPenutup('Kegiatan ini mendukung Perkin.'));
        $this->assertSame('{nama_kegiatan} hanya boleh dipakai sekali.', LatarBelakangBaku::galatPenutup('{nama_kegiatan} dan {nama_kegiatan}.'));
        $this->assertNull(LatarBelakangBaku::galatPenutup('Kegiatan {nama_kegiatan} mendukung Perkin TA {tahun}.'));
    }

    public function test_ubah_simpan_dan_dipakai_dokumen(): void
    {
        $komponen = Livewire::test(DasarHukum::class)
            ->assertSeeHtml('wire:click="ubahLatar"')
            ->call('ubahLatar')
            ->assertSet('suntingLatar', true)
            ->assertSet('isianLatar.kalimat_penutup', StubDasarHukumRepository::bawaan()['kalimat_penutup'])
            ->assertSeeHtml('data-testid="editor-latar"')
            ->assertSeeHtml('x-data="editorTeksBaku(')
            ->assertDontSeeHtml('wire:click="ubahLatar"')
            ->assertSeeHtml('data-testid="pratinjau-latar"');
        $this->assertStringContainsString('{tahun}', $komponen->get('isianLatar.latar_belakang'), 'Editor memuat teks mentah berpenanda.');

        $komponen->set('isianLatar.latar_belakang', '  ')
            ->set('isianLatar.kalimat_penutup', 'Tanpa penanda.')
            ->call('simpanLatar')
            ->assertHasErrors(['isianLatar.latar_belakang', 'isianLatar.kalimat_penutup'])
            ->assertSee('Paragraf latar belakang baku wajib diisi.')
            ->assertSee('Sisipkan {nama_kegiatan} di tempat nama kegiatan tercetak.')
            ->assertSet('suntingLatar', true);
        $this->assertSame(StubDasarHukumRepository::bawaan()['kalimat_penutup'], app(DasarHukumRepository::class)->untukTahun(2026)['kalimat_penutup']);

        $komponen->set('isianLatar.latar_belakang', "MTsN 2 Kota Malang berkomitmen\nmeningkatkan mutu pada Tahun Anggaran {tahun}.")
            ->set('isianLatar.kalimat_penutup', 'Kegiatan {nama_kegiatan} mendukung Perkin Kepala Madrasah.')
            ->call('simpanLatar')
            ->assertHasNoErrors()
            ->assertSet('suntingLatar', false)
            ->assertDispatched('notify', message: 'Latar belakang baku TA 2026 tersimpan.', type: 'success')
            ->assertSeeHtmlInOrder(['data-testid="paragraf-baku"', 'MTsN 2 Kota Malang berkomitmen meningkatkan mutu pada Tahun Anggaran 2026.'])
            ->assertSeeHtmlInOrder(['data-testid="kalimat-sisip"', 'Kegiatan ', 'nama kegiatan</mark>', ' mendukung Perkin Kepala Madrasah.']);

        $tersimpan = app(DasarHukumRepository::class)->untukTahun(2026);
        $this->assertSame('MTsN 2 Kota Malang berkomitmen meningkatkan mutu pada Tahun Anggaran {tahun}.', $tersimpan['latar_belakang']);
        $this->assertCount(4, $tersimpan['dasar_hukum'], 'Dasar hukum tidak ikut berubah.');
        $riwayat = app(RiwayatPengaturanRepository::class)->semua()[0];
        $this->assertSame('ubah_latar_belakang', $riwayat['aksi']);
        $this->assertSame('Latar belakang TA 2026 — ubah paragraf baku & kalimat penutup: Kegiatan {nama_kegiatan} mendukung Perkin Kepala Madrasah.', $riwayat['keterangan']);

        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        $nama = KalimatKak::tanpaAwalanKegiatan($kegiatan['nama_kegiatan']);
        $latar = collect(SusunanKak::dari($kegiatan)->bagian)->firstWhere('nomor', 1)['isi'];
        $this->assertSame('MTsN 2 Kota Malang berkomitmen meningkatkan mutu pada Tahun Anggaran 2026.', $latar[0]['teks']);
        $this->assertSame("Kegiatan {$nama} mendukung Perkin Kepala Madrasah.", collect($latar)->last()['teks']);
        $this->assertSame('MTsN 2 Kota Malang berkomitmen meningkatkan mutu pada Tahun Anggaran 2026.', StubData::latarBelakangBaku(2026));

        // Tanpa perubahan: tidak disimpan & tidak dicatat.
        $jumlahRiwayat = count(app(RiwayatPengaturanRepository::class)->semua());
        $komponen->call('ubahLatar')->call('simpanLatar')
            ->assertDispatched('notify', message: 'Tidak ada yang berubah.', type: 'info')
            ->assertSet('suntingLatar', false);
        $this->assertCount($jumlahRiwayat, app(RiwayatPengaturanRepository::class)->semua());
    }

    public function test_pakai_teks_bawaan_dan_batal(): void
    {
        app(DasarHukumRepository::class)->simpan(2026, ['dasar_hukum' => [], 'latar_belakang' => 'Paragraf lama {tahun}.', 'kalimat_penutup' => 'Lama {nama_kegiatan}.']);

        Livewire::test(DasarHukum::class)
            ->call('pakaiBawaanLatar')
            ->assertSet('isianLatar.latar_belakang', '')
            ->call('ubahLatar')
            ->assertSet('isianLatar.latar_belakang', 'Paragraf lama {tahun}.')
            ->call('pakaiBawaanLatar')
            ->assertSet('isianLatar', StubDasarHukumRepository::bawaan())
            ->call('batalLatar')
            ->assertSet('suntingLatar', false)
            ->assertSet('isianLatar.latar_belakang', '')
            ->assertSee('Paragraf lama 2026.')
            ->call('ubahLatar')
            ->call('pakaiBawaanLatar')
            ->call('simpanLatar')
            ->assertHasNoErrors();
        $this->assertSame(StubDasarHukumRepository::bawaan()['latar_belakang'], app(DasarHukumRepository::class)->untukTahun(2026)['latar_belakang']);
    }

    public function test_tahun_terkunci_tanpa_editor_dan_ganti_tahun_menutup_editor(): void
    {
        Livewire::test(DasarHukum::class)
            ->call('ubahLatar')
            ->set('isianLatar.kalimat_penutup', 'Belum disimpan {nama_kegiatan}.')
            ->set('tahun', '2025')
            ->assertSet('suntingLatar', false)
            ->assertDontSeeHtml('wire:click="ubahLatar"')
            ->call('ubahLatar')
            ->assertSet('suntingLatar', false)
            ->set('suntingLatar', true)
            ->set('isianLatar', ['latar_belakang' => 'Paksa {tahun}.', 'kalimat_penutup' => 'Paksa {nama_kegiatan}.'])
            ->call('simpanLatar');
        $this->assertSame(StubDasarHukumRepository::bawaan()['latar_belakang'], app(DasarHukumRepository::class)->untukTahun(2025)['latar_belakang'], 'Tahun terkunci tidak berubah.');
        $this->assertSame(StubDasarHukumRepository::bawaan()['kalimat_penutup'], app(DasarHukumRepository::class)->untukTahun(2026)['kalimat_penutup'], 'Isian yang belum disimpan dibuang.');
    }
}
