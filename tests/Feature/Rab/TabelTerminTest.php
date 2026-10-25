<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\Rab;
use App\Livewire\Kegiatan\TerminPembayaran;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\MekanismePembayaran;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

class TabelTerminTest extends TestCase
{
    private function mekanismeContoh(): MekanismePembayaran
    {
        return MekanismePembayaran::dari(AnggaranKegiatan::perSumber(app(KegiatanRepository::class)->temukan(1))['Komite']);
    }

    public function test_baris_tabel_kumulatif_dan_per_bulan(): void
    {
        $baris = $this->mekanismeContoh()->barisTabel();

        $this->assertSame([[1, '2026-06-17', 7_680_000, 76.8, 7_680_000], [2, '2026-06-19', 2_320_000, 23.2, 10_000_000]],
            array_map(fn ($b) => [$b['nomor'], $b['tanggal']->toDateString(), $b['nominal'], $b['persen'], $b['kumulatif']], $baris));
        $this->assertSame('Dua Juta Tiga Ratus Dua Puluh Ribu Rupiah', $baris[1]['terbilang']);
        $this->assertSame([6 => 10_000_000], $this->mekanismeContoh()->perBulan());

        $campur = new MekanismePembayaran(300, [['tanggal' => '2026-09-01', 'nominal' => 100], ['tanggal' => null, 'nominal' => 50], ['tanggal' => '2026-03-02', 'nominal' => 150]]);
        $this->assertSame([0 => 50, 3 => 150, 9 => 100], $campur->perBulan());
        $this->assertNull($campur->barisTabel()[1]['tanggal']);
    }

    public function test_status_termin_server_sama_dengan_kembaran_klien(): void
    {
        // Berkas yang sama dipakai tests/e2e/format.spec.js untuk statusTermin()/persenTermin() di resources/js/termin.js.
        $kasus = json_decode(file_get_contents(base_path('tests/fixtures/status-termin.json')), true);
        $this->assertNotEmpty($kasus);

        foreach ($kasus as $k) {
            $mekanisme = new MekanismePembayaran($k['total'], array_map(fn ($n) => ['tanggal' => null, 'nominal' => $n], $k['nominal']));
            $this->assertSame([$k['status'], $k['teks'], $k['jumlah']], [$mekanisme->status(), $mekanisme->teksStatus(), $mekanisme->jumlah()], json_encode($k));
            $this->assertEquals($k['persen'], array_map(fn ($n) => $mekanisme->persen($n), $k['nominal']));
        }
    }

    public function test_halaman_termin_menghitung_di_klien(): void
    {
        Livewire::test(TerminPembayaran::class, ['kegiatanId' => 1, 'sumber' => 'Komite'])
            ->assertSeeHtml('x-data="jumlahTermin({ total: 10000000 })"')
            ->assertSeeHtml('x-bind:data-status="hasil.status"')
            ->assertSeeHtml('data-persen-termin')
            ->assertSeeHtml('x-text="hasil.teks"');
    }

    public function test_komponen_tabel_termin_baca_saja(): void
    {
        $html = Blade::render('<x-rab.tabel-termin :mekanisme="$m" sumber="Komite" />', ['m' => $this->mekanismeContoh()]);

        foreach (['data-testid="tabel-termin-komite"', '17 Juni 2026', 'Rp7.680.000', '76,8%', 'Rp10.000.000', 'Tujuh Juta Enam Ratus Delapan Puluh Ribu Rupiah', 'Jumlah termin sama dengan total RAB', 'data-termin-kartu="2"'] as $teks) {
            $this->assertStringContainsString($teks, $html);
        }

        $sekaligus = Blade::render('<x-rab.tabel-termin :mekanisme="$m" sumber="DIPA" />', ['m' => new MekanismePembayaran(5_400_000)]);
        $this->assertStringContainsString('RAB DIPA dibayar sekaligus sebesar Rp5.400.000 — tanpa termin.', $sekaligus);
    }

    public function test_grid_jadwal_pembayaran_di_halaman_termin(): void
    {
        Livewire::test(TerminPembayaran::class, ['kegiatanId' => 1, 'sumber' => 'Komite'])
            ->assertSeeHtml('data-testid="jadwal-termin"')
            ->assertSee('Jadwal pembayaran 2026')
            ->assertSeeHtml('data-jadwal-sumber="Komite"')
            ->assertSeeHtml('data-bulan="6"');

        // Kegiatan dua sumber tanpa termin: grid menjelaskan semuanya sekaligus; sunting DIPA langsung tercermin.
        Livewire::test(TerminPembayaran::class, ['kegiatanId' => 2, 'sumber' => 'DIPA'])
            ->assertSee('Semua RAB kegiatan ini dibayar sekaligus — belum ada termin.')
            ->call('bagiRata', 2)
            ->assertSee('Tanpa tanggal')
            ->assertSee('Dibayar sekaligus Rp2.500.000')
            ->set('baris.0.tanggal', '2026-08-03')
            ->set('baris.1.tanggal', '2026-09-01')
            ->assertSeeHtml('data-bulan="8"')
            ->assertSeeHtml('data-bulan="9"')
            ->assertDontSee('Tanpa tanggal')
            ->assertSee('Semua sumber');
    }

    public function test_terkunci_menampilkan_tabel_baca_saja_dan_semua_rab_memuat_termin(): void
    {
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Final', 'status' => 'final', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]]]);
        app(RabRepository::class)->tambahItem($id, 'DIPA', ['kode' => '', 'uraian' => 'Honor', 'kategori' => 'jasa', 'volume' => 1, 'satuan' => 'OJ', 'harga_satuan' => 900_000]);
        app(RabRepository::class)->simpanTermin($id, 'DIPA', [['tanggal' => '2026-05-04', 'nominal' => 900_000]]);

        Livewire::test(TerminPembayaran::class, ['kegiatanId' => $id, 'sumber' => 'DIPA'])
            ->assertSeeHtml('data-testid="tabel-termin-dipa"')
            ->assertDontSeeHtml('id="termin-nominal-0"')
            ->assertSee('4 Mei 2026');

        app(RabRepository::class)->simpanTermin(2, 'Komite', [['tanggal' => '2026-08-31', 'nominal' => 2_500_000]]);
        Livewire::test(Rab::class, ['kegiatan' => 2])
            ->set('halaman', 'semua')
            ->assertSeeHtml('data-testid="tabel-termin-komite"')
            ->assertDontSeeHtml('data-testid="tabel-termin-dipa"')
            ->assertSee('31 Agustus 2026');
    }
}
