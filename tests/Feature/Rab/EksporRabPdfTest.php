<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Contracts\PengubahPdf;
use App\Livewire\Kegiatan\Rab;
use App\Support\Dokumen\GagalUbahPdf;
use App\Support\Dokumen\PengubahPdfLibreOffice;
use App\Support\Rab\EksporRabPdf;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

class EksporRabPdfTest extends TestCase
{
    /** Pengubah palsu: memeriksa berkas .xlsx RAB yang diubah, lalu mengembalikan PDF tiruan (atau gagal). */
    private function pengubahPalsu(?string $galat = null): object
    {
        $palsu = new class($galat) implements PengubahPdf
        {
            public ?string $berkas = null;

            /** @var list<string> */
            public array $lembar = [];

            public function __construct(private ?string $galat) {}

            public function ubah(string $berkas): string
            {
                $this->berkas = $berkas;
                $this->lembar = IOFactory::load($berkas)->getSheetNames();
                if ($this->galat) {
                    throw new GagalUbahPdf($this->galat);
                }

                return '%PDF-1.4 palsu';
            }
        };
        $this->app->instance(PengubahPdf::class, $palsu);

        return $palsu;
    }

    public function test_pdf_dari_buku_excel_yang_sama(): void
    {
        $palsu = $this->pengubahPalsu();

        $hasil = EksporRabPdf::buat(app(KegiatanRepository::class)->temukan(2), 'Komite');
        $this->assertSame(['%PDF-1.4 palsu', 'RAB_Supervisi_Akademik_dan_Refleksi_Pembelajaran_KOMITE_2026_DRAF.pdf'], [$hasil['isi'], $hasil['nama']]);
        $this->assertSame(['RAB Komite'], $palsu->lembar);
        $this->assertFileDoesNotExist($palsu->berkas, 'Berkas sementara dihapus.');

        $this->get(route('kegiatan.rab.pdf', ['kegiatan' => 2]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="RAB_Supervisi_Akademik_dan_Refleksi_Pembelajaran_2026_DRAF.pdf"');
        $this->assertSame(['RAB DIPA', 'RAB Komite'], $palsu->lembar);

        $this->get(route('kegiatan.rab.pdf', ['kegiatan' => 2, 'sumber' => 'BOS']))->assertNotFound();
        $lain = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Kegiatan Leading Lain', 'leading' => '1.2', 'user_id' => 2, 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 1_000_000]]]);
        $this->get(route('kegiatan.rab.pdf', ['kegiatan' => $lain]))->assertForbidden();

        Livewire::test(Rab::class, ['kegiatan' => 2])->assertSeeHtml('data-testid="unduh-rab-pdf"');
    }

    public function test_gagal_diubah_kembali_ke_halaman_rab(): void
    {
        $palsu = $this->pengubahPalsu('Mesin dokumen (LibreOffice) tidak tersedia di server.');

        $this->get(route('kegiatan.rab.pdf', ['kegiatan' => 2, 'sumber' => 'DIPA']))
            ->assertRedirect(route('kegiatan.rab', ['kegiatan' => 2, 'sumber' => 'DIPA']))
            ->assertSessionHas('notify', ['message' => 'Mesin dokumen (LibreOffice) tidak tersedia di server.', 'type' => 'error']);
        $this->assertFileDoesNotExist($palsu->berkas);
    }

    public function test_konversi_sungguhan_bila_libreoffice_ada(): void
    {
        if ((new ExecutableFinder)->find(config('sigenrabtor.pdf.soffice', 'soffice')) === null) {
            $this->markTestSkipped('LibreOffice (soffice) tidak terpasang.');
        }
        $this->app->instance(PengubahPdf::class, app(PengubahPdfLibreOffice::class));

        $hasil = EksporRabPdf::buat(app(KegiatanRepository::class)->temukan(1), 'Komite');

        $this->assertStringStartsWith('%PDF-', $hasil['isi']);
        $this->assertGreaterThan(5_000, strlen($hasil['isi']));
    }
}
