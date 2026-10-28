<?php

namespace Tests\Feature\Buku;

use App\Contracts\PenggabungPdf;
use App\Support\Dokumen\BukuDokumen;
use App\Support\Dokumen\ProsesPdfBuku;
use Tests\Support\PenggabungPdfPalsu;
use Tests\TestCase;

class ApiPdfBukuTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(PenggabungPdf::class, new PenggabungPdfPalsu);
    }

    public function test_picu_pantau_sampai_unduh(): void
    {
        $mulai = $this->postJson(route('buku.pdf.mulai'), ['leading' => '1.6', 'sumber' => 'DIPA'])->assertStatus(202)
            ->assertJson(['pesan' => 'Pembuatan PDF buku dimulai.', 'buku' => ['leading' => '1.6', 'nama_leading' => 'Tim Penjamin Mutu', 'sumber' => 'DIPA', 'jumlah_kegiatan' => 2, 'berjalan' => true, 'siap_unduh' => false, 'tautan_unduh' => null, 'pdf' => ['status' => 'antre', 'tahap' => 'antre', 'label_tahap' => ProsesPdfBuku::TAHAP['antre']]]])
            ->json('buku');
        $this->assertSame(route('buku.pdf.status', ['buku' => $mulai['id']]), $mulai['tautan_status']);
        $this->assertCount(count(ProsesPdfBuku::TAHAP), $mulai['langkah']);

        // Memicu lagi selama berjalan tidak membuat pekerjaan baru.
        $this->postJson(route('buku.pdf.mulai'), ['leading' => '1.6', 'sumber' => 'DIPA'])->assertStatus(409)->assertJsonPath('buku.id', $mulai['id']);

        $status = null;
        for ($i = 0; $i < count(ProsesPdfBuku::TAHAP) + 1 && ($status['berjalan'] ?? true); $i++) {
            $status = $this->getJson($mulai['tautan_status'])->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();
        }
        $this->assertSame(['selesai', false, true], [$status['pdf']['status'], $status['berjalan'], $status['siap_unduh']]);
        $this->assertSame(route('buku.unduh', ['buku' => $mulai['id']]), $status['tautan_unduh']);
        $this->assertSame(BukuDokumen::namaBerkas('1.6', 'DIPA'), $status['nama_berkas']);
        $this->get($status['tautan_unduh'])->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Sesudah selesai boleh dipicu ulang; riwayat menampilkan keduanya.
        $this->postJson(route('buku.pdf.mulai'), ['leading' => '1.6', 'sumber' => 'DIPA'])->assertStatus(202);
        $riwayat = $this->getJson(route('buku.riwayat', ['sumber' => 'DIPA']))->assertOk()->json();
        $this->assertSame(['antre', 'selesai'], array_column(array_column(array_slice(array_filter($riwayat['riwayat'], fn ($b) => $b['pdf'] !== null), 0, 2), 'pdf'), 'status'));
        $this->assertTrue(collect($this->getJson(route('buku.riwayat'))->json('riwayat'))->every(fn ($b) => $b['leading'] === '1.6'));
    }

    public function test_aturan_pemicu_dan_akses(): void
    {
        $this->postJson(route('buku.pdf.mulai'), ['leading' => '1.6', 'sumber' => 'BOS'])->assertStatus(422)->assertJsonPath('galat.sumber.0', 'Sumber dana harus DIPA atau Komite.');
        $this->postJson(route('buku.pdf.mulai'), ['leading' => '1.2', 'sumber' => 'DIPA'])->assertForbidden();
        $this->getJson(route('buku.pdf.status', ['buku' => '00000000-0000-4000-8000-000000000000']))->assertNotFound();

        $this->withSession(['stub_role' => 'admin']);
        $this->postJson(route('buku.pdf.mulai'), ['leading' => '1.2', 'sumber' => 'DIPA'])->assertStatus(422)->assertJsonPath('galat.buku.0', 'Buku Waka Kesiswaan DIPA belum berisi kegiatan.');
        $id = $this->postJson(route('buku.pdf.mulai'), ['leading' => '1.6', 'sumber' => 'Komite'])->assertStatus(202)->json('buku.id');

        $this->withSession(['stub_role' => 'leading']);
        $this->getJson(route('buku.pdf.status', ['buku' => $id]))->assertOk();
        $this->assertGreaterThan(0, $this->getJson(route('buku.riwayat', ['leading' => '1.6', 'sumber' => 'Komite']))->json('jumlah'));
    }
}
