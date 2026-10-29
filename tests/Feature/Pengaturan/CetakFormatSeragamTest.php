<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\TemplateCoverRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Livewire\Pengaturan\FormatSeragam;
use App\Support\Dokumen\CetakDokumen;
use App\Support\Dokumen\CoverDokumen;
use App\Support\Dokumen\DataPratinjau;
use App\Support\Dokumen\NilaiVariabel;
use App\Support\Dokumen\PembacaTemplateDocx;
use App\Support\Dokumen\PembuatPdfChrome;
use App\Support\Dokumen\PengisiTemplateDocx;
use App\Support\Dokumen\PilihanTemplate;
use App\Support\Dokumen\TeksCover;
use App\Support\Dokumen\VariabelTemplate;
use App\Support\Stub\StubData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\Docx;
use Tests\TestCase;
use ZipArchive;

class CetakFormatSeragamTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin'];

    private function teksDocx(string $isi, string $bagian = 'word/document.xml'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cek');
        file_put_contents($path, $isi);
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = (string) $zip->getFromName($bagian);
        $zip->close();
        unlink($path);

        return html_entity_decode(strip_tags(str_replace(['</w:p>', '<w:br/>'], ["\n", "\n"], $xml)), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function master(): string
    {
        return Docx::isi([
            ['KERANGKA ACUAN KERJA ', '${nama_kegiatan', '_cover}'],
            'Biaya ${total} (${terbilang}) — ${variabel_asing} — ${nama_pejabat_kosong}.',
            '${dasar_hukum}',
            'Penutup.',
        ], header: ['${kop}']);
    }

    public function test_pengisi_master_docx(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'master');
        file_put_contents($path, $this->master());
        $kegiatan = app(KegiatanRepository::class)->temukan(2);
        $nilai = NilaiVariabel::untuk($kegiatan, 'DIPA');
        $hasil = PengisiTemplateDocx::isi($path, $nilai);
        unlink($path);

        $teks = $this->teksDocx($hasil);
        $this->assertStringContainsString('KERANGKA ACUAN KERJA '.$nilai['nama_kegiatan_cover'], $teks, 'Variabel terpecah antar-run tetap terganti.');
        $this->assertStringContainsString('Biaya '.$nilai['total'].' ('.$nilai['terbilang'].')', $teks);
        $this->assertStringContainsString('${variabel_asing}', $teks, 'Tidak terdaftar dibiarkan.');
        $this->assertStringNotContainsString('${dasar_hukum}', $teks);
        foreach ($nilai['dasar_hukum'] as $i => $butir) {
            $this->assertStringContainsString(($i + 1).'. '.$butir."\n", $teks, 'Satu paragraf bernomor per butir.');
        }
        $this->assertStringContainsString('Penutup.', $teks);
        $this->assertStringNotContainsString('${kop}', $this->teksDocx($hasil, 'word/header1.xml'));
        $this->assertSame([], array_filter(array_keys(PembacaTemplateDocx::variabel(tap(tempnam(sys_get_temp_dir(), 'x'), fn ($p) => file_put_contents($p, $hasil)))), fn ($v) => VariabelTemplate::terdaftar($v)), 'Tidak ada variabel terdaftar tersisa.');

        $kosong = PengisiTemplateDocx::isi(tap(tempnam(sys_get_temp_dir(), 'm'), fn ($p) => file_put_contents($p, Docx::isi(['Tempat ${tempat}.']))), ['tempat' => null]);
        $this->assertStringContainsString('Tempat '.PengisiTemplateDocx::KOSONG.'.', $this->teksDocx($kosong));
    }

    public function test_cover_mengikuti_pilihan_template_di_semua_keluaran(): void
    {
        $repo = app(TemplateCoverRepository::class);
        $png = fn (string $n) => new UploadedFile(base_path('tests/fixtures/cover-contoh.png'), $n, 'image/png', null, true);
        $bawaan = $repo->tambah(2026, 'Cover Default', $png('a.png'), TeksCover::bawaan());
        $workshop = $repo->tambah(2026, 'Cover Workshop', $png('b.png'), TeksCover::bawaan());
        $komite = $repo->tambah(2026, 'Cover Komite 1.6', $png('c.png'), TeksCover::bawaan());
        $berkas = config('sigenrabtor.stub.cover_file');
        $semua = json_decode(Storage::disk('local')->get($berkas), true);
        foreach ($semua as &$c) {
            if ($c['id'] === $komite) {
                $c['leading'] = '1.6';
                $c['sumber_dana'] = 'Komite';
            }
        }
        unset($c);
        Storage::disk('local')->put($berkas, json_encode($semua));

        $k1 = app(KegiatanRepository::class)->temukan(1);
        $k2 = app(KegiatanRepository::class)->temukan(2);
        $this->assertSame([$komite, 'Cover Komite 1.6'], [CoverDokumen::untukKegiatan($k1, 'Komite')['cover_id'], CoverDokumen::untukKegiatan($k1, 'Komite')['nama']], 'Cover khusus leading × sumber dana.');
        $this->assertSame($bawaan, CoverDokumen::untukKegiatan($k2, 'DIPA')['cover_id'], 'Selain itu cover default.');

        PilihanTemplate::simpan(2026, ['jenis' => ['workshop' => ['cover' => $workshop]]], self::ADMIN);
        $this->assertSame($workshop, CoverDokumen::untukKegiatan($k1, 'Komite')['cover_id'], 'Pilihan template mengalahkan cover khusus.');
        $this->assertStringContainsString('/dokumen/cover/'.$workshop.'/gambar', (string) CetakDokumen::untuk($k1, 'Komite', ['cover'])['cover']['gambar']);
        $this->assertStringContainsString('/dokumen/cover/'.$workshop.'/gambar', (string) DataPratinjau::untuk($k1, 'Komite', StubData::currentUser(), ['cover'])['dokumen']['cover']['gambar']);
        $this->get(route('kegiatan.cetak', ['kegiatan' => 1]))->assertOk()->assertSee('/dokumen/cover/'.$workshop.'/gambar', false);
        $this->assertStringContainsString('data:image/png;base64,', (new PembuatPdfChrome)->html($k1, 'Komite', ['cover']));
        $this->assertStringStartsWith('data:image/png;base64,', (string) CoverDokumen::gambarDataCover($workshop));
        $this->assertNull(CoverDokumen::gambarDataCover('tidak-ada'));
    }

    public function test_word_memakai_master_terpilih_dan_tombol_contoh(): void
    {
        $master = app(TemplateDokumenRepository::class)->tambah(2026, 'Master Workshop', 'KAK+TOR', UploadedFile::fake()->createWithContent('m.docx', $this->master()), ['nama_kegiatan_cover', 'total', 'terbilang', 'dasar_hukum', 'kop']);
        $k1 = app(KegiatanRepository::class)->temukan(1);
        $this->assertNull(PilihanTemplate::masterKak($k1));
        $this->assertNull(DataPratinjau::untuk($k1, 'Komite', StubData::currentUser())['template']['kak']);
        $sebelum = $this->get(route('kegiatan.kak.word', ['kegiatan' => 1]))->assertOk()->getContent();
        $this->assertStringContainsString('1. LATAR BELAKANG', $this->teksDocx($sebelum), 'Tanpa pilihan: susunan baku.');

        PilihanTemplate::simpan(2026, ['jenis' => ['workshop' => ['kak' => $master]]], self::ADMIN);
        $this->assertSame('Master Workshop', PilihanTemplate::masterKak($k1)['nama']);
        $this->assertSame(['kak' => $master, 'nama' => 'Master Workshop'], DataPratinjau::untuk($k1, 'Komite', StubData::currentUser())['template']);
        $teks = $this->teksDocx($this->get(route('kegiatan.kak.word', ['kegiatan' => 1]))->assertOk()->getContent());
        $this->assertStringContainsString('KERANGKA ACUAN KERJA WORKSHOP PENYUSUNAN KURIKULUM', $teks, 'Word dari master terisi.');
        $this->assertStringNotContainsString('1. LATAR BELAKANG', $teks);
        $k2 = $this->get(route('kegiatan.kak.word', ['kegiatan' => 2]))->assertOk()->getContent();
        $this->assertStringContainsString('1. LATAR BELAKANG', $this->teksDocx($k2), 'Kegiatan jenis lain tetap susunan baku.');

        session(['stub_role' => 'admin']);
        Livewire::test(FormatSeragam::class)->set('tab', 'template')
            ->assertSeeHtml('data-terkunci="0"')
            ->assertSee('data-testid="cetak-contoh-template"', false)
            ->assertSee('data-testid="unduh-pdf-contoh"', false)
            ->assertSee('data-testid="unduh-word-contoh"', false)
            ->set('pilihan.jenis.workshop.kak', '')
            ->assertSeeHtml('data-terkunci="1"')
            ->assertSee('data-testid="simpan-dulu-pratinjau"', false)
            ->assertDontSee('data-testid="cetak-contoh-template"', false);
    }
}
