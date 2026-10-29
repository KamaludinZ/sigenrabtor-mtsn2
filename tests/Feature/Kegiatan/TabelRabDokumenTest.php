<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\SusunanRab;
use App\Support\Dokumen\TabelRab;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\AnggaranSumber;
use App\Support\Rab\EksporRabExcel;
use App\Support\Rab\KalkulatorRab;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class TabelRabDokumenTest extends TestCase
{
    private function anggaran(int $kegiatan, string $sumber): AnggaranSumber
    {
        return AnggaranKegiatan::perSumber(app(KegiatanRepository::class)->temukan($kegiatan))[$sumber];
    }

    public function test_struktur_kolom_baris_total_dan_terbilang(): void
    {
        $anggaran = $this->anggaran(2, 'DIPA');
        $tabel = TabelRab::dari($anggaran);

        $this->assertSame('DIPA', $tabel->sumber);
        $this->assertSame(['no', 'kode', 'uraian', 'volume', 'satuan', 'harga', 'jumlah'], $tabel->kolom);
        $this->assertSame(['No', 'Kode', 'Uraian', 'Vol', 'Satuan', 'Harga Satuan (Rp)', 'Jumlah (Rp)'], $tabel->judul());
        $this->assertCount(count($anggaran->hasil->rincian), $tabel->baris);
        $this->assertSame([1, 2, 3], array_slice(array_column($tabel->baris, 'nomor'), 0, 3));
        $honor = $tabel->baris[2];
        $this->assertSame(['no' => '3.', 'kode' => '521213', 'uraian' => 'Honorarium tim supervisor (6 orang x 4 kali)'], array_slice($honor['teks'], 0, 3));
        $this->assertSame(array_sum(array_column(array_column($tabel->baris, 'nilai'), 'jumlah')), $tabel->total, 'Total = jumlah baris (KalkulatorRab).');
        $this->assertSame([5_400_000, '5.400.000', 'Lima Juta Empat Ratus Ribu Rupiah'], [$tabel->total, $tabel->totalTeks, $tabel->terbilang]);
        $this->assertSame($anggaran->hasil->rincian[0]['id'], $tabel->baris[0]['id']);

        // Lebar kolom: persen lebar isi A4, Uraian mengambil sisa.
        $lebar = $tabel->lebar();
        $this->assertNull($lebar['uraian']);
        $this->assertEqualsWithDelta(10 / TabelRab::LEBAR_ISI * 100, $lebar['no'], 0.01);
        $this->assertLessThan(100, array_sum(array_filter($lebar)));

        // Volume pecahan & tanpa kode: kolom Kode hilang (bisa dipaksa tampil).
        $pecahan = TabelRab::dari(new AnggaranSumber('Komite', KalkulatorRab::hitung([
            ['kode' => '', 'uraian' => 'Bensin', 'kategori' => 'lain', 'volume' => 2.5, 'satuan' => 'liter', 'harga_satuan' => 10_000],
        ])));
        $this->assertSame(['no', 'uraian', 'volume', 'satuan', 'harga', 'jumlah'], $pecahan->kolom);
        $this->assertSame(['volume' => '2,5', 'harga' => '10.000', 'jumlah' => '25.000'], array_intersect_key($pecahan->baris[0]['teks'], array_flip(['volume', 'harga', 'jumlah'])));
        $this->assertSame([2.5, 25_000], [$pecahan->baris[0]['nilai']['volume'], $pecahan->baris[0]['nilai']['jumlah']]);
        $this->assertContains('kode', TabelRab::dari(new AnggaranSumber('Komite', KalkulatorRab::hitung([])), kode: true)->kolom);

        // Tanpa rincian / tanpa sumber dana: tabel kosong, total 0, tanpa terbilang.
        $kosong = TabelRab::dari(null);
        $this->assertSame([true, null, 0, '0', null], [$kosong->kosong(), $kosong->sumber, $kosong->total, $kosong->totalTeks, $kosong->terbilang]);
    }

    public function test_komponen_tabel_rab_dokumen(): void
    {
        $tabel = SusunanRab::dari(app(KegiatanRepository::class)->temukan(2), 'DIPA')->tabel();
        $html = Blade::render('<x-dokumen.tabel-rab :tabel="$tabel" />', ['tabel' => $tabel]);

        $this->assertStringContainsString('data-testid="tabel-rab-dipa" data-jumlah-baris="'.count($tabel->baris).'"', $html);
        $this->assertStringContainsString('<caption class="sr-only">Rincian belanja RAB DIPA</caption>', $html);
        $this->assertSame(7, substr_count($html, '<col data-kolom='));
        $this->assertMatchesRegularExpression('/<col data-kolom="uraian"\s*>/', $html, 'Uraian tanpa lebar tetap (sisa lebar).');
        $this->assertStringContainsString('[display:table-header-group]', $html, 'Kepala tabel berulang di tiap halaman cetak.');
        foreach (['No', 'Kode', 'Uraian', 'Vol', 'Satuan', 'Harga Satuan (Rp)', 'Jumlah (Rp)'] as $judul) {
            $this->assertStringContainsString(">{$judul}</th>", $html);
        }
        $this->assertStringContainsString('id="cetak-rab-baris-1"', $html);
        $this->assertStringContainsString('data-baris="'.$tabel->baris[0]['id'].'"', $html);
        $this->assertMatchesRegularExpression('/data-kolom="no">3\.<\/td>\s*<td[^>]*data-kolom="kode">521213<\/td>\s*<td[^>]*data-kolom="uraian">Honorarium tim supervisor/', $html);
        $this->assertStringContainsString('id="cetak-rab-total"', $html);
        $this->assertStringContainsString('data-testid="total-tabel-rab">5.400.000</td>', $html);
        $this->assertStringContainsString('Terbilang: Lima Juta Empat Ratus Ribu Rupiah', $html);
        $this->assertStringContainsString('>TOTAL KESELURUHAN</th>', $html);

        $kosong = Blade::render('<x-dokumen.tabel-rab :tabel="$tabel" />', ['tabel' => TabelRab::dari(new AnggaranSumber('Komite', KalkulatorRab::hitung([])))]);
        $this->assertStringContainsString('Belum ada rincian belanja RAB Komite.', $kosong);
        $this->assertStringNotContainsString('terbilang-tabel-rab', $kosong);

        // Dokumen RAB memakai komponen ini.
        $this->assertStringContainsString('data-testid="tabel-rab-dipa" data-jumlah-baris=', Blade::render('<x-dokumen.rab :susunan="$s" />', ['s' => SusunanRab::dari(app(KegiatanRepository::class)->temukan(2), 'DIPA')]));
    }

    public function test_tabel_rab_siap_cetak_berhalaman_banyak(): void
    {
        $rincian = array_map(fn (int $i) => ['kode' => '521211', 'kategori' => 'bahan', 'uraian' => "Belanja bahan rapat ke-{$i} dengan keterangan cukup panjang", 'volume' => 2, 'satuan' => 'paket', 'harga_satuan' => 1_250_000], range(1, 60));
        $tabel = TabelRab::dari(new AnggaranSumber('DIPA', KalkulatorRab::hitung($rincian)));
        $html = Blade::render('<x-dokumen.tabel-rab :tabel="$tabel" />', ['tabel' => $tabel]);

        $this->assertSame(61, substr_count($html, 'class="break-inside-avoid scroll-mt-40'), 'Tiap baris (60 rincian + total) tidak terbelah antarhalaman.');
        $this->assertStringContainsString('[display:table-header-group] [print-color-adjust:exact]', $html, 'Kepala tabel berulang & latarnya tercetak.');
        $this->assertMatchesRegularExpression('#<tfoot class="break-inside-avoid font-bold \[display:table-row-group\]" data-testid="kaki-tabel-rab">#', $html, 'Total & terbilang sekali di akhir, bukan di kaki tiap halaman.');
        $this->assertSame(1, substr_count($html, 'TOTAL KESELURUHAN'));
        $this->assertMatchesRegularExpression('#whitespace-nowrap" data-kolom="jumlah">2.500.000<#', $html);

        $rab = Blade::render('<x-dokumen.rab :susunan="$s" />', ['s' => SusunanRab::dari(app(KegiatanRepository::class)->temukan(2), 'DIPA')]);
        $this->assertStringContainsString('cetak:overflow-visible', $rab, 'Wadah tabel bukan wadah gulir di lembar cetak.');
        $this->assertStringContainsString('min-w-[34rem] font-sans cetak:min-w-0', $rab);
    }

    public function test_ekspor_excel_memakai_struktur_yang_sama(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(2);
        $tabel = SusunanRab::dari($kegiatan, 'DIPA')->tabel();
        $lembar = EksporRabExcel::buat($kegiatan, 'DIPA')['buku']->getActiveSheet();
        // Jumlah & total berupa rumus (ROUND/SUM): dibandingkan dengan hasil hitungnya.
        $isi = array_map(fn (array $r) => array_map(fn ($v) => is_float($v) && floor($v) === $v ? (int) $v : $v, $r), $lembar->toArray(null, true, false));
        $kepala = collect($isi)->search(fn (array $b) => $b[0] === 'No');

        $this->assertSame($tabel->judul(), array_slice($isi[$kepala], 0, count($tabel->kolom)));
        foreach ($tabel->baris as $i => $baris) {
            $this->assertSame(array_values($baris['nilai']), array_slice($isi[$kepala + 1 + $i], 0, count($tabel->kolom)), "Baris {$baris['nomor']}");
        }
        $total = $isi[$kepala + 1 + count($tabel->baris)];
        $this->assertSame([TabelRab::LABEL_TOTAL, $tabel->total], [$total[0], $total[count($tabel->kolom) - 1]]);
        $this->assertSame('Terbilang: '.$tabel->terbilang, $isi[$kepala + 2 + count($tabel->baris)][0]);
    }
}
