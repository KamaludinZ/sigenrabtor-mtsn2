<?php

namespace Tests\Feature\Monitoring;

use App\Contracts\PengubahPdf;
use App\Support\Dokumen\GagalUbahPdf;
use App\Support\Dokumen\PengubahPdfLibreOffice;
use App\Support\Monitoring\EksporExcel;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

class EksporPdfTest extends TestCase
{
    /** Pengubah palsu: mencatat berkas yang diubah (harus .xlsx yang masih ada) lalu mengembalikan PDF tiruan. */
    private function pengubahPalsu(?string $galat = null): object
    {
        $palsu = new class($galat) implements PengubahPdf
        {
            public ?string $berkas = null;

            public bool $adaSaatDiubah = false;

            public function __construct(private ?string $galat) {}

            public function ubah(string $berkas): string
            {
                $this->berkas = $berkas;
                $this->adaSaatDiubah = is_file($berkas) && str_ends_with($berkas, '.xlsx');
                if ($this->galat) {
                    throw new GagalUbahPdf($this->galat);
                }

                return '%PDF-1.4 palsu';
            }
        };
        $this->app->instance(PengubahPdf::class, $palsu);

        return $palsu;
    }

    public function test_unduh_pdf_khusus_admin_dari_buku_ekspor(): void
    {
        $palsu = $this->pengubahPalsu();
        $this->get(route('monitoring.ekspor.pdf'))->assertForbidden();

        $respons = $this->withSession(['stub_role' => 'admin'])->get(route('monitoring.ekspor.pdf', ['sumber' => 'Komite', 'status' => 'draf']));
        $respons->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame('%PDF-1.4 palsu', $respons->getContent());
        $this->assertStringContainsString('monitoring-anggaran-TA2026-komite-draf.pdf', $respons->headers->get('content-disposition'));
        $this->assertTrue($palsu->adaSaatDiubah, 'Buku Excel ditulis dulu lalu diubah.');
        $this->assertFileDoesNotExist($palsu->berkas, 'Berkas sementara dihapus.');
    }

    public function test_gagal_ubah_kembali_ke_halaman_dengan_notifikasi(): void
    {
        $this->pengubahPalsu('PDF belum bisa dibuat: konversi terlalu lama. Coba lagi atau unduh versi Excel.');

        $this->withSession(['stub_role' => 'admin'])->get(route('monitoring.ekspor.pdf', ['leading' => '1.6']))
            ->assertRedirect(route('monitoring.index', ['leading' => '1.6']))
            ->assertSessionHas('notify', ['message' => 'PDF belum bisa dibuat: konversi terlalu lama. Coba lagi atau unduh versi Excel.', 'type' => 'error']);
    }

    public function test_pengaturan_cetak_a4_lanskap(): void
    {
        [$buku] = EksporExcel::dariPermintaan([]);

        foreach ($buku->getAllSheets() as $i => $lembar) {
            $this->assertSame([PageSetup::ORIENTATION_LANDSCAPE, PageSetup::PAPERSIZE_A4, 1, 0], [$lembar->getPageSetup()->getOrientation(), $lembar->getPageSetup()->getPaperSize(), $lembar->getPageSetup()->getFitToWidth(), $lembar->getPageSetup()->getFitToHeight()]);
            $this->assertStringContainsString('Monitoring Anggaran TA 2026', $lembar->getHeaderFooter()->getOddHeader());
            $this->assertStringContainsString('Halaman &P dari &N', $lembar->getHeaderFooter()->getOddFooter());
            $this->assertSame($i > 0, $lembar->getPageSetup()->isRowsToRepeatAtTopSet(), 'Judul tabel diulang di tiap halaman (selain Ringkasan).');
        }
    }

    public function test_pengubah_libreoffice_menolak_berkas_tidak_ada_dan_program_tidak_ada(): void
    {
        $pengubah = new PengubahPdfLibreOffice;
        try {
            $pengubah->ubah('/tidak/ada.xlsx');
            $this->fail('Harus gagal.');
        } catch (GagalUbahPdf $e) {
            $this->assertSame('Berkas yang akan diubah ke PDF tidak ditemukan.', $e->getMessage());
        }

        config(['sigenrabtor.pdf.soffice' => '/tidak/ada/soffice']);
        $berkas = tempnam(sys_get_temp_dir(), 'xlsx');
        try {
            $this->expectException(GagalUbahPdf::class);
            $this->expectExceptionMessageMatches('/^PDF belum bisa dibuat: /');
            $pengubah->ubah($berkas);
        } finally {
            unlink($berkas);
        }
    }

    public function test_konversi_sungguhan_lewat_libreoffice(): void
    {
        if ((new ExecutableFinder)->find((string) config('sigenrabtor.pdf.soffice')) === null) {
            $this->markTestSkipped('LibreOffice (soffice) tidak terpasang di mesin ini.');
        }

        [$buku] = EksporExcel::dariPermintaan([]);
        $berkas = storage_path('framework/cache/uji-ekspor.xlsx');
        (new Xlsx($buku))->save($berkas);
        try {
            $pdf = app(PengubahPdf::class)->ubah($berkas);
        } finally {
            unlink($berkas);
        }

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame(count(EksporExcel::LEMBAR), preg_match_all('#/Type\s*/Page[^s]#', $pdf), 'Satu halaman per lembar.');
    }
}
