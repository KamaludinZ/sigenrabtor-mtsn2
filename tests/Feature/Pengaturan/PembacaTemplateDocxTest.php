<?php

namespace Tests\Feature\Pengaturan;

use App\Livewire\Pengaturan\TemplateMaster;
use App\Support\Dokumen\PembacaTemplateDocx;
use App\Support\Dokumen\ValidasiVariabelTemplate;
use App\Support\Pengaturan\UnggahMasterTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class PembacaTemplateDocxTest extends TestCase
{
    private const NS = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"';

    /** @var list<string> */
    private array $berkas = [];

    protected function tearDown(): void
    {
        array_map('unlink', array_filter($this->berkas, 'is_file'));
        parent::tearDown();
    }

    /**
     * Arsip .docx dengan isi XML mentah (body dokumen) dan bagian tambahan.
     *
     * @param  array<string, string>  $tambahan  Nama entri → isi.
     */
    private function docx(string $body, array $tambahan = []): string
    {
        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document '.self::NS.'><w:body>'.$body.'</w:body></w:document>');
        foreach ($tambahan as $nama => $isi) {
            $zip->addFromString($nama, $isi);
        }
        $zip->close();

        return $this->berkas[] = $path;
    }

    private function p(string $xml): string
    {
        return '<w:p>'.$xml.'</w:p>';
    }

    public function test_teks_bukan_dokumen_dibuang_dan_run_disambung(): void
    {
        $body = $this->p('<w:r><w:t>Kegiatan ${nama_</w:t></w:r><w:r><w:t>kegiatan}</w:t></w:r>')
            // Teks terhapus lewat lacak perubahan & kode field tidak dihitung.
            .$this->p('<w:del w:id="1"><w:r><w:delText>${total}</w:delText></w:r></w:del><w:r><w:instrText> MERGEFIELD ${terbilang} </w:instrText></w:r>')
            // Kotak teks: Choice & Fallback memuat teks yang sama — dihitung sekali.
            .$this->p('<mc:AlternateContent><mc:Choice><w:txbxContent>'.$this->p('<w:r><w:t>${tujuan}</w:t></w:r>').'</w:txbxContent></mc:Choice><mc:Fallback><w:txbxContent>'.$this->p('<w:r><w:t>${tujuan}</w:t></w:r>').'</w:txbxContent></mc:Fallback></mc:AlternateContent>')
            .$this->p('<w:r><w:t>Baris</w:t><w:br w:type="page"/><w:t>${tempat}</w:t><w:tab w:val="left"/><w:t>x</w:t></w:r>');
        $kepala = '<?xml version="1.0"?><w:hdr '.self::NS.'>'.$this->p('<w:r><w:t>${nama_kegiatan} — ${kop}</w:t></w:r>').'</w:hdr>';
        $hasil = PembacaTemplateDocx::baca($this->docx($body, ['word/header1.xml' => $kepala]));

        $this->assertSame([
            'nama_kegiatan' => ['jumlah' => 2, 'bagian' => ['isi', 'header']],
            'tujuan' => ['jumlah' => 1, 'bagian' => ['isi']],
            'tempat' => ['jumlah' => 1, 'bagian' => ['isi']],
            'kop' => ['jumlah' => 1, 'bagian' => ['header']],
        ], $hasil['variabel']);
        $this->assertSame([], $hasil['tidak_tertutup']);
        $this->assertSame(['header', 'isi', 'isi', 'isi', 'isi'], array_column($hasil['paragraf'], 'bagian'), 'Paragraf kosong (teks terhapus) dilewati; ganti halaman = baris baru.');
        $this->assertContains('${tempat} x', array_column($hasil['paragraf'], 'teks'));
        $this->assertSame(['nama_kegiatan' => 2, 'tujuan' => 1, 'tempat' => 1, 'kop' => 1], PembacaTemplateDocx::variabel($this->docx($body, ['word/header1.xml' => $kepala])));
    }

    public function test_penanda_tidak_tertutup_menahan_simpan(): void
    {
        $path = $this->docx($this->p('<w:r><w:t>Kegiatan ${nama_kegiatan</w:t></w:r>').$this->p('<w:r><w:t>Total ${total}</w:t></w:r>'));
        $this->assertSame(['${nama_kegiatan'], PembacaTemplateDocx::baca($path)['tidak_tertutup']);

        $hasil = ValidasiVariabelTemplate::periksa($path, 'KAK+TOR');
        $this->assertFalse($hasil['bisa_disimpan']);
        $this->assertSame([
            'berkas' => 'Perbaiki penanda yang tidak tertutup di Word, lalu unggah ulang.',
            'penanda' => 'Penanda tanpa kurung tutup: “${nama_kegiatan” — lengkapi menjadi ${nama_variabel}.',
        ], $hasil['galat']);
    }

    public function test_berkas_berbahaya_atau_tidak_sah_ditolak(): void
    {
        $tolak = function (string $path): string {
            try {
                PembacaTemplateDocx::baca($path);
            } catch (RuntimeException $e) {
                return $e->getMessage();
            }
            $this->fail('Seharusnya ditolak.');
        };

        $ole = tempnam(sys_get_temp_dir(), 'ole');
        file_put_contents($ole, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 100));
        $this->berkas[] = $ole;
        $this->assertStringStartsWith('Berkas terlindungi kata sandi atau berformat Word lama (.doc)', $tolak($ole));

        $this->assertStringStartsWith('Berkas berisi makro', $tolak($this->docx($this->p(''), ['word/vbaProject.bin' => 'makro'])));
        $this->assertStringStartsWith('Isi berkas Word terlalu besar', $tolak($this->docx($this->p(''), ['word/media/besar.bin' => str_repeat('a', PembacaTemplateDocx::MAKS_UKURAN_ISI + 1)])));

        $tanpaIsi = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive;
        $zip->open($tanpaIsi, ZipArchive::OVERWRITE);
        $zip->addFromString('a.txt', 'a');
        $zip->close();
        $this->berkas[] = $tanpaIsi;
        $this->assertSame('Berkas bukan dokumen Word (.docx) yang sah — isi dokumen tidak ditemukan.', $tolak($tanpaIsi));

        // Lewat unggah: pesan yang sama sebagai galat berkas.
        try {
            UnggahMasterTemplate::simpan(2026, new UploadedFile($this->docx($this->p('<w:r><w:t>${total}</w:t></w:r>'), ['word/vbaProject.bin' => 'x']), 'm.docx', null, null, true), 'M', 'KAK', ['role' => 'admin']);
            $this->fail('Berkas bermakro ditolak.');
        } catch (ValidationException $e) {
            $this->assertStringStartsWith('Berkas berisi makro', $e->errors()['berkas'][0]);
        }
    }

    public function test_formulir_unggah_menampilkan_penanda_tidak_tertutup(): void
    {
        $this->withSession(['stub_role' => 'admin']);
        $path = $this->docx($this->p('<w:r><w:t>Kegiatan ${nama_kegiatan</w:t></w:r>').$this->p('<w:r><w:t>Total ${total}</w:t></w:r>'));

        Livewire::test(TemplateMaster::class)
            ->call('bukaUnggah')
            ->set('berkas', UploadedFile::fake()->createWithContent('kak.docx', (string) file_get_contents($path)))
            ->assertSeeHtml('data-testid="galat-penanda"')
            ->assertSeeHtml('data-testid="simpan-template-belum"');
    }
}
