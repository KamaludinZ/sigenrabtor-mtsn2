<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\TemplateDokumenRepository;
use App\Livewire\Pengaturan\TemplateDokumen;
use App\Support\Dokumen\AnalisisTemplate;
use App\Support\Dokumen\PembacaTemplateDocx;
use App\Support\Dokumen\VariabelTemplate;
use App\Support\Pengaturan\KesiapanPengaturan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\Support\Docx;
use Tests\TestCase;

class MasterKakTest extends TestCase
{
    private function berkas(string $nama, string $isi): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, $isi);
    }

    private function masterBenar(): string
    {
        return Docx::isi([
            ['KERANGKA ACUAN KERJA ', '${nama_kegiatan_cover}'],
            ['Tahun Anggaran ${tahun', '_anggaran}'], // terpecah antar-run seperti di Word
            'Biaya ${total} (${terbilang}), dibebankan pada dana ${sumber_dana}; total ${total}.',
            '${rab}',
        ], header: ['${kop}']);
    }

    public function test_pembaca_menyambung_run_dan_membaca_header(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kak');
        file_put_contents($path, $this->masterBenar());

        $this->assertSame(
            ['nama_kegiatan_cover' => 1, 'tahun_anggaran' => 1, 'total' => 2, 'terbilang' => 1, 'sumber_dana' => 1, 'rab' => 1, 'kop' => 1],
            PembacaTemplateDocx::variabel($path),
        );

        file_put_contents($path, 'bukan zip');
        try {
            PembacaTemplateDocx::variabel($path);
            $this->fail('Berkas tidak sah harus ditolak.');
        } catch (RuntimeException $e) {
            $this->assertSame('Berkas bukan dokumen Word (.docx) yang sah.', $e->getMessage());
        } finally {
            unlink($path);
        }
    }

    public function test_analisis_variabel_dan_saran_salah_tulis(): void
    {
        $this->assertSame('nama_kegiatan', VariabelTemplate::saran('nama_kegiaatan'));
        $this->assertSame('nama_kegiatan', VariabelTemplate::saran('Nama Kegiatan'));
        $this->assertSame('tahun_anggaran', VariabelTemplate::saran('tahun-anggaran'));
        $this->assertNull(VariabelTemplate::saran('xyz_abc'));

        $analisis = AnalisisTemplate::dari(['nama_kegiatan' => 1, 'nama_kegiaatan' => 2, 'xyz_abc' => 1]);
        $this->assertSame(['nama_kegiaatan', 'xyz_abc'], array_column($analisis->tidakTerdaftar(), 'nama'));
        $this->assertSame(['nama_kegiatan', null], array_column($analisis->tidakTerdaftar(), 'saran'));
        $this->assertSame(['tahun_anggaran', 'total', 'terbilang'], $analisis->dianjurkanBelumAda());
        $this->assertFalse($analisis->bisaDisimpan());
        $this->assertFalse(AnalisisTemplate::dari([])->bisaDisimpan(), 'Tanpa variabel tidak bisa disimpan.');
        $this->assertTrue(AnalisisTemplate::dari(['nama_kegiatan' => 1])->bisaDisimpan());
        $this->assertSame(['nama_kegiatan'], AnalisisTemplate::dari(['nama_kegiatan' => 1, 'salah' => 1])->namaTerdaftar());
    }

    public function test_tab_kak_dengan_daftar_variabel_terdaftar(): void
    {
        Livewire::withQueryParams(['tab' => 'kak'])->test(TemplateDokumen::class)
            ->assertSet('tab', 'kak')
            ->assertSeeHtml('data-testid="unggah-master"')
            ->assertSeeHtml('data-testid="master-kosong"')
            ->assertSeeHtml('data-testid="daftar-variabel"')
            ->assertSeeHtmlInOrder(['Kegiatan &amp; tahun', '${tahun_anggaran}', 'RAB', '${rab}', 'tabel utuh', 'Penanda tangan', '${ttd_kepala}'])
            ->assertSet('namaMaster', 'Master KAK/TOR 2026');

        Livewire::withQueryParams(['tab' => 'nomor_halaman'])->test(TemplateDokumen::class)->assertSet('tab', 'cover');
    }

    public function test_unggah_master_dengan_salah_tulis_ditolak_lalu_yang_benar_disimpan(): void
    {
        $salah = Docx::isi(['Kegiatan ${nama_kegiaatan}', 'Total ${total}', '${Tahun Anggaran}']);

        $komponen = Livewire::test(TemplateDokumen::class)
            ->set('tab', 'kak')
            ->set('berkasMaster', $this->berkas('master-salah.docx', $salah))
            ->assertHasNoErrors()
            ->assertSeeHtml('data-testid="info-master"')
            ->assertSee('3 variabel ditemukan · 1 terdaftar · 2 tidak terdaftar')
            ->assertSeeHtml('data-variabel="nama_kegiaatan" data-terdaftar="tidak"')
            ->assertSeeHtml('Mungkin maksud Anda <code class="font-semibold">${nama_kegiatan}</code>?')
            ->assertSeeHtml('Mungkin maksud Anda <code class="font-semibold">${tahun_anggaran}</code>?')
            ->assertSeeHtml('data-testid="master-tidak-terdaftar"')
            ->assertSeeHtml('wire:key="simpan-master-terkunci" disabled')
            ->call('simpanMaster')
            ->assertHasErrors(['berkasMaster'])
            ->assertSee('Perbaiki variabel yang tidak terdaftar di Word, lalu unggah ulang.');
        $this->assertSame([], app(TemplateDokumenRepository::class)->semua(2026));

        $komponen->set('berkasMaster', $this->berkas('master-kak-2026.docx', $this->masterBenar()))
            ->assertHasNoErrors()
            ->assertSee('7 variabel ditemukan · 7 terdaftar')
            ->assertSeeHtml('data-variabel="total" data-terdaftar="ya"')
            ->assertSee('2×')
            ->assertSee('Belum dipakai: ${nama_kegiatan}')
            ->call('simpanMaster')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Master KAK/TOR tersimpan.')
            ->assertSet('berkasMaster', null)
            ->assertSet('temuanMaster', null)
            ->assertSet('namaMaster', 'Master KAK/TOR 2026 (2)')
            ->assertSeeHtml('data-testid="master-1"')
            ->assertSee('master-kak-2026.docx · 7 variabel');

        $master = app(TemplateDokumenRepository::class)->semua(2026)[0];
        $this->assertSame(['Master KAK/TOR 2026', 'KAK+TOR', true], [$master['nama'], $master['jenis'], $master['is_default']]);
        $this->assertSame(['nama_kegiatan_cover', 'tahun_anggaran', 'total', 'terbilang', 'sumber_dana', 'rab', 'kop'], $master['variabel']);
        Storage::disk('local')->assertExists($master['file_path']);
        $this->assertTrue(collect(KesiapanPengaturan::tahunAktif()->butir)->firstWhere('kunci', 'kak')['siap']);

        // Unduh khusus admin, dengan nama berkas asli.
        $this->get(route('pengaturan.template.master.unduh', $master['id']))->assertForbidden();
        $this->withSession(['stub_role' => 'admin'])->get(route('pengaturan.template.master.unduh', $master['id']))
            ->assertOk()->assertDownload('master-kak-2026.docx');
    }

    public function test_validasi_berkas_dan_dokumen_tanpa_variabel(): void
    {
        Livewire::test(TemplateDokumen::class)
            ->set('tab', 'kak')
            ->set('berkasMaster', UploadedFile::fake()->create('master.pdf', 50, 'application/pdf'))
            ->assertHasErrors(['berkasMaster' => 'extensions'])
            ->assertSee('Pilih dokumen Word .docx (bukan .doc atau PDF).')
            ->set('berkasMaster', $this->berkas('rusak.docx', 'bukan zip'))
            ->assertHasErrors(['berkasMaster'])
            ->assertSee('Berkas bukan dokumen Word (.docx) yang sah.')
            ->assertSet('temuanMaster', null)
            ->set('berkasMaster', $this->berkas('polos.docx', Docx::isi(['Tanpa variabel sama sekali.'])))
            ->assertSeeHtml('data-testid="master-tanpa-variabel"')
            ->call('simpanMaster')
            ->assertHasErrors(['berkasMaster'])
            ->call('batalUnggahMaster')
            ->assertSet('berkasMaster', null)
            ->assertHasNoErrors();
    }

    public function test_hapus_master_dan_default_per_jenis(): void
    {
        $repo = app(TemplateDokumenRepository::class);
        $kak = $repo->tambah(2026, 'KAK A', 'KAK+TOR', $this->berkas('a.docx', $this->masterBenar()), ['total']);
        $this->travel(1)->minutes();
        $rab = $repo->tambah(2026, 'RAB A', 'RAB', $this->berkas('r.docx', $this->masterBenar()), ['rab']);
        $this->travel(1)->minutes();
        $kak2 = $repo->tambah(2026, 'KAK B', 'KAK+TOR', $this->berkas('b.docx', $this->masterBenar()), ['total']);
        $this->assertSame([true, true, false], [$repo->temukan($kak)['is_default'], $repo->temukan($rab)['is_default'], $repo->temukan($kak2)['is_default']]);

        Livewire::test(TemplateDokumen::class)
            ->set('tab', 'kak')
            ->call('mintaHapusMaster', $kak)
            ->assertSee('Hapus template ini?')
            ->call('hapusMaster')
            ->assertDispatched('notify', message: 'Master “KAK A” dihapus.')
            ->assertDontSee('KAK A');

        $this->assertTrue($repo->temukan($kak2)['is_default'], 'Default jenis KAK+TOR pindah ke template berikutnya.');
        $this->assertTrue($repo->temukan($rab)['is_default']);
    }
}
