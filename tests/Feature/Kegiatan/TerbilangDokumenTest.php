<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\Rab;
use App\Support\Dokumen\AngkaDokumen;
use App\Support\Dokumen\PemeriksaTerbilang;
use App\Support\Rab\KalkulatorRab;
use App\Support\Stub\StubAnggaran;
use Livewire\Livewire;
use Tests\TestCase;

class TerbilangDokumenTest extends TestCase
{
    public function test_semua_tempat_cetak_memakai_nominal_dan_terbilang_yang_sama(): void
    {
        $angka = AngkaDokumen::dari(KalkulatorRab::hitung([['volume' => 1, 'harga_satuan' => 10_000_000]]), 'Komite', 2026, 'MTsN 2 Kota Malang');

        $this->assertSame(
            'Biaya yang terkait dengan kegiatan ini diperlukan sebesar Rp10.000.000,- (Sepuluh Juta Rupiah) yang dibebankan pada dana KOMITE MTsN 2 Kota Malang Tahun 2026, sebagaimana RAB terlampir.',
            $angka->kalimatPembiayaan(),
        );
        $this->assertSame('Rp10.000.000,- (Sepuluh Juta Rupiah)', $angka->nominalDanTerbilang());
        $this->assertSame('10.000.000', $angka->angkaTabel());
        $this->assertSame(['total' => 'Rp10.000.000,-', 'terbilang' => 'Sepuluh Juta Rupiah'], $angka->variabel());
        $this->assertSame(['kak-pembiayaan', 'rab-diajukan', 'rab-total'], array_column($angka->tempatCetak(), 'kunci'));
        $this->assertTrue($angka->sinkron());
    }

    public function test_tanpa_rincian_dokumen_tidak_memuat_nominal_dan_terbilang(): void
    {
        $angka = AngkaDokumen::dari(KalkulatorRab::hitung([]), 'DIPA', 2026, 'MTsN 2 Kota Malang');

        $this->assertFalse($angka->adaRincian());
        $this->assertNull($angka->kalimatPembiayaan());
        $this->assertNull($angka->nominalDanTerbilang());
        $this->assertSame('', $angka->variabel()['terbilang']);
        $this->assertSame([null, null, null], array_column($angka->tempatCetak(), 'teks'));
    }

    public function test_seluruh_kegiatan_contoh_sinkron_di_setiap_sumber_dana(): void
    {
        $kegiatanRepo = app(KegiatanRepository::class);
        $rabRepo = app(RabRepository::class);
        $diperiksa = 0;

        foreach ($kegiatanRepo->terbaru(50) as $ringkas) {
            $kegiatan = $kegiatanRepo->temukan($ringkas['id']);

            foreach ($kegiatan['sumber_dana'] ?? [] as $sumber) {
                $hasil = KalkulatorRab::hitung($rabRepo->rincian($kegiatan['id'], $sumber['sumber']));
                $angka = AngkaDokumen::dari($hasil, $sumber['sumber'], 2026, 'MTsN 2 Kota Malang');

                $this->assertTrue($angka->sinkron(), "Kegiatan {$kegiatan['id']} {$sumber['sumber']} tidak sinkron.");
                $diperiksa++;
            }
        }

        $this->assertGreaterThan(0, $diperiksa);
    }

    public function test_pemeriksa_menangkap_kesalahan_dokumen_lama(): void
    {
        foreach (StubAnggaran::kesalahanDokumenLama() as $salah) {
            $teks = 'sebesar Rp. '.$salah['nilai'].' ('.$salah['tertulis'].') yang dibebankan';
            $temuan = PemeriksaTerbilang::periksa($teks);

            $this->assertCount(1, $temuan, $salah['masalah']);
            $this->assertSame($salah['nilai'], $temuan[0]['nominal']);
        }

        // Format mail merge lama (tanpa pemisah ribuan, spasi ganda) tetap dikenali bila terbilangnya benar.
        $this->assertSame([], PemeriksaTerbilang::periksa('sebesar Rp. 10000000 ( Sepuluh  Juta Rupiah) yang'));
        $this->assertSame(1, PemeriksaTerbilang::jumlahPasangan('Rp2.320.000,00 (Dua Juta Tiga Ratus Dua Puluh Ribu Rupiah)'));
    }

    public function test_pemeriksa_menandai_nominal_yang_berbeda_dari_total_rab(): void
    {
        $temuan = PemeriksaTerbilang::periksa('sebesar Rp9.450.000,- (Sembilan Juta Empat Ratus Lima Puluh Ribu Rupiah)', 9_540_000);

        $this->assertCount(1, $temuan);
        $this->assertSame('Nominal berbeda dari total RAB.', $temuan[0]['masalah']);
        $this->assertSame('Rp9.540.000', $temuan[0]['seharusnya']);
    }

    public function test_halaman_rab_menampilkan_angka_dokumen_yang_ikut_berubah(): void
    {
        $komponen = Livewire::test(Rab::class, ['kegiatan' => 1])
            ->assertSeeHtml('data-testid="sinkron-dokumen"')
            ->assertSee('Sinkron')
            ->assertSee('sebesar Rp10.000.000,- (Sepuluh Juta Rupiah) yang dibebankan pada dana KOMITE');

        $komponen->call('bukaFormItem')
            ->set('item.uraian', 'Spanduk kegiatan')
            ->set('item.volume', '1')
            ->set('item.satuan', 'buah')
            ->set('item.harga_satuan', '250000')
            ->call('simpanItem')
            ->assertSee('sebesar Rp10.250.000,- (Sepuluh Juta Dua Ratus Lima Puluh Ribu Rupiah)')
            ->assertSeeHtml('data-testid="header-nominal">Rp10.250.000,-</dd>')
            ->assertSeeHtml('data-testid="total-rab">10.250.000</td>')
            ->assertSeeHtml('data-teks="Rp10.250.000,- (Sepuluh Juta Dua Ratus Lima Puluh Ribu Rupiah)"')
            ->assertDontSee('Tidak sinkron');
    }

    public function test_rab_tanpa_rincian_menunggu_rincian(): void
    {
        $id = app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Kegiatan Kosong',
            'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]],
        ]);

        Livewire::test(Rab::class, ['kegiatan' => $id])
            ->assertSee('menunggu rincian')
            ->assertDontSee('Nol Rupiah')
            ->assertSeeHtml('data-testid="kalimat-pembiayaan-kosong"');
    }
}
