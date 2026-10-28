<?php

namespace Tests\Feature\Buku;

use App\Contracts\KegiatanRepository;
use App\Contracts\PenggabungPdf;
use App\Support\Dokumen\BukuDokumen;
use App\Support\Dokumen\GabungPdfBuku;
use App\Support\Dokumen\GagalGabungPdf;
use App\Support\Dokumen\NomorHalaman;
use App\Support\Dokumen\PdfSederhana;
use App\Support\Dokumen\PenggabungPdfPoppler;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PenggabungPdfPalsu;
use Tests\TestCase;

class GabungPdfBukuTest extends TestCase
{
    private string $kerja;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kerja = storage_path('framework/testing/gabung-'.uniqid());
        File::ensureDirectoryExists($this->kerja);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->kerja);
        parent::tearDown();
    }

    private function pdf(string $nama, int $halaman): string
    {
        file_put_contents($path = "{$this->kerja}/{$nama}", PdfSederhana::tulis(array_fill(0, $halaman, ["Halaman {$nama}"])));

        return $path;
    }

    /** @return list<array<string, mixed>> */
    private function kegiatan(): array
    {
        return BukuDokumen::tahunAktif(app(KegiatanRepository::class)->semua());
    }

    private function poppler(): void
    {
        foreach (['pdfunite', 'pdfinfo'] as $program) {
            if (trim((string) shell_exec('command -v '.escapeshellarg($program))) === '') {
                $this->markTestSkipped("{$program} (poppler-utils) tidak terpasang.");
            }
        }
    }

    public function test_bagian_buku_mengikuti_simulasi_halaman(): void
    {
        $isi = BukuDokumen::isi($this->kegiatan(), '1.6', 'DIPA');
        $bagian = GabungPdfBuku::bagian($isi['simulasi'], $isi['baris']);

        $this->assertSame(['cover_buku', 'daftar_isi', 'cover_kegiatan', 'kak', 'rab', 'cover_kegiatan', 'kak', 'rab'], array_column($bagian, 'jenis'));
        $kak = NomorHalaman::HALAMAN_KEGIATAN['kak'];
        $this->assertSame([1, 1, 1, $kak, NomorHalaman::HALAMAN_KEGIATAN['rab'], 1, $kak, NomorHalaman::HALAMAN_KEGIATAN['rab']], array_column($bagian, 'halaman'));
        $this->assertSame(count($isi['simulasi']['halaman']), array_sum(array_column($bagian, 'halaman')));
        $this->assertSame([null, null, ...array_merge(...array_map(fn ($b) => array_fill(0, 3, $b['id']), $isi['baris']))], array_column($bagian, 'kegiatan_id'));
        $this->assertSame([$isi['simulasi']['halaman'][3]['label'], $isi['simulasi']['halaman'][3 + $kak - 1]['label']], [$bagian[3]['label_awal'], $bagian[3]['label_akhir']]);
        $this->assertSame('KAK/TOR', $bagian[3]['judul']);
        $this->assertSame($isi['baris'][0]['nama_kegiatan'], $bagian[3]['nama_kegiatan']);
    }

    public function test_gabung_memeriksa_berkas_dan_jumlah_halaman(): void
    {
        $this->app->instance(PenggabungPdf::class, $palsu = new PenggabungPdfPalsu);
        $hasil = GabungPdfBuku::gabung([$this->pdf('a.pdf', 2), $this->pdf('b.pdf', 3)], "{$this->kerja}/buku.pdf", 5);
        $this->assertSame(5, $hasil['halaman']);
        $this->assertGreaterThan(0, $hasil['ukuran']);
        $this->assertCount(1, $palsu->panggilan);

        file_put_contents("{$this->kerja}/rusak.pdf", 'bukan pdf');
        foreach ([
            'Bagian buku ke-2 bukan PDF yang sah — susun ulang buku.' => fn () => GabungPdfBuku::gabung([$this->pdf('c.pdf', 1), "{$this->kerja}/rusak.pdf"], "{$this->kerja}/x.pdf"),
            'Bagian buku ke-1 bukan PDF yang sah — susun ulang buku.' => fn () => GabungPdfBuku::gabung(["{$this->kerja}/hilang.pdf"], "{$this->kerja}/x.pdf"),
            'PDF buku berisi 3 halaman, seharusnya 4 sesuai susunan buku — susun ulang buku.' => fn () => GabungPdfBuku::gabung([$this->pdf('d.pdf', 3)], "{$this->kerja}/x.pdf", 4),
        ] as $pesan => $aksi) {
            try {
                $aksi();
                $this->fail("Seharusnya gagal: {$pesan}");
            } catch (GagalGabungPdf $e) {
                $this->assertSame($pesan, $e->getMessage());
            }
        }
    }

    public function test_buat_pdf_buku_dari_kegiatan_tahun_aktif(): void
    {
        $this->app->instance(PenggabungPdf::class, $palsu = new PenggabungPdfPalsu);
        $buku = ['leading' => '1.6', 'sumber' => 'Komite', 'tahun' => 2026];
        $hasil = GabungPdfBuku::buat($buku, $this->kegiatan(), 'buku/uji.pdf');

        $harapan = count(BukuDokumen::isi($this->kegiatan(), '1.6', 'Komite')['simulasi']['halaman']);
        $this->assertSame(['buku/uji.pdf', $harapan, 2 + 3 * 3], [$hasil['path'], $hasil['halaman'], $hasil['bagian']]);
        Storage::disk('local')->assertExists('buku/uji.pdf');
        $this->assertCount($hasil['bagian'], $palsu->panggilan[0]);
        $this->assertSame([], glob(storage_path('framework/cache/buku-*')) ?: [], 'Berkas kerja sementara dibersihkan.');

        $this->app->instance(PenggabungPdf::class, new PenggabungPdfPalsu('Penggabung tidak tersedia.'));
        $this->expectExceptionObject(new GagalGabungPdf('Penggabung tidak tersedia.'));
        GabungPdfBuku::buat($buku, $this->kegiatan(), 'buku/gagal.pdf');
    }

    public function test_penggabung_poppler_sungguhan(): void
    {
        $this->poppler();
        $penggabung = new PenggabungPdfPoppler;
        $penggabung->gabung([$this->pdf('a.pdf', 2), $this->pdf('b.pdf', 3)], $tujuan = "{$this->kerja}/gabung.pdf");
        $this->assertSame(5, $penggabung->jumlahHalaman($tujuan));
        $penggabung->gabung([$this->pdf('c.pdf', 4)], $satu = "{$this->kerja}/satu.pdf");
        $this->assertSame(4, $penggabung->jumlahHalaman($satu));

        // Bawaan aplikasi memakai poppler: buku contoh tergabung dengan jumlah halaman sesuai susunan.
        $hasil = GabungPdfBuku::buat(['leading' => '1.6', 'sumber' => 'DIPA', 'tahun' => 2026], $this->kegiatan(), 'buku/poppler.pdf');
        $this->assertSame(count(BukuDokumen::isi($this->kegiatan(), '1.6', 'DIPA')['simulasi']['halaman']), $hasil['halaman']);

        $this->expectException(GagalGabungPdf::class);
        $penggabung->gabung(["{$this->kerja}/tidak-ada.pdf", $this->pdf('e.pdf', 1)], "{$this->kerja}/x.pdf");
    }
}
