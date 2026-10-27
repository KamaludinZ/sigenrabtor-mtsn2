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
            $this->assertSame($k['isi'], TeksBaku::isiNilai($k['teks'], $k['tahun'], $k['nilai']), $k['teks']);
            $this->assertSame($k['asing'], TeksBaku::penandaAsing($k['teks'], TeksBaku::bolehPenutup()), $k['teks']);
        }
        $this->assertSame('Kegiatan Rapat Koordinasi TA 2026', TeksBaku::isi('Kegiatan {nama_kegiatan} TA {tahun}', 2026, '  kegiatan   Rapat Koordinasi '));
    }

    public function test_aturan_paragraf_dan_kalimat_penutup(): void
    {
        $this->assertSame('Satu paragraf saja.', LatarBelakangBaku::rapikan("  Satu \n\n paragraf   saja. "));

        $this->assertSame('Paragraf latar belakang baku wajib diisi.', LatarBelakangBaku::galatParagraf(''));
        $this->assertSame('Terlalu panjang (maksimal 3000 karakter).', LatarBelakangBaku::galatParagraf(str_repeat('a', 3001)));
        $this->assertSame('Penanda {nama_kegiatan} tidak dikenal — di paragraf baku hanya {tahun} yang bisa dipakai; variabel kegiatan disisipkan di kalimat penutup.', LatarBelakangBaku::galatParagraf('Visi {tahun} untuk {nama_kegiatan}'));
        $this->assertNull(LatarBelakangBaku::galatParagraf('Visi madrasah Tahun Anggaran {tahun}.'));

        $this->assertSame('Kalimat penutup wajib diisi.', LatarBelakangBaku::galatPenutup(''));
        $this->assertSame('Terlalu panjang (maksimal 500 karakter).', LatarBelakangBaku::galatPenutup(str_repeat('a', 501).'{nama_kegiatan}'));
        $this->assertSame('Penanda {nama} tidak dikenal — pakai tombol variabel kegiatan atau {tahun}.', LatarBelakangBaku::galatPenutup('Kegiatan {nama}.'));
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

    public function test_variabel_kegiatan_nilai_kosong_dan_potongan(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        $nilai = TeksBaku::nilaiKegiatan($kegiatan);
        $this->assertSame(array_keys(TeksBaku::VARIABEL_KEGIATAN), array_keys($nilai));
        $this->assertSame(KalimatKak::tanpaAwalanKegiatan($kegiatan['nama_kegiatan']), $nilai['{nama_kegiatan}']);
        $this->assertSame('Tim Penjamin Mutu', $nilai['{leading}']);
        $this->assertNotNull($nilai['{uraian_rktm}']);
        $this->assertNotNull($nilai['{uraian_sk}']);
        $this->assertNotNull($nilai['{uraian_iksk}']);
        $this->assertSame([null, null, null, null, null], array_values(TeksBaku::nilaiKegiatan([])));

        $teks = 'Kegiatan {nama_kegiatan} oleh {leading} untuk {uraian_iksk} dan {uraian_iksk} TA {tahun}.';
        $this->assertSame(['Unit leading', 'Indikator (IKSK)'], TeksBaku::kosong($teks, ['{nama_kegiatan}' => 'Rapat']));
        $this->assertSame([], TeksBaku::kosong($teks, $nilai));
        $this->assertSame([
            ['teks' => 'Kegiatan ', 'penanda' => null],
            ['teks' => '{nama_kegiatan}', 'penanda' => '{nama_kegiatan}'],
            ['teks' => ' oleh ', 'penanda' => null],
            ['teks' => '{leading}', 'penanda' => '{leading}'],
            ['teks' => ' TA {tahun}.', 'penanda' => null],
        ], TeksBaku::potong('Kegiatan {nama_kegiatan} oleh {leading} TA {tahun}.'));
        $this->assertNull(LatarBelakangBaku::galatPenutup($teks));
    }

    public function test_pemilih_variabel_kegiatan_dan_dokumen_mengikuti(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        $nilai = TeksBaku::nilaiKegiatan($kegiatan);

        $komponen = Livewire::test(DasarHukum::class)
            ->call('ubahLatar')
            ->assertSeeHtml('data-testid="pilih-variabel-kegiatan"')
            ->assertSeeHtmlInOrder(['data-variabel="{nama_kegiatan}"', 'data-variabel="{leading}"', 'data-variabel="{uraian_rktm}"', 'data-variabel="{uraian_sk}"', 'data-variabel="{uraian_iksk}"', 'Tahun anggaran'])
            ->assertSeeHtml('title="Contoh: Tim Penjamin Mutu"');

        $komponen->set('isianLatar.kalimat_penutup', 'Kegiatan {nama_kegiatan} dilaksanakan {leading} untuk mendukung indikator {uraian_iksk}.')
            ->call('simpanLatar')
            ->assertHasNoErrors()
            ->assertSeeHtmlInOrder(['data-testid="kalimat-sisip"', 'Kegiatan ', 'data-variabel="{nama_kegiatan}">nama kegiatan</mark>', ' dilaksanakan ', 'data-variabel="{leading}">unit leading</mark>', ' untuk mendukung indikator ', 'data-variabel="{uraian_iksk}">indikator (iksk)</mark>', '.']);

        $nama = $nilai['{nama_kegiatan}'];
        $this->assertSame("Kegiatan {$nama} dilaksanakan Tim Penjamin Mutu untuk mendukung indikator {$nilai['{uraian_iksk}']}.", KalimatKak::latarBelakangKegiatan($kegiatan, 2026));
        $kak = SusunanKak::dari($kegiatan);
        $this->assertSame("Kegiatan {$nama} dilaksanakan Tim Penjamin Mutu untuk mendukung indikator {$nilai['{uraian_iksk}']}.", collect(collect($kak->bagian)->firstWhere('nomor', 1)['isi'])->last()['teks']);
        $this->assertNotContains('indikator (iksk) (kalimat penutup latar belakang)', $kak->kurang);

        // Kegiatan yang belum memilih IKSK/leading: "…" di kalimat & tercatat sebagai kekurangan dokumen.
        $tanpaKode = ['nama_kegiatan' => 'Rapat Evaluasi', 'leading' => null, 'kode_iksk' => null];
        $this->assertSame('Kegiatan Rapat Evaluasi dilaksanakan … untuk mendukung indikator ….', KalimatKak::latarBelakangKegiatan($tanpaKode, 2026));
        $kurang = SusunanKak::dari($tanpaKode)->kurang;
        $this->assertContains('unit leading (kalimat penutup latar belakang)', $kurang);
        $this->assertContains('indikator (iksk) (kalimat penutup latar belakang)', $kurang);
    }

    public function test_contoh_pratinjau_memakai_kegiatan_dengan_variabel_terlengkap(): void
    {
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Kegiatan Tanpa Kode Apa Pun']);
        $this->assertNotNull(app(KegiatanRepository::class)->temukan($id));

        $contoh = Livewire::test(DasarHukum::class)->instance()->contohKegiatan();
        $this->assertNotSame('Kegiatan Tanpa Kode Apa Pun', $contoh['nama_kegiatan']);
        $this->assertCount(5, array_filter(TeksBaku::nilaiKegiatan($contoh)));
    }
}
