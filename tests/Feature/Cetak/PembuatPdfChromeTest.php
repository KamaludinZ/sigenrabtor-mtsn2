<?php

namespace Tests\Feature\Cetak;

use App\Contracts\KegiatanRepository;
use App\Contracts\PembuatPdfDokumen;
use App\Contracts\TemplateCoverRepository;
use App\Support\Dokumen\CoverDokumen;
use App\Support\Dokumen\GagalUbahPdf;
use App\Support\Dokumen\PembuatPdfChrome;
use App\Support\Dokumen\TeksCover;
use App\Support\Stub\StubPembuatPdfDokumen;
use Illuminate\Foundation\Vite;
use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

class PembuatPdfChromeTest extends TestCase
{
    private function kegiatan(): array
    {
        return app(KegiatanRepository::class)->temukan(2);
    }

    public function test_pilihan_perender_dari_config(): void
    {
        $this->assertInstanceOf(StubPembuatPdfDokumen::class, app(PembuatPdfDokumen::class), 'Tes memakai PDF tiruan.');
        config(['sigenrabtor.pdf.perender' => 'chrome']);
        $this->app->forgetInstance(PembuatPdfDokumen::class);
        $this->assertInstanceOf(PembuatPdfChrome::class, app(PembuatPdfDokumen::class));

        config(['sigenrabtor.pdf.chrome' => '/bin/sh']);
        $this->assertSame('/bin/sh', PembuatPdfChrome::program(), 'Path yang diatur dan bisa dijalankan.');
        config(['sigenrabtor.pdf.chrome' => '/opt/tidak-ada/chrome']);
        $this->assertNull(PembuatPdfChrome::program());
    }

    public function test_html_halaman_cetak_mandiri_untuk_pdf(): void
    {
        $html = (new PembuatPdfChrome)->html($this->kegiatan(), 'DIPA', ['cover', 'kak', 'rab']);
        $this->assertStringNotContainsString('__laravel_vite_plugin__', $html, 'Bukan server dev Vite.');
        $this->assertStringNotContainsString('@vite/client', $html);
        $this->assertMatchesRegularExpression('#href="file://'.preg_quote(public_path('build'), '#').'/assets/app-[^"]+\.css"#', $html, 'CSS build dari berkas lokal.');
        $this->assertStringNotContainsString(rtrim(asset('build'), '/').'/', $html);
        $this->assertStringContainsString('@page sampul', $html);
        $this->assertStringNotContainsString('window.print()', str_replace('onclick="window.print()"', '', $html), 'Tanpa cetak otomatis.');

        app(TemplateCoverRepository::class)->tambah(2026, 'Cover PDF', new UploadedFile(base_path('tests/fixtures/cover-contoh.png'), 'cover.png', 'image/png', null, true), TeksCover::bawaan());
        $this->assertStringStartsWith('data:image/png;base64,', (string) CoverDokumen::gambarData());
        $denganCover = (new PembuatPdfChrome)->html($this->kegiatan(), 'DIPA', ['cover', 'kak']);
        $this->assertStringContainsString('src="data:image/png;base64,', $denganCover, 'Gambar cover tertanam (rutenya butuh sesi).');
        $this->assertStringNotContainsString('/dokumen/cover/', $denganCover);
    }

    public function test_aset_belum_dibangun(): void
    {
        app(Vite::class)->useManifestFilename('tidak-ada-manifest.json');
        try {
            (new PembuatPdfChrome)->html($this->kegiatan(), 'DIPA', ['kak']);
            $this->fail('Manifest tidak ada.');
        } catch (GagalUbahPdf $e) {
            $this->assertStringContainsString('aset belum dibangun', $e->getMessage());
        } finally {
            app(Vite::class)->useManifestFilename('manifest.json');
        }
    }

    public function test_program_chrome_tidak_terpasang(): void
    {
        config(['sigenrabtor.pdf.chrome' => base_path('tidak-ada/chrome')]);
        $this->expectException(GagalUbahPdf::class);
        $this->expectExceptionMessage('belum terpasang di server');
        (new PembuatPdfChrome)->buat($this->kegiatan(), 'DIPA', ['rab']);
    }

    public function test_pdf_sungguhan_dari_chrome(): void
    {
        if (PembuatPdfChrome::program() === null || ! is_file(public_path('build/manifest.json'))) {
            $this->markTestSkipped('Chrome/Chromium atau aset build tidak tersedia.');
        }
        $pdf = (new PembuatPdfChrome)->buat($this->kegiatan(), 'DIPA', ['cover', 'kak', 'rab']);
        $this->assertStringStartsWith('%PDF-', $pdf);

        $finder = new ExecutableFinder;
        if ($finder->find('pdfinfo') === null || $finder->find('pdftotext') === null) {
            return;
        }
        $berkas = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($berkas, $pdf);
        exec('pdfinfo '.escapeshellarg($berkas), $info);
        exec('pdftotext -layout '.escapeshellarg($berkas).' -', $teks);
        unlink($berkas);
        $info = implode("\n", $info);
        $teks = implode("\n", $teks);
        $this->assertMatchesRegularExpression('/Page size:\s+59\d(\.\d+)? x 84\d(\.\d+)? pts \(A4\)/', $info);
        preg_match('/Pages:\s+(\d+)/', $info, $halaman);
        $this->assertGreaterThanOrEqual(3, (int) $halaman[1], 'Cover, KAK/TOR, dan RAB.');
        $this->assertStringContainsString('TOTAL KESELURUHAN', $teks);
        $this->assertStringContainsString('Halaman 2 dari '.$halaman[1], $teks, 'Nomor halaman di kaki @page.');
        $this->assertStringContainsString('DRAF — dicetak', $teks, 'Kaki DRAF selama belum siap.');
    }
}
