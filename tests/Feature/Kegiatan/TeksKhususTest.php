<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Livewire\Kegiatan\SusunDokumen;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanRab;
use App\Support\Dokumen\TahunDokumen;
use App\Support\Kegiatan\KalimatKak;
use App\Support\Kegiatan\TeksKhusus;
use App\Support\Kegiatan\UbahDataKegiatan;
use App\Support\Stub\StubData;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TeksKhususTest extends TestCase
{
    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private function kegiatanBaru(array $data = []): int
    {
        return app(KegiatanRepository::class)->simpan($data + ['nama_kegiatan' => 'Kegiatan Rapat Kerja Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026]);
    }

    public function test_tanpa_penyesuaian_teks_baku_sama_dengan_kalimat_kak(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan($this->kegiatanBaru());
        $nama = $kegiatan['nama_kegiatan'];

        $this->assertSame(['persiapan', 'analisis_hasil'], TeksKhusus::untukBagian(4));
        $this->assertSame(['pengarah'], TeksKhusus::untukBagian(6));
        $this->assertSame([], TeksKhusus::untukBagian(1));
        $this->assertSame(KalimatKak::persiapan($nama), TeksKhusus::cetak('persiapan', $kegiatan));
        $this->assertSame(KalimatKak::analisisHasil($nama), TeksKhusus::cetak('analisis_hasil', $kegiatan));
        $this->assertSame(KalimatKak::pengarah($nama, StubData::madrasah()['nama']), TeksKhusus::cetak('pengarah', $kegiatan));
        $this->assertStringContainsString(TeksKhusus::PENANDA, TeksKhusus::bakuMentah('pengarah'));
        $this->assertSame([], TeksKhusus::dari($kegiatan));

        $teks = json_encode(SusunanKak::dari($kegiatan)->bagian, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString(KalimatKak::pengarah($nama, StubData::madrasah()['nama']), $teks);
        $this->assertStringNotContainsString(TeksKhusus::PENANDA, $teks);
    }

    public function test_rapikan_dan_galat(): void
    {
        $baku = TeksKhusus::bakuMentah('analisis_hasil');
        $this->assertSame(
            ['persiapan' => ['Rapat persiapan panitia.', 'Menyiapkan ruang.'], 'asing' => 'x'],
            TeksKhusus::rapikan(['persiapan' => "  Rapat   persiapan panitia. \n\n Menyiapkan ruang.\r\n", 'analisis_hasil' => " {$baku} ", 'pengarah' => '  ', 'asing' => 'x']),
        );
        $this->assertSame(['persiapan' => ['Satu butir.']], TeksKhusus::rapikan(['persiapan' => ['  Satu butir. ', '']]));
        $this->assertSame([], TeksKhusus::rapikan(['persiapan' => implode("\n", TeksKhusus::bakuMentah('persiapan'))]));

        $this->assertSame([], TeksKhusus::galat([]));
        $this->assertSame([], TeksKhusus::galat(['pengarah' => 'Pengarah kegiatan {nama_kegiatan} adalah Wakil Kepala Bidang Kurikulum.']));
        $galat = TeksKhusus::galat([
            'asing' => 'x',
            'persiapan' => array_fill(0, TeksKhusus::MAKS_BUTIR + 1, 'Butir persiapan.'),
            'analisis_hasil' => 'Pendek',
            'pengarah' => 'Pengarah kegiatan {nama_kegiatan} adalah {kepala}.',
        ]);
        $this->assertSame('Teks asing tidak bisa disesuaikan.', $galat['asing']);
        $this->assertStringContainsString('paling banyak '.TeksKhusus::MAKS_BUTIR.' butir', $galat['persiapan']);
        $this->assertStringContainsString('10–1000 karakter', $galat['analisis_hasil']);
        $this->assertStringContainsString('penanda yang boleh hanya {nama_kegiatan}', $galat['pengarah']);
        $this->assertArrayHasKey('persiapan', TeksKhusus::galat(['persiapan' => []]));
        $this->assertArrayHasKey('pengarah', TeksKhusus::galat(['pengarah' => ['bukan teks']]));
    }

    public function test_tersimpan_lewat_ubah_data_kegiatan_dan_tercetak_di_kak(): void
    {
        $repo = app(KegiatanRepository::class);
        $id = $this->kegiatanBaru();

        $hasil = UbahDataKegiatan::ubah($id, ['teks_khusus' => [
            'pengarah' => 'Pengarah kegiatan {nama_kegiatan} adalah Kepala Madrasah dan Wakil Kepala Bidang Kurikulum.',
            'persiapan' => "Rapat koordinasi panitia {nama_kegiatan} tahun 2025.\nMenyiapkan undangan peserta.",
        ]], self::LEADING);
        $this->assertSame(['teks_khusus'], $hasil['diubah']);
        $this->assertSame(['Teks KAK/TOR yang disesuaikan'], $hasil['bagian']);

        $kegiatan = $repo->temukan($id);
        $this->assertSame(['Rapat koordinasi panitia {nama_kegiatan} tahun 2025.', 'Menyiapkan undangan peserta.'], $kegiatan['teks_khusus']['persiapan']);
        $this->assertTrue(TeksKhusus::disesuaikan('pengarah', $kegiatan));
        $this->assertFalse(TeksKhusus::disesuaikan('analisis_hasil', $kegiatan));

        $kak = SusunanKak::dari($kegiatan);
        $teks = json_encode($kak->bagian, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Pengarah kegiatan Rapat Kerja Madrasah adalah Kepala Madrasah dan Wakil Kepala Bidang Kurikulum.', $teks);
        $this->assertStringContainsString('Rapat koordinasi panitia Rapat Kerja Madrasah tahun 2025.', $teks);
        $this->assertStringContainsString(KalimatKak::analisisHasil($kegiatan['nama_kegiatan']), $teks);

        // Tahun yang terketik di teks khusus ikut diperiksa sinkronisasi tahun dokumen.
        $tempat = TahunDokumen::dari($kegiatan, $kak, SusunanRab::dari($kegiatan), ['teks' => [], 'nilai' => []], 2026);
        $this->assertContains('KAK · Tahapan persiapan (4a) (2025)', TahunDokumen::beda($tempat, 2026));

        // Sama dengan teks baku = penyesuaian dibuang.
        UbahDataKegiatan::ubah($id, ['teks_khusus' => ['pengarah' => TeksKhusus::bakuMentah('pengarah')]], self::LEADING);
        $this->assertSame([], TeksKhusus::dari($repo->temukan($id)));

        try {
            UbahDataKegiatan::ubah($id, ['teks_khusus' => ['pengarah' => 'Pendek']], self::LEADING);
            $this->fail('Teks terlalu pendek harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('teks_khusus', $e->errors());
        }
    }

    public function test_sesuaikan_teks_di_tab_susun(): void
    {
        $repo = app(KegiatanRepository::class);
        $id = $this->kegiatanBaru();

        $halaman = Livewire::test(SusunDokumen::class, ['kegiatan' => $id])
            ->assertSeeHtml('data-testid="teks-khusus-4"')
            ->assertSeeHtml('data-testid="teks-khusus-6"')
            ->assertDontSeeHtml('data-testid="teks-khusus-5"')
            ->assertSeeHtml('data-teks="pengarah" data-disesuaikan="tidak"')
            ->assertDontSeeHtml('data-testid="bagian-disesuaikan"')
            ->call('bukaTeks', 'persiapan')
            ->assertSet('teksTerbuka', 'persiapan')
            ->assertSet('teksIsian', implode("\n", TeksKhusus::bakuMentah('persiapan')))
            ->assertSeeHtml('data-testid="penyunting-teks-persiapan"');

        // Membuka isian bagian lain menutup penyunting teks, dan sebaliknya.
        $halaman->call('bukaIsian', 5)->assertSet('teksTerbuka', null)
            ->call('bukaTeks', 'pengarah')->assertSet('isianTerbuka', null)
            ->set('teksIsian', 'Pengarah kegiatan {nama_kegiatan} adalah {kepala}.')
            ->call('simpanTeks')
            ->assertHasErrors('teksIsian')
            ->assertSet('teksTerbuka', 'pengarah')
            ->set('teksIsian', 'Pengarah kegiatan {nama_kegiatan} adalah Kepala MTsN 2 Kota Malang bersama Komite Madrasah.')
            ->call('simpanTeks')
            ->assertHasNoErrors()
            ->assertSet('teksTerbuka', null)
            ->assertDispatched('notify', message: 'Kalimat pengarah (6b) disesuaikan untuk kegiatan ini.', type: 'success')
            ->assertSeeHtml('data-teks="pengarah" data-disesuaikan="ya"')
            ->assertSeeHtml('data-testid="bagian-disesuaikan"')
            ->assertSee('Pengarah kegiatan Rapat Kerja Madrasah adalah Kepala MTsN 2 Kota Malang bersama Komite Madrasah.');
        $this->assertTrue(TeksKhusus::disesuaikan('pengarah', $repo->temukan($id)));

        // Teks yang sama tidak menulis ulang.
        $halaman->call('bukaTeks', 'pengarah')->call('simpanTeks')
            ->assertDispatched('notify', message: 'Kalimat pengarah (6b) tidak berubah.', type: 'info');

        // Berubah di tempat lain sejak dibuka: tidak menimpa, penyunting tetap terbuka.
        $halaman->call('bukaTeks', 'analisis_hasil');
        $repo->simpan(['tempat' => 'Aula Utama'] + $repo->temukan($id), $id);
        $halaman->set('teksIsian', 'Menganalisis capaian kegiatan {nama_kegiatan} bersama tim penjamin mutu.')->call('simpanTeks')
            ->assertSet('teksTerbuka', 'analisis_hasil')
            ->assertDispatched('notify', type: 'error');
        $this->assertFalse(TeksKhusus::disesuaikan('analisis_hasil', $repo->temukan($id)));
        $halaman->call('simpanTeks')->assertSet('teksTerbuka', null);
        $this->assertTrue(TeksKhusus::disesuaikan('analisis_hasil', $repo->temukan($id)));

        // Kembalikan ke teks baku: penyesuaian lain tetap.
        $halaman->call('kembalikanTeks', 'pengarah')
            ->assertDispatched('notify', message: 'Kalimat pengarah (6b) kembali memakai teks baku.', type: 'success')
            ->assertSeeHtml('data-teks="pengarah" data-disesuaikan="tidak"');
        $this->assertSame(['analisis_hasil'], array_keys(TeksKhusus::dari($repo->temukan($id))));

        $halaman->call('bukaTeks', 'tujuan')->assertNotFound();
    }

    public function test_kegiatan_terkunci_hanya_melihat_teks(): void
    {
        $id = $this->kegiatanBaru(['status' => StatusKegiatan::Diperiksa->value, 'teks_khusus' => ['pengarah' => 'Pengarah kegiatan {nama_kegiatan} adalah Kepala Madrasah.']]);

        $halaman = Livewire::test(SusunDokumen::class, ['kegiatan' => $id])
            ->assertSeeHtml('data-teks="pengarah" data-disesuaikan="ya"')
            ->assertDontSeeHtml('data-testid="sesuaikan-teks-pengarah"')
            ->assertDontSeeHtml('data-testid="kembalikan-teks-pengarah"');
        $halaman->call('kembalikanTeks', 'pengarah')->assertForbidden();
    }
}
