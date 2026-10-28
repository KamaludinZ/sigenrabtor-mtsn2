<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Rab;
use App\Support\Rab\EksporRabExcel;
use App\Support\Rab\KalkulatorRab;
use App\Support\Stub\StubRab;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class EksporRabExcelTest extends TestCase
{
    /** @return list<list<mixed>> Baris lembar yang berisi (nilai mentah). */
    private static function isi($lembar): array
    {
        return array_values(array_filter($lembar->toArray(null, false, false, false), fn (array $r) => array_filter($r, fn ($v) => $v !== null && $v !== '') !== []));
    }

    public function test_rab_satu_sumber_dana_seperti_dokumen(): void
    {
        ['buku' => $buku, 'nama' => $nama] = EksporRabExcel::buat(app(KegiatanRepository::class)->temukan(2), 'DIPA');

        $this->assertSame('RAB_Supervisi_Akademik_dan_Refleksi_Pembelajaran_DIPA_2026_DRAF.xlsx', $nama);
        $this->assertSame(['RAB DIPA'], $buku->getSheetNames());
        $baris = self::isi($buku->getSheet(0));
        $this->assertSame('Rencana Anggaran Biaya Kegiatan Supervisi Akademik dan Refleksi Pembelajaran MTsN 2 Kota Malang Tahun 2026', $baris[0][0]);
        $this->assertStringStartsWith('DRAF — ', $baris[1][0]);
        $this->assertContains(['Jumlah yang diajukan', null, 'Rp5.400.000,- (Lima Juta Empat Ratus Ribu Rupiah)', 'DIPA MTsN 2 Kota Malang Tahun 2026', null, null, null], $baris);

        $kepala = array_search(['No', 'Kode', 'Uraian', 'Vol', 'Satuan', 'Harga Satuan (Rp)', 'Jumlah (Rp)'], $baris, true);
        $rincian = StubRab::rincian(2, 'DIPA');
        $pertama = $baris[$kepala + 1];
        $this->assertSame([1, '521211', $rincian[0]['uraian'], 60, 'eksemplar', 7_500, 450_000], $pertama, 'Kode berupa teks, uang berupa bilangan.');
        $total = $baris[$kepala + count($rincian) + 1];
        $this->assertSame(['TOTAL KESELURUHAN', KalkulatorRab::hitung($rincian)->total], [$total[0], $total[6]]);
        $this->assertSame('Terbilang: Lima Juta Empat Ratus Ribu Rupiah', $baris[$kepala + count($rincian) + 2][0]);
        $lembar = $buku->getSheet(0);
        $barisTotal = collect(range(1, $lembar->getHighestRow()))->first(fn (int $r) => $lembar->getCell("A{$r}")->getValue() === 'TOTAL KESELURUHAN');
        $this->assertSame(['#,##0', '#,##0'], [$lembar->getStyle("G{$barisTotal}")->getNumberFormat()->getFormatCode(), $lembar->getStyle('F'.($barisTotal - 1))->getNumberFormat()->getFormatCode()]);
    }

    public function test_semua_sumber_termin_dan_unduhan(): void
    {
        ['buku' => $buku, 'nama' => $nama] = EksporRabExcel::buat(app(KegiatanRepository::class)->temukan(1));
        $this->assertSame(['RAB Komite'], $buku->getSheetNames());
        $this->assertSame('RAB_Workshop_Penyusunan_Kurikulum_Madrasah_dan_Perangkat_Pembela_2026_DRAF.xlsx', $nama, 'Nama kegiatan dibatasi 60 karakter seperti StatusCetak.');
        $teks = array_merge([], ...self::isi($buku->getSheet(0)));
        $this->assertContains('Mekanisme Pembayaran', $teks, 'Contoh 1 dibayar dua termin.');

        $respons = $this->get(route('kegiatan.rab.excel', ['kegiatan' => 2]))->assertOk()->assertDownload('RAB_Supervisi_Akademik_dan_Refleksi_Pembelajaran_2026_DRAF.xlsx');
        $berkas = tempnam(sys_get_temp_dir(), 'rab').'.xlsx';
        file_put_contents($berkas, $respons->streamedContent());
        $this->assertSame(['RAB DIPA', 'RAB Komite'], IOFactory::load($berkas)->getSheetNames());

        $this->get(route('kegiatan.rab.excel', ['kegiatan' => 2, 'sumber' => 'BOS']))->assertNotFound();
        $this->get(route('kegiatan.rab.excel', ['kegiatan' => 1, 'sumber' => 'DIPA']))->assertNotFound();
        $this->get(route('kegiatan.rab.excel', ['kegiatan' => 999]))->assertNotFound();
        $lain = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Kegiatan Leading Lain', 'leading' => '1.2', 'user_id' => 2, 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 1_000_000]]]);
        $this->get(route('kegiatan.rab.excel', ['kegiatan' => $lain]))->assertForbidden();
        $tanpaSumber = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Kegiatan Tanpa Sumber', 'leading' => '1.6']);
        $this->get(route('kegiatan.rab.excel', ['kegiatan' => $tanpaSumber]))->assertNotFound();

        Livewire::test(Rab::class, ['kegiatan' => 2])->assertSeeHtml('data-testid="unduh-rab-excel"')
            ->assertSeeHtml('href="'.e(route('kegiatan.rab.excel', ['kegiatan' => 2, 'sumber' => 'DIPA'])).'"');
    }
}
