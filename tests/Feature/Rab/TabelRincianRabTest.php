<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Form;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\AnggaranSumber;
use App\Support\Rab\KalkulatorRab;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

class TabelRincianRabTest extends TestCase
{
    private function anggaran(int $kegiatan, string $sumber): AnggaranSumber
    {
        return AnggaranKegiatan::perSumber(app(KegiatanRepository::class)->temukan($kegiatan))[$sumber];
    }

    private function render(AnggaranSumber $anggaran, array $atribut = []): string
    {
        $prop = collect($atribut)->map(fn ($nilai, $kunci) => ":{$kunci}=\"\${$kunci}\"")->implode(' ');

        return Blade::render("<x-rab.tabel-rincian :anggaran=\"\$anggaran\" {$prop} />", ['anggaran' => $anggaran] + $atribut);
    }

    public function test_satu_rab_dengan_kolom_baku_total_dan_terbilang_dari_angka_dokumen(): void
    {
        $html = $this->render($this->anggaran(2, 'DIPA'));

        $this->assertStringContainsString('data-testid="tabel-rab-dipa"', $html);
        $this->assertStringContainsString('<caption class="sr-only">Rincian belanja RAB DIPA</caption>', $html);
        foreach (['No', 'Kode', 'Uraian', 'Vol', 'Satuan', 'Harga Satuan (Rp)', 'Jumlah (Rp)'] as $judul) {
            $this->assertStringContainsString(">{$judul}</th>", $html);
        }
        $this->assertStringContainsString('Honorarium tim supervisor (6 orang x 4 kali)', $html);
        $this->assertStringContainsString('data-testid="total-tabel-rab">5.400.000</td>', $html);
        $this->assertStringContainsString('Terbilang: Lima Juta Empat Ratus Ribu Rupiah', $html);
        $this->assertStringNotContainsString('Komite', $html, 'Hanya satu RAB (sumber dana) yang tampil.');
    }

    public function test_kolom_kode_hanya_tampil_bila_ada_kode(): void
    {
        $tanpaKode = new AnggaranSumber('Komite', KalkulatorRab::hitung([
            ['kode' => '', 'uraian' => 'Snack', 'kategori' => 'bahan', 'volume' => 20, 'satuan' => 'kotak', 'harga_satuan' => 10_000],
        ]));

        $this->assertStringNotContainsString('>Kode</th>', $this->render($tanpaKode));
        $this->assertStringContainsString('>Kode</th>', $this->render($tanpaKode, ['kode' => true]));
    }

    public function test_pengelompokan_menampilkan_subtotal_dan_nomor_urut_cetak(): void
    {
        $html = $this->render($this->anggaran(2, 'DIPA'), ['kelompok' => 'kategori']);

        $this->assertStringContainsString('Bahan · 4 item', $html);
        $this->assertStringContainsString('Subtotal Bahan', $html);
        $this->assertStringContainsString('Subtotal Jasa', $html);
        $this->assertMatchesRegularExpression('/Subtotal Bahan<\/td>\s*<td[^>]*>1\.500\.000<\/td>/', $html);
        // Item honorarium tetap bernomor 3 (urutan cetak) walau dikelompokkan.
        $this->assertMatchesRegularExpression('/>3\.<\/td>\s*<td[^>]*>521213<\/td>\s*<td[^>]*>Honorarium tim supervisor/', $html);
    }

    public function test_volume_pecahan_dan_kartu_untuk_wadah_sempit(): void
    {
        $anggaran = new AnggaranSumber('DIPA', KalkulatorRab::hitung([
            ['kode' => '', 'uraian' => 'Bensin', 'kategori' => 'lain', 'volume' => 2.5, 'satuan' => 'liter', 'harga_satuan' => 10_000],
        ]));
        $html = $this->render($anggaran);

        $this->assertStringContainsString('>2,5</td>', $html);
        $this->assertStringContainsString('2,5 liter × Rp10.000', $html);
        $this->assertStringContainsString('Rp25.000,-', $html);
        $this->assertStringContainsString('@lg:hidden', $html);
    }

    public function test_gaya_dokumen_berbingkai_tanpa_kartu(): void
    {
        $html = $this->render($this->anggaran(1, 'Komite'), ['gaya' => 'dokumen']);

        $this->assertStringContainsString('border border-stone-400', $html);
        $this->assertStringNotContainsString('@lg:hidden', $html);
        $this->assertStringContainsString('data-testid="total-tabel-rab">10.000.000</td>', $html);
    }

    public function test_tanpa_rincian_menampilkan_keterangan(): void
    {
        $html = $this->render(new AnggaranSumber('Komite', KalkulatorRab::hitung([])));

        $this->assertStringContainsString('Belum ada rincian belanja RAB Komite.', $html);
        $this->assertStringNotContainsString('<table', $html);
    }

    public function test_data_kegiatan_menampilkan_rincian_rab_per_sumber(): void
    {
        Livewire::test(Form::class, ['kegiatan' => 2])
            ->assertSeeHtml('data-testid="rincian-rab-dipa"')
            ->assertSeeHtml('data-testid="rincian-rab-komite"')
            ->assertSee('Lihat rincian RAB DIPA')
            ->assertSeeHtml('data-testid="tabel-rab-dipa"')
            ->assertSeeHtml('data-testid="tabel-rab-komite"')
            ->assertSee('Piagam penghargaan guru terbaik')
            ->set('form.dana_komite', false)
            ->assertDontSeeHtml('data-testid="tabel-rab-komite"');

        Livewire::test(Form::class, ['kegiatan' => $this->idKegiatanBaruDenganDana()])
            ->assertDontSeeHtml('data-testid="rincian-rab-dipa"');
    }

    private function idKegiatanBaruDenganDana(): int
    {
        return app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Tanpa Rincian', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]]]);
    }
}
