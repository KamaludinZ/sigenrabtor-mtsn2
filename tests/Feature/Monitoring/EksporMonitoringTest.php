<?php

namespace Tests\Feature\Monitoring;

use App\Contracts\KegiatanRepository;
use App\Livewire\Monitoring\Anggaran;
use App\Support\Monitoring\EksporExcel;
use App\Support\Monitoring\RingkasanAnggaran;
use App\Support\Monitoring\SaringanMonitoring;
use App\Support\Stub\StubPengaturan;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class EksporMonitoringTest extends TestCase
{
    private function buku(array $saringan = [], string $urut = 'total'): Spreadsheet
    {
        $s = SaringanMonitoring::dari($saringan);

        return EksporExcel::buat(RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), $s->untukRingkasan()), $s, StubPengaturan::pengguna(), $urut);
    }

    /**
     * @return list<list<mixed>>
     */
    private function isi(Spreadsheet $buku, string $lembar): array
    {
        return $buku->getSheetByName($lembar)->toArray(null, false, false, false);
    }

    public function test_hanya_admin_dan_berkas_xlsx_yang_sah(): void
    {
        $this->get(route('monitoring.ekspor.excel'))->assertForbidden();

        $respons = $this->withSession(['stub_role' => 'admin'])->get(route('monitoring.ekspor.excel', ['leading' => '1.6', 'sumber' => 'Komite']));
        $respons->assertOk();
        $this->assertStringContainsString('monitoring-anggaran-TA2026-leading-1.6-komite.xlsx', $respons->headers->get('content-disposition'));

        $berkas = tempnam(sys_get_temp_dir(), 'ekspor').'.xlsx';
        file_put_contents($berkas, $respons->streamedContent());
        $buku = IOFactory::load($berkas);
        unlink($berkas);
        $this->assertSame(EksporExcel::LEMBAR, $buku->getSheetNames());
        $this->assertSame('Saringan: Tim Penjamin Mutu · Dana Komite', $buku->getSheet(0)->getCell('A3')->getValue());
        $this->assertSame(['Komite', 20_000_000, 20_450_000, -450_000, 102.3], array_slice($buku->getSheet(0)->rangeToArray('A7:H7', null, false, false)[0], 0, 5), 'Hanya sumber dana yang disaring.');
        $this->assertSame('Jumlah', $buku->getSheet(0)->getCell('A8')->getValue());
        $this->assertSame(['Kode', 'Leading', 'Kegiatan', 'Pagu Komite', 'Total RAB Komite', 'Selisih Komite', 'Pagu jumlah'], array_slice($buku->getSheetByName('Per Leading')->rangeToArray('A1:G1')[0], 0, 7));

        $this->assertSame('monitoring-anggaran-TA2026.xlsx', EksporExcel::namaBerkas(SaringanMonitoring::dari(['sumber' => 'BOS', 'tahun' => '1999'])), 'Saringan tidak dikenal dilepas.');
    }

    public function test_isi_lembar_ringkasan_leading_rincian_kelompok_operator(): void
    {
        $buku = $this->buku();

        $ringkasan = $this->isi($buku, 'Ringkasan');
        $this->assertSame('Tahun Anggaran 2026', $ringkasan[1][0]);
        $this->assertSame('Saringan: semua kegiatan', $ringkasan[2][0]);
        $this->assertSame(['Sumber dana', 'Pagu', 'Total RAB', 'Selisih', 'Pemakaian (%)', 'Jumlah RAB', 'RAB melebihi pagu', 'RAB belum berpagu'], $ringkasan[5]);
        $this->assertSame(['DIPA', 6_000_000, 6_300_000, null, null, 2, 0, 1], $ringkasan[6], 'Ada RAB tanpa pagu → selisih kosong.');
        $this->assertSame(['Komite', 20_000_000, 20_450_000, -450_000, 102.3, 3, 1, 0], $ringkasan[7]);
        $this->assertSame(['Jumlah', 26_000_000, 26_750_000, null, null, 5, 1, 1], $ringkasan[8]);
        $this->assertSame('#,##0;[Red]-#,##0', $buku->getSheetByName('Ringkasan')->getStyle('B7')->getNumberFormat()->getFormatCode());

        $leading = $this->isi($buku, 'Per Leading');
        $this->assertSame(['Kode', 'Leading', 'Kegiatan', 'Pagu DIPA', 'Total RAB DIPA', 'Selisih DIPA', 'Pagu Komite', 'Total RAB Komite', 'Selisih Komite', 'Pagu jumlah', 'Total RAB jumlah', 'Selisih jumlah', 'Keterangan'], $leading[0]);
        $this->assertCount(10, $leading, 'Kepala + 8 leading + jumlah.');
        $mutu = $leading[6];
        $this->assertSame(['1.6', 'Tim Penjamin Mutu', 3, 6_000_000, 6_300_000, null, 20_000_000, 20_450_000, -450_000], array_slice($mutu, 0, 9));
        $this->assertSame('1 RAB belum berpagu (selisih tidak dihitung); 1 RAB melebihi pagunya', $mutu[12]);
        $this->assertSame(['1.1', 'Waka Kurikulum', 0, 0, 0, null], array_slice($leading[1], 0, 6));
        $this->assertSame('Belum ada RAB', $leading[1][12]);
        $this->assertSame(DataType::TYPE_STRING, $buku->getSheetByName('Per Leading')->getCell('A7')->getDataType(), 'Kode leading tetap teks.');

        $rincian = $this->isi($buku, 'Rincian Kegiatan');
        $this->assertCount(7, $rincian, 'Kepala + 5 RAB + jumlah.');
        $this->assertSame([1, 'Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran', '6.17.12', '1.6 Tim Penjamin Mutu', 'Draf', 'Komite', 10_000_000, 10_000_000, 0, 100.0, 'Sesuai pagu', 15], $rincian[1]);
        $this->assertSame([2, 'Review RKJM dan Penyusunan RKTM', 'DIPA', null, 900_000, null, null, 'Pagu belum diisi'], [$rincian[2][0], $rincian[2][1], $rincian[2][5], $rincian[2][6], $rincian[2][7], $rincian[2][8], $rincian[2][9], $rincian[2][10]]);
        $this->assertSame(['Komite', 'Melebihi pagu Rp450.000'], [$rincian[3][5], $rincian[3][10]]);
        $this->assertSame(['Jumlah 3 kegiatan', '5 RAB', 26_000_000, 26_750_000, '1 RAB melebihi pagunya'], [$rincian[6][1], $rincian[6][5], $rincian[6][6], $rincian[6][7], $rincian[6][10]]);
        $this->assertSame(['Review RKJM dan Penyusunan RKTM'], array_values(array_unique(array_column(array_slice($this->isi($this->buku(urut: 'kelebihan'), 'Rincian Kegiatan'), 1, 2), 1))), 'Urutan mengikuti layar.');

        $kelompok = $this->isi($buku, 'Kelompok Belanja');
        $this->assertSame(['Kelompok belanja', 'DIPA', 'Komite', 'Total', 'Persen (%)', 'Jumlah item', 'Jumlah kegiatan'], $kelompok[0]);
        $this->assertSame(['Bahan', 2_100_000, 9_380_000, 11_480_000, 42.9, 20, 3], $kelompok[1]);
        $this->assertSame(['Jumlah', 6_300_000, 20_450_000, 26_750_000, 100, 33, 3], $kelompok[4]);
        $this->assertCount(5, $kelompok, 'Kepala + 3 kelompok + jumlah.');
        $kode = $this->isi($buku, 'Kode Akun');
        $this->assertSame(['Kode akun', 'Uraian', 'DIPA', 'Komite', 'Total'], $kode[0]);
        $this->assertSame(['521211', 'Belanja Bahan', 2_100_000, 9_380_000, 11_480_000], $kode[1]);
        $this->assertSame(['', 'Jumlah', 6_300_000, 20_450_000, 26_750_000], $kode[5]);

        $operator = $this->isi($buku, 'Operator');
        $this->assertSame(['Muslimin, M.Pd.', 'Leading', '1.6 Tim Penjamin Mutu', 2, 2], array_slice($operator[1], 0, 5));
        $this->assertSame(['Koordinator PUSKOM', 'Admin'], array_slice($operator[2], 0, 2));
        $this->assertSame('Jumlah', $operator[3][0]);
    }

    public function test_saringan_kosong_dan_tautan_tombol(): void
    {
        $rincian = $this->isi($this->buku(['status' => 'final']), 'Rincian Kegiatan');
        $this->assertSame('Tidak ada kegiatan yang cocok dengan saringan.', $rincian[1][1]);
        $this->assertSame('Jumlah 0 kegiatan', $rincian[2][1]);

        session(['stub_role' => 'admin']);
        Livewire::test(Anggaran::class)
            ->assertSeeHtml('href="'.route('monitoring.ekspor.excel').'"')
            ->set('sumber', 'Komite')
            ->call('urutkan', 'nama')
            ->call('urutkanLeading', 'komite_total')
            ->assertSeeHtml('href="'.e(route('monitoring.ekspor.excel', ['sumber' => 'Komite', 'urut' => 'nama', 'urut_leading' => 'komite_total'])).'"');
    }
}
