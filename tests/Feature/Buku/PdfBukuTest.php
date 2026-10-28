<?php

namespace Tests\Feature\Buku;

use App\Contracts\BukuDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\PembuatPdfBuku;
use App\Contracts\PenggabungPdf;
use App\Livewire\Buku\Daftar;
use App\Support\Dokumen\ProsesPdfBuku;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\PenggabungPdfPalsu;
use Tests\TestCase;

class PdfBukuTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Tanpa poppler-utils: penggabungan bagian buku dipalsukan (uji poppler sungguhan di GabungPdfBukuTest).
        $this->app->instance(PenggabungPdf::class, new PenggabungPdfPalsu);
    }

    public function test_tahap_pembuatan_pdf(): void
    {
        $this->assertSame(['susun', 'konversi', null], [ProsesPdfBuku::berikutnya('antre'), ProsesPdfBuku::berikutnya('susun'), ProsesPdfBuku::berikutnya('nomor')]);
        $this->assertSame([0, 40, 100], [ProsesPdfBuku::persen('antre', 'antre'), ProsesPdfBuku::persen('konversi', 'proses'), ProsesPdfBuku::persen('nomor', 'selesai')]);
        $this->assertSame(['selesai', 'gagal', 'menunggu', 'menunggu', 'menunggu'], array_column(ProsesPdfBuku::langkah(['status' => 'gagal', 'tahap' => 'susun']), 'keadaan'));
        $this->assertSame(['selesai', 'selesai', 'berjalan', 'menunggu', 'menunggu'], array_column(ProsesPdfBuku::langkah(['status' => 'proses', 'tahap' => 'konversi']), 'keadaan'));
        $this->assertTrue(ProsesPdfBuku::berjalan(['status' => 'antre']));
        $this->assertFalse(ProsesPdfBuku::berjalan(null));
    }

    public function test_gabung_pdf_dipantau_sampai_bisa_diunduh(): void
    {
        $komponen = Livewire::test(Daftar::class)
            ->assertSeeHtml('data-testid="gabung-pdf"')
            ->call('gabungPdf')
            ->assertSeeHtml('data-testid="status-pdf" data-status="antre"')
            ->assertSeeHtml('wire:poll.750ms="pantauPdf"')
            ->assertDontSeeHtml('data-testid="gabung-pdf"');

        foreach (['susun', 'konversi', 'gabung', 'nomor'] as $tahap) {
            $komponen->call('pantauPdf')->assertSeeHtml('data-tahap="'.$tahap.'" data-keadaan="berjalan"');
        }
        $komponen->call('pantauPdf')
            ->assertDispatched('notify', message: 'PDF buku siap diunduh.', type: 'success')
            ->assertSeeHtml('data-testid="status-pdf" data-status="selesai"')
            ->assertSeeHtml('data-testid="unduh-pdf-buku"')
            ->assertDontSeeHtml('wire:poll.750ms="pantauPdf"');

        $buku = app(BukuDokumenRepository::class)->riwayat(2026)[0];
        $this->assertSame(['1.6', 'DIPA', 'selesai'], [$buku['leading'], $buku['sumber'], $buku['pdf']['status']]);
        $isi = Storage::disk('local')->get($buku['file_pdf_path']);
        $this->assertStringStartsWith('%PDF-', $isi);
        $this->assertSame($buku['halaman'], (new PenggabungPdfPalsu)->jumlahHalaman(Storage::disk('local')->path($buku['file_pdf_path'])), 'Bagian buku tergabung sejumlah halaman susunannya.');

        $this->get(route('buku.unduh', ['buku' => $buku['id']]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename=TOR_RAB_Tim_Penjamin_Mutu_DIPA_2026.pdf');
    }

    public function test_gagal_bila_rab_kosong_lalu_coba_lagi(): void
    {
        app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Rapat Mutu', 'leading' => '1.6', 'kode_rktm' => '6.17.1', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 500_000]]]);

        $komponen = Livewire::test(Daftar::class)->call('gabungPdf')->call('pantauPdf')->call('pantauPdf')
            ->assertSeeHtml('data-testid="status-pdf" data-status="gagal"')
            ->assertSeeHtml('data-tahap="susun" data-keadaan="gagal"')
            ->assertSee('RAB DIPA kegiatan “Rapat Mutu” belum berisi rincian — lengkapi lalu coba lagi.', false)
            ->assertSeeHtml('data-testid="ulangi-pdf-buku"');

        $jumlah = count(app(BukuDokumenRepository::class)->riwayat(2026));
        $komponen->call('gabungPdf')->assertSeeHtml('data-testid="status-pdf" data-status="antre"');
        $this->assertCount($jumlah + 1, app(BukuDokumenRepository::class)->riwayat(2026), 'Coba lagi = pembuatan baru.');
    }

    public function test_unduh_hanya_buku_leading_sendiri(): void
    {
        $buku = app(PembuatPdfBuku::class)->mulai(['tahun' => 2026, 'leading' => '1.1', 'sumber' => 'DIPA', 'jumlah_kegiatan' => 0, 'halaman' => 2, 'ada_draf' => false, 'kegiatan' => [], 'dibuat_oleh' => 'Koordinator PUSKOM']);
        foreach (range(1, 5) as $_) {
            $buku = app(PembuatPdfBuku::class)->pantau($buku['id']);
        }
        $this->assertSame('selesai', $buku['pdf']['status']);

        $this->get(route('buku.unduh', ['buku' => $buku['id']]))->assertForbidden();
        $this->withSession(['stub_role' => 'admin'])->get(route('buku.unduh', ['buku' => $buku['id']]))->assertOk();
        $this->withSession(['stub_role' => 'admin'])->get(route('buku.unduh', ['buku' => 'b1f0e8a2-6c1d-4c55-9a43-0d7c2f5e1a01']))->assertNotFound();
    }
}
