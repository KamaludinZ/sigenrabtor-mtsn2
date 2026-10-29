<?php

namespace Tests\Feature\Cetak;

use App\Contracts\GeneratorBerkasDokumen;
use App\Contracts\KegiatanRepository;
use App\Contracts\PembuatPdfDokumen;
use App\Support\Dokumen\CetakDokumen;
use App\Support\Dokumen\DokumenKegiatan;
use App\Support\Dokumen\GagalUbahPdf;
use App\Support\Dokumen\Generasi\FormatBerkas;
use App\Support\Dokumen\Generasi\GagalBuatBerkas;
use App\Support\Dokumen\Generasi\GeneratorBerkasServer;
use App\Support\Dokumen\Generasi\KatalogBerkas;
use App\Support\Dokumen\Generasi\PermintaanBerkas;
use App\Support\Dokumen\StatusCetak;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class GenerasiDokumenServerTest extends TestCase
{
    private function kegiatan(int $id): array
    {
        return app(KegiatanRepository::class)->temukan($id);
    }

    /** @return array<string, string> */
    private function galat(array $kegiatan, array $isian): array
    {
        try {
            PermintaanBerkas::dari($kegiatan, $isian);
        } catch (ValidationException $e) {
            return array_map(fn (array $p) => $p[0], $e->errors());
        }

        return [];
    }

    public function test_format_dan_kecocokan_jenis(): void
    {
        $this->assertSame(array_keys(DokumenKegiatan::JENIS), array_keys(FormatBerkas::LEMBAR_JENIS), 'Setiap jenis punya lembar berkas.');
        $this->assertSame(['pdf'], FormatBerkas::untukJenis('lengkap'));
        $this->assertSame(['pdf', 'docx'], FormatBerkas::untukJenis('KAK+TOR'));
        $this->assertSame(['pdf', 'xlsx'], FormatBerkas::untukJenis('RAB'));
        $this->assertSame('Rencana Anggaran Biaya tidak bisa dijadikan berkas Word — pilih PDF atau Excel.', str_replace(DokumenKegiatan::JENIS['RAB'], 'Rencana Anggaran Biaya', (string) FormatBerkas::galat('RAB', 'docx')));
        $this->assertNull(FormatBerkas::galat('TOR', 'docx'));
        $this->assertSame(['cover', 'kak'], FormatBerkas::lembar('lengkap', null), 'Tanpa sumber dana RAB tidak ikut.');
    }

    public function test_permintaan_dirapikan_dan_divalidasi(): void
    {
        $k = $this->kegiatan(2);
        $p = PermintaanBerkas::dari($k, ['format' => ' XLSX ']);
        $this->assertSame(['kegiatan_id' => 2, 'jenis' => 'RAB', 'sumber' => 'DIPA', 'format' => 'xlsx', 'lembar' => ['rab']], $p->toArray());
        $this->assertSame(['KAK+TOR', ['cover', 'kak']], [PermintaanBerkas::dari($k, ['format' => 'docx'])->jenis, PermintaanBerkas::dari($k, ['format' => 'docx'])->lembar]);
        $this->assertSame(['lengkap', 'Komite'], [PermintaanBerkas::dari($k, ['format' => 'pdf', 'sumber' => 'Komite'])->jenis, PermintaanBerkas::dari($k, ['format' => 'pdf', 'sumber' => 'Komite'])->sumber]);

        $this->assertArrayHasKey('format', $this->galat($k, []));
        $this->assertArrayHasKey('format', $this->galat($k, ['format' => 'odt']));
        $this->assertArrayHasKey('jenis', $this->galat($k, ['format' => 'pdf', 'jenis' => 'SPJ']));
        $this->assertStringContainsString('tidak bisa dijadikan berkas Word', $this->galat($k, ['format' => 'docx', 'jenis' => 'RAB'])['format']);
        $this->assertSame('Sumber dana BOS tidak dipilih untuk kegiatan ini.', $this->galat($k, ['format' => 'pdf', 'sumber' => 'BOS'])['sumber']);

        $tanpa = ['id' => 99, 'nama_kegiatan' => 'Tanpa Sumber', 'sumber_dana' => []];
        $this->assertSame(['KAK+TOR', null, ['cover', 'kak']], [PermintaanBerkas::dari($tanpa, ['format' => 'pdf'])->jenis, PermintaanBerkas::dari($tanpa, ['format' => 'pdf'])->sumber, PermintaanBerkas::dari($tanpa, ['format' => 'pdf'])->lembar]);
        $this->assertArrayHasKey('sumber', $this->galat($tanpa, ['format' => 'xlsx']));
        $this->assertArrayHasKey('sumber', $this->galat($tanpa, ['format' => 'pdf', 'jenis' => 'lengkap']));
    }

    public function test_generator_server_pdf_excel_dan_word_di_peramban(): void
    {
        $generator = app(GeneratorBerkasDokumen::class);
        $this->assertInstanceOf(GeneratorBerkasServer::class, $generator);
        $this->assertSame([true, false, true], [$generator->mendukung('pdf'), $generator->mendukung('docx'), $generator->mendukung('xlsx')]);
        $k = $this->kegiatan(2);

        $pdf = $generator->buat(PermintaanBerkas::dari($k, ['format' => 'pdf', 'jenis' => 'RAB', 'sumber' => 'DIPA']));
        $this->assertStringStartsWith('%PDF-', $pdf->isi);
        $this->assertSame(CetakDokumen::namaBerkas(StatusCetak::dari($k, 'DIPA'), ['rab']), $pdf->nama);
        $this->assertTrue($pdf->draf);
        $this->assertSame('1', $pdf->header()['X-Draf']);

        $xlsx = $generator->buat(PermintaanBerkas::dari($k, ['format' => 'xlsx', 'sumber' => 'DIPA']));
        $this->assertStringStartsWith('PK', $xlsx->isi);
        $this->assertSame(StatusCetak::dari($k, 'DIPA')->namaBerkas('RAB', 'xlsx'), $xlsx->nama);
        $sementara = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($sementara, $xlsx->isi);
        $this->assertSame(['RAB DIPA'], IOFactory::load($sementara)->getSheetNames(), 'Satu lembar untuk sumber dana permintaan.');
        unlink($sementara);

        try {
            $generator->buat(PermintaanBerkas::dari($k, ['format' => 'docx']));
            $this->fail('Word belum dibuat server.');
        } catch (GagalBuatBerkas $e) {
            $this->assertSame(['tidak_didukung', 501], [$e->jenis, $e->status()]);
            $this->assertStringContainsString('disusun di peramban', $e->getMessage());
            $this->assertStringContainsString('/kegiatan/2/cetak', (string) $e->tautanCetak);
        }
        $this->assertStringEndsWith('.docx', GeneratorBerkasServer::nama(PermintaanBerkas::dari($k, ['format' => 'docx'])));
    }

    public function test_katalog_berkas(): void
    {
        $katalog = KatalogBerkas::untuk($this->kegiatan(2));
        $kunci = array_map(fn (array $b) => $b['jenis'].'|'.$b['sumber'].'|'.$b['format'], $katalog);
        foreach (['lengkap|DIPA|pdf', 'lengkap|Komite|pdf', 'RAB|DIPA|xlsx', 'RAB|Komite|pdf', 'KAK+TOR|DIPA|docx', 'TOR|DIPA|pdf'] as $ada) {
            $this->assertContains($ada, $kunci);
        }
        $this->assertNotContains('KAK+TOR|Komite|pdf', $kunci, 'Jenis KAK/TOR hanya pada sumber pertama.');
        $this->assertNotContains('lengkap|DIPA|docx', $kunci);
        $word = collect($katalog)->first(fn (array $b) => $b['format'] === 'docx');
        $this->assertSame([false, true], [$word['server'], $word['klien']]);
        $this->assertSame(route('kegiatan.berkas.unduh', ['kegiatan' => 2, 'jenis' => 'KAK+TOR', 'sumber' => 'DIPA', 'format' => 'docx']), $word['tautan']);
        $this->assertSame(['pdf', 'docx', 'xlsx'], array_keys(KatalogBerkas::format()));

        $this->getJson(route('kegiatan.berkas', ['kegiatan' => 2]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('kegiatan_id', 2)
            ->assertJsonPath('format.docx.server', false)
            ->assertJsonPath('format.xlsx.perlu_sumber', true)
            ->assertJsonCount(count($katalog), 'berkas');
        $this->getJson(route('kegiatan.berkas', ['kegiatan' => 999]))->assertNotFound();
    }

    public function test_endpoint_unduh_berkas(): void
    {
        $k = $this->kegiatan(2);
        $nama = StatusCetak::dari($k, 'DIPA')->namaBerkas('RAB', 'xlsx');
        $this->get(route('kegiatan.berkas.unduh', ['kegiatan' => 2, 'format' => 'xlsx', 'sumber' => 'DIPA']))
            ->assertOk()
            ->assertHeader('Content-Type', FormatBerkas::mime('xlsx'))
            ->assertHeader('X-Nama-Berkas', $nama)
            ->assertHeader('X-Format-Berkas', 'xlsx')
            ->assertHeader('X-Draf', '1')
            ->assertHeader('Content-Disposition', 'attachment; filename="'.$nama.'"; filename*=UTF-8\'\''.rawurlencode($nama))
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('kegiatan.berkas.unduh', ['kegiatan' => 2, 'format' => 'pdf', 'jenis' => 'KAK']))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->getJson(route('kegiatan.berkas.unduh', ['kegiatan' => 2, 'format' => 'docx', 'jenis' => 'RAB']))
            ->assertStatus(422)->assertJsonStructure(['pesan', 'galat' => ['format']]);
        $this->get(route('kegiatan.berkas.unduh', ['kegiatan' => 2, 'format' => 'odt']))
            ->assertRedirect(route('cetak.index'))->assertSessionHas('notify.type', 'error');
        $this->getJson(route('kegiatan.berkas.unduh', ['kegiatan' => 2, 'format' => 'docx']))
            ->assertStatus(501)->assertJsonPath('jenis', 'tidak_didukung')->assertJsonPath('klien', true);

        $this->app->instance(PembuatPdfDokumen::class, new class implements PembuatPdfDokumen
        {
            public function buat(array $kegiatan, ?string $sumber, array $lembar): string
            {
                throw new GagalUbahPdf('Perender PDF sedang tidak tersedia.');
            }
        });
        $this->getJson(route('kegiatan.berkas.unduh', ['kegiatan' => 2, 'format' => 'pdf']))
            ->assertStatus(503)->assertJsonPath('jenis', 'gagal')->assertJsonPath('pesan', 'Perender PDF sedang tidak tersedia.')
            ->assertJsonPath('tautan_cetak', CetakDokumen::tautan(2, 'DIPA', ['cover', 'kak', 'rab']));

        $lain = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Kegiatan Leading Lain', 'leading' => '1.2', 'user_id' => 2]);
        $this->getJson(route('kegiatan.berkas.unduh', ['kegiatan' => $lain, 'format' => 'pdf']))->assertForbidden();
        $this->getJson(route('kegiatan.berkas', ['kegiatan' => $lain]))->assertForbidden();
        $this->getJson(route('kegiatan.berkas.unduh', ['kegiatan' => 999, 'format' => 'pdf']))->assertNotFound();
    }
}
