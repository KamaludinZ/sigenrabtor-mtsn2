<?php

namespace Tests\Feature\Cetak;

use App\Contracts\KegiatanRepository;
use App\Contracts\PembuatPdfDokumen;
use App\Contracts\PengubahPdf;
use App\Support\Dokumen\CetakDokumen;
use App\Support\Dokumen\GagalUbahPdf;
use App\Support\Dokumen\StatusCetak;
use App\Support\Stub\StubPembuatPdfDokumen;
use Tests\TestCase;

class UnduhPdfDokumenTest extends TestCase
{
    /** Jumlah halaman PDF dari objek /Type /Page. */
    private function halaman(string $pdf): int
    {
        return preg_match_all('#/Type /Page[^s]#', $pdf);
    }

    public function test_unduh_pdf_set_dokumen_dengan_nama_baku(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(2);
        $nama = CetakDokumen::namaBerkas(StatusCetak::dari($kegiatan, 'DIPA'), ['cover', 'kak', 'rab']);

        $respons = $this->get(route('kegiatan.cetak.pdf', ['kegiatan' => 2, 'sumber' => 'DIPA']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Nama-Berkas', $nama)
            ->assertHeader('Content-Disposition', 'attachment; filename="'.$nama.'"')
            ->assertHeader('Cache-Control', 'no-store, private');
        $isi = $respons->getContent();
        $this->assertStringStartsWith('%PDF-', $isi);
        $this->assertSame((string) strlen($isi), $respons->headers->get('Content-Length'));
        $this->assertGreaterThanOrEqual(3, $this->halaman($isi), 'Tiap lembar mulai di halaman baru.');
        $this->assertStringContainsString('DRAF', $isi);
        $this->assertStringContainsString('TOTAL KESELURUHAN', $isi);

        $rab = $this->get(route('kegiatan.cetak.pdf', ['kegiatan' => 2, 'sumber' => 'DIPA', 'dokumen' => 'rab']))->assertOk();
        $this->assertStringStartsWith('RAB_', $rab->headers->get('X-Nama-Berkas'));
        $this->assertSame(1, $this->halaman($rab->getContent()));
        $this->assertSame(1, $this->halaman((new StubPembuatPdfDokumen)->buat($kegiatan, 'DIPA', ['cover'])));
    }

    public function test_akses_sumber_dan_kegagalan(): void
    {
        $this->get(route('kegiatan.cetak.pdf', ['kegiatan' => 1, 'sumber' => 'DIPA']))->assertNotFound();
        $this->get(route('kegiatan.cetak.pdf', 999))->assertNotFound();
        $lain = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Kegiatan Leading Lain', 'leading' => '1.2', 'user_id' => 3, 'status' => 'draf']);
        $this->get(route('kegiatan.cetak.pdf', $lain))->assertForbidden();

        $this->app->instance(PembuatPdfDokumen::class, new class implements PembuatPdfDokumen
        {
            public function buat(array $kegiatan, ?string $sumber, array $lembar): string
            {
                throw new GagalUbahPdf('Penyusun PDF sedang sibuk — coba lagi.');
            }
        });
        $this->getJson(route('kegiatan.cetak.pdf', ['kegiatan' => 1, 'dokumen' => 'kak']))
            ->assertStatus(503)
            ->assertJsonPath('pesan', 'Penyusun PDF sedang sibuk — coba lagi.')
            ->assertJsonPath('tautan_cetak', CetakDokumen::tautan(1, 'Komite', ['kak'], otomatis: true));
        $this->get(route('kegiatan.cetak.pdf', 1))
            ->assertRedirect(route('cetak.index', ['pilih' => '1|Komite']))
            ->assertSessionHas('notify', ['message' => 'Penyusun PDF sedang sibuk — coba lagi.', 'type' => 'error']);
    }

    public function test_rab_pdf_gagal_menjawab_json_untuk_klien(): void
    {
        $this->app->instance(PengubahPdf::class, new class implements PengubahPdf
        {
            public function ubah(string $berkas): string
            {
                throw new GagalUbahPdf('LibreOffice tidak tersedia di server.');
            }
        });
        $this->getJson(route('kegiatan.rab.pdf', ['kegiatan' => 2, 'sumber' => 'DIPA']))
            ->assertStatus(503)->assertJsonPath('pesan', 'LibreOffice tidak tersedia di server.');
        $this->get(route('kegiatan.rab.pdf', ['kegiatan' => 2, 'sumber' => 'DIPA']))->assertRedirect();
        $this->assertSame(route('kegiatan.cetak.pdf', ['kegiatan' => 2, 'sumber' => 'DIPA', 'dokumen' => 'kak,rab']), CetakDokumen::tautanPdf(2, 'DIPA', ['kak', 'rab']));
    }
}
