<?php

namespace Tests\Feature\Cetak;

use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\DataPratinjau;
use App\Support\Dokumen\Generasi\DocxSederhana;
use App\Support\Dokumen\Generasi\FormatBerkas;
use App\Support\Dokumen\Generasi\WordKakTor;
use App\Support\Stub\StubData;
use DOMDocument;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;
use ZipArchive;

class WordKakTorTest extends TestCase
{
    /** @return array<string, string> Isi tiap bagian paket .docx. */
    private function buka(string $docx): array
    {
        $berkas = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($berkas, $docx);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($berkas));
        $isi = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $isi[$zip->getNameIndex($i)] = (string) $zip->getFromIndex($i);
        }
        $zip->close();
        unlink($berkas);

        return $isi;
    }

    private function data(int $kegiatan, ?string $sumber, array $lembar): array
    {
        return DataPratinjau::untuk(app(KegiatanRepository::class)->temukan($kegiatan), $sumber, StubData::currentUser(), $lembar);
    }

    public function test_paket_docx_sah_dan_bagian_kak(): void
    {
        $paket = $this->buka(WordKakTor::susun($this->data(3, 'Komite', ['cover', 'kak'])));
        $this->assertSame('[Content_Types].xml', array_key_first($paket));
        foreach (['word/document.xml', 'word/styles.xml', 'word/settings.xml', 'word/_rels/document.xml.rels', '_rels/.rels', 'docProps/core.xml', 'word/header1.xml', 'word/footer2.xml'] as $bagian) {
            $this->assertArrayHasKey($bagian, $paket);
        }
        foreach ($paket as $nama => $xml) {
            $dom = new DOMDocument;
            $this->assertTrue($dom->loadXML($xml), "{$nama} XML sah.");
        }

        $dokumen = strip_tags(str_replace('</w:p>', "\n", $paket['word/document.xml']));
        $this->assertStringContainsString(WordKakTor::JUDUL, $dokumen);
        $this->assertStringContainsString('KEGIATAN REVIEW RKJM DAN PENYUSUNAN RKTM', $dokumen, 'Cover.');
        foreach (['1. LATAR BELAKANG', '2. DASAR HUKUM', '4. TAHAPAN KEGIATAN', '7. JADWAL KEGIATAN', '9. LEMBAR PENGESAHAN'] as $judul) {
            $this->assertStringContainsString($judul, $dokumen);
        }
        $this->assertSame(2, substr_count($paket['word/document.xml'], '<w:sectPr>'), 'Cover & KAK/TOR = dua section.');
        $this->assertStringContainsString('<w:tblHeader/>', $paket['word/document.xml'], 'Kepala tabel berulang.');
        $this->assertStringContainsString('DRAF', $paket['word/header2.xml']);
        $this->assertStringContainsString(' PAGE ', $paket['word/footer2.xml']);
        $this->assertStringContainsString(' NUMPAGES ', $paket['word/footer2.xml']);
        $this->assertStringContainsString('DRAF — ', $paket['word/footer2.xml']);
        $this->assertStringContainsString('<dc:title>KAK-TOR_Review_RKJM', $paket['docProps/core.xml']);
        $this->assertStringContainsString(' — Format 9 bagian · susunan baku</dc:description>', $paket['docProps/core.xml'], 'Template yang dipakai tercatat di properti dokumen.');
    }

    public function test_rab_dan_penulis_docx(): void
    {
        $paket = $this->buka(WordKakTor::susun($this->data(2, 'DIPA', ['rab'])));
        $dokumen = $paket['word/document.xml'];
        $this->assertStringContainsString('TOTAL KESELURUHAN', $dokumen);
        $this->assertStringContainsString('Terbilang: ', $dokumen);
        $this->assertStringContainsString('w:fill="F5F5F4"', $dokumen);

        $this->assertSame('a &amp; b &lt;c&gt;', DocxSederhana::teks("a & b <c>\x01"));
        $run = DocxSederhana::run("No\tUraian\nBaris dua", ['tebal' => true, 'warna' => '#B91C1C']);
        $this->assertStringContainsString('<w:tab/>', $run);
        $this->assertStringContainsString('<w:br/>', $run);
        $this->assertStringContainsString('<w:color w:val="B91C1C"/>', $run);
        $this->assertStringContainsString('<w:b/>', $run);
        $this->assertSame(DocxSederhana::MIME, FormatBerkas::mime('docx'));
    }

    public function test_endpoint_word_kak(): void
    {
        $respons = $this->get(route('kegiatan.kak.word', ['kegiatan' => 3]))
            ->assertOk()
            ->assertHeader('Content-Type', DocxSederhana::MIME)
            ->assertHeader('X-Format-Berkas', 'docx');
        $this->assertMatchesRegularExpression('/^KAK-TOR_Review_RKJM_dan_Penyusunan_RKTM_DIPA_2026(_DRAF)?\.docx$/', $respons->headers->get('X-Nama-Berkas'));
        $this->assertStringStartsWith('PK', $respons->getContent());
        $tor = $this->get(route('kegiatan.kak.word', ['kegiatan' => 3, 'jenis' => 'TOR', 'sumber' => 'Komite']))->assertOk();
        $this->assertStringContainsString('_KOMITE_2026', (string) $tor->headers->get('X-Nama-Berkas'), 'Sumber dana yang diminta.');
        $this->get(route('kegiatan.kak.word', ['kegiatan' => 3, 'jenis' => 'RAB']), ['Accept' => DocxSederhana::MIME.', application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)->assertJsonStructure(['pesan', 'galat' => ['format']]);
        $this->get(route('kegiatan.kak.word', ['kegiatan' => 999]))->assertNotFound();
    }

    public function test_word_terbuka_di_libreoffice(): void
    {
        $finder = new ExecutableFinder;
        if ($finder->find((string) config('sigenrabtor.pdf.soffice', 'soffice')) === null || $finder->find('pdfinfo') === null) {
            $this->markTestSkipped('LibreOffice/pdfinfo tidak tersedia.');
        }
        $kerja = sys_get_temp_dir().'/word-'.uniqid();
        mkdir($kerja);
        file_put_contents($kerja.'/kak.docx', WordKakTor::susun($this->data(2, 'DIPA', ['cover', 'kak', 'rab'])));
        exec(implode(' ', array_map('escapeshellarg', [(string) config('sigenrabtor.pdf.soffice', 'soffice'), '-env:UserInstallation=file://'.$kerja.'/profil', '--headless', '--convert-to', 'pdf', '--outdir', $kerja, $kerja.'/kak.docx'])).' 2>/dev/null');
        exec('pdfinfo '.escapeshellarg($kerja.'/kak.pdf'), $info);
        exec('rm -rf '.escapeshellarg($kerja));
        preg_match('/Pages:\s+(\d+)/', implode("\n", $info), $halaman);
        $this->assertGreaterThanOrEqual(4, (int) ($halaman[1] ?? 0), 'Cover, KAK/TOR, dan RAB terbuka sebagai dokumen.');
    }
}
