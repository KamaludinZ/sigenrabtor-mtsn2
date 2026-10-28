<?php

namespace Tests\Feature\Master;

use App\Contracts\MasterKodeRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Livewire\Master\UnggahMaster;
use App\Repositories\EloquentMasterKodeRepository;
use App\Support\Master\Impor\GagalSimpanMaster;
use App\Support\Master\Impor\SimpanMasterKode;
use App\Support\MasterKode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\MasterKodeDb;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SimpanMasterRktmTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    /**
     * CSV RKTM dari master TA 2026 bergaya sel gabungan (SNP & kegiatan hanya di baris pertama kelompoknya).
     *
     * @param  array<string, array{0: string, 1: string, 2: string}|null>  $ubah  Kode → [snp, kegiatan, sub kegiatan] (null = dibuang).
     * @param  list<string>  $tambah
     */
    private function csvRktm(array $ubah = [], array $tambah = []): string
    {
        $baris = ['Kode RKTM;SNP;Kegiatan;Sub Kegiatan'];
        [$snpLalu, $kegiatanLalu] = [null, null];
        foreach (MasterKode::kodeRktm(2026) as $r) {
            if (array_key_exists($r['kode'], $ubah) && $ubah[$r['kode']] === null) {
                continue;
            }
            [$snp, $kegiatan, $sub] = $ubah[$r['kode']] ?? [$r['snp'], $r['kegiatan'], $r['sub_kegiatan']];
            $baris[] = $r['kode'].';'.($snp === $snpLalu ? '' : $snp).';'.($kegiatan === $kegiatanLalu && $snp === $snpLalu ? '' : $kegiatan).';'.$sub;
            [$snpLalu, $kegiatanLalu] = [$snp, $kegiatan];
        }

        return implode("\n", [...$baris, ...$tambah])."\n";
    }

    private function berkas(string $isi): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rktm');
        file_put_contents($path, $isi);

        return $path;
    }

    #[DataProvider('penyimpanan')]
    public function test_simpan_rktm_dengan_isian_sel_gabungan(bool $database): void
    {
        if ($database) {
            RujukanDb::siapkan();
            MasterKodeDb::muat(2026);
            $this->app->singleton(MasterKodeRepository::class, EloquentMasterKodeRepository::class);
        }
        $lama = MasterKode::kodeRktm(2026);
        $pertama = $lama[0];
        $hasil = SimpanMasterKode::simpan($this->berkas($this->csvRktm(
            [$pertama['kode'] => [$pertama['snp'], $pertama['kegiatan'], 'Penyusunan Program Tahunan Madrasah'], $lama[1]['kode'] => null],
            ['9.1.1;STANDAR BARU;Kegiatan baru;Sub kegiatan baru'],
        )), 'snp.csv', 'rktm', 2026, self::ADMIN);

        $this->assertSame(['baru' => 1, 'berubah' => 1, 'tetap' => count($lama) - 2, 'hilang' => 1, 'gagal' => 0], $hasil['pratinjau']['rktm']['ringkasan']);
        $baru = collect(MasterKode::kodeRktm(2026))->keyBy('kode');
        $this->assertSame(['kode' => '9.1.1', 'snp' => 'STANDAR BARU', 'kegiatan' => 'Kegiatan baru', 'sub_kegiatan' => 'Sub kegiatan baru'], $baru['9.1.1']);
        $this->assertSame('Penyusunan Program Tahunan Madrasah', $baru[$pertama['kode']]['sub_kegiatan']);
        $this->assertSame(array_values(array_diff_key(collect($lama)->keyBy('kode')->all(), [$pertama['kode'] => 1, $lama[1]['kode'] => 1])), array_values(array_diff_key($baru->all(), [$pertama['kode'] => 1, '9.1.1' => 1])), 'SNP & kegiatan sel kosong terisi dari baris di atasnya.');
        $this->assertSame('Master kode TA 2026 dari snp.csv — RKTM: 1 baru, 1 berubah, '.(count($lama) - 2).' tetap, 1 dihapus', app(RiwayatPengaturanRepository::class)->semua()[0]['keterangan']);
    }

    public function test_struktur_snp_dan_kendala(): void
    {
        $lama = MasterKode::kodeRktm(2026);
        $baris = SimpanMasterKode::simpan($this->berkas($this->csvRktm()), 'snp.csv', 'rktm', 2026, self::ADMIN)['pratinjau']['rktm']['baris'];
        $struktur = SimpanMasterKode::strukturRktm($baris);

        $this->assertSame(count($lama), array_sum(array_column($struktur, 'jumlah')));
        $this->assertSame(array_values(array_unique(array_map(fn (array $r) => explode('.', $r['kode'])[0], $lama))), array_column($struktur, 'nomor'), 'Urut nomor standar.');
        $this->assertSame($lama[0]['snp'], $struktur[0]['snp']);
        $kegiatan = $struktur[0]['kegiatan'][0];
        $this->assertSame([implode('.', array_slice(explode('.', $lama[0]['kode']), 0, 2)), $lama[0]['kegiatan']], [$kegiatan['nomor'], $kegiatan['kegiatan']]);
        $this->assertSame(['tetap' => $kegiatan['jumlah']], $kegiatan['status']);

        try {
            SimpanMasterKode::simpan($this->berkas($this->csvRktm([], ['6.17;Standar;Kegiatan;Kode dua bagian'])), 'snp.csv', 'rktm', 2026, self::ADMIN);
            $this->fail('Baris gagal harus menahan simpan.');
        } catch (GagalSimpanMaster $e) {
            $this->assertSame(['RKTM: 1 baris gagal — perbaiki berkasnya lalu unggah lagi.'], $e->kendala);
        }
    }

    public function test_endpoint_dan_panel_rktm(): void
    {
        $this->withSession(['stub_role' => 'admin']);
        $csv = fn (string $isi) => UploadedFile::fake()->createWithContent('snp.csv', $isi);

        $respons = $this->postJson(route('master.unggah', ['tahun' => 2026, 'jenis' => 'rktm']), ['berkas' => $csv($this->csvRktm([], ['9.1.1;STANDAR BARU;Kegiatan baru;Sub kegiatan baru']))])
            ->assertOk()
            ->assertJsonPath('siap', true)
            ->assertJsonPath('induk', null);
        $akhir = collect($respons->json('struktur'))->last();
        $this->assertSame(['9', 'STANDAR BARU', 1], [$akhir['nomor'], $akhir['snp'], $akhir['jumlah']]);
        $this->assertSame([['nomor' => '9.1', 'kegiatan' => 'Kegiatan baru', 'jumlah' => 1, 'status' => ['baru' => 1]]], $akhir['kegiatan']);

        Livewire::test(UnggahMaster::class, ['tahun' => 2026])
            ->set('cakupan', 'rktm')
            ->set('berkas', $csv($this->csvRktm([], ['9.1.1;STANDAR BARU;Kegiatan baru;Sub kegiatan baru'])))
            ->call('lanjut')
            ->assertSeeHtml('data-testid="simpan-master"')
            ->call('simpan')
            ->assertDispatched('notify', type: 'success');
        $this->assertSame('9.1.1', collect(MasterKode::kodeRktm(2026))->last()['kode']);
    }
}
