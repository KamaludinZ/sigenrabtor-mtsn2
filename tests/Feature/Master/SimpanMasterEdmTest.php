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

class SimpanMasterEdmTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    /**
     * CSV EDM dari master TA 2026; $komponen = huruf → nama di kolom Komponen (null = tanpa kolom Komponen).
     *
     * @param  array<string, string>|null  $komponen
     * @param  list<string>  $buang
     * @param  list<string>  $tambah
     */
    private function csvEdm(?array $komponen = [], array $buang = [], array $tambah = []): string
    {
        $nama = ($komponen ?? []) + MasterKode::komponenEdm(2026);
        $baris = [$komponen === null ? 'Kode;Uraian' : 'Kode;Uraian;Komponen'];
        foreach (MasterKode::kodeEdm(2026) as $e) {
            if (! in_array($e['kode'], $buang, true)) {
                $baris[] = "{$e['kode']};{$e['uraian']}".($komponen === null ? '' : ';'.$nama[$e['komponen']]);
            }
        }

        return implode("\n", [...$baris, ...$tambah])."\n";
    }

    private function berkas(string $isi): string
    {
        $path = tempnam(sys_get_temp_dir(), 'edm');
        file_put_contents($path, $isi);

        return $path;
    }

    #[DataProvider('penyimpanan')]
    public function test_simpan_edm_beserta_nama_komponen(bool $database): void
    {
        if ($database) {
            RujukanDb::siapkan();
            MasterKodeDb::muat(2026);
            $this->app->singleton(MasterKodeRepository::class, EloquentMasterKodeRepository::class);
        }
        $lama = MasterKode::kodeEdm(2026);
        $jumlahB = count(array_filter($lama, fn (array $e) => $e['komponen'] === 'B'));
        $hasil = SimpanMasterKode::simpan($this->berkas($this->csvEdm(['B' => 'Pengembangan Diri Guru'], ['A7'], ['F1;Kemitraan dengan orang tua;Kemitraan'])), 'edm.csv', 'edm', 2026, self::ADMIN);

        $this->assertSame(['baru' => 1, 'berubah' => $jumlahB, 'tetap' => count($lama) - 1 - $jumlahB, 'hilang' => 1, 'gagal' => 0], $hasil['pratinjau']['edm']['ringkasan']);
        $this->assertSame(['A' => 'Kedisiplinan', 'B' => 'Pengembangan Diri Guru', 'C' => 'Proses Pembelajaran', 'D' => 'Sarana dan Prasarana', 'E' => 'Pembiayaan', 'F' => 'Kemitraan'], MasterKode::komponenEdm(2026));
        $baru = collect(MasterKode::kodeEdm(2026))->keyBy('kode');
        $this->assertSame(['kode' => 'F1', 'komponen' => 'F', 'uraian' => 'Kemitraan dengan orang tua'], $baru['F1']);
        $this->assertArrayNotHasKey('A7', $baru->all());
        $this->assertSame('Master kode TA 2026 dari edm.csv — EDM: 1 baru, '.$jumlahB.' berubah, '.(count($lama) - 1 - $jumlahB).' tetap, 1 dihapus', app(RiwayatPengaturanRepository::class)->semua()[0]['keterangan']);
    }

    public function test_tanpa_kolom_komponen_memakai_nama_master(): void
    {
        $hasil = SimpanMasterKode::simpan($this->berkas($this->csvEdm(null)), 'edm.csv', 'edm', 2026, self::ADMIN);
        $this->assertSame(count(MasterKode::kodeEdm(2026)), $hasil['pratinjau']['edm']['ringkasan']['tetap']);
        $this->assertSame(['A' => 'Kedisiplinan', 'B' => 'Pengembangan Diri', 'C' => 'Proses Pembelajaran', 'D' => 'Sarana dan Prasarana', 'E' => 'Pembiayaan'], MasterKode::komponenEdm(2026));

        try {
            SimpanMasterKode::simpan($this->berkas($this->csvEdm(null, [], ['G1;Indikator komponen baru'])), 'edm.csv', 'edm', 2026, self::ADMIN);
            $this->fail('Komponen tanpa nama harus ditahan.');
        } catch (GagalSimpanMaster $e) {
            $this->assertSame(['EDM: nama komponen G belum ada di berkas maupun master — isi kolom Komponen.'], $e->kendala);
        }
        $this->assertArrayNotHasKey('G', MasterKode::komponenEdm(2026));
    }

    public function test_endpoint_dan_panel_edm(): void
    {
        $this->withSession(['stub_role' => 'admin']);
        $csv = fn (string $isi) => UploadedFile::fake()->createWithContent('edm.csv', $isi);

        $respons = $this->postJson(route('master.unggah', ['tahun' => 2026, 'jenis' => 'edm']), ['berkas' => $csv($this->csvEdm(['B' => 'Pengembangan Diri Guru']))])
            ->assertOk()
            ->assertJsonPath('siap', true)
            ->assertJsonPath('struktur', null);
        $komponen = collect($respons->json('komponen'))->keyBy('huruf');
        $this->assertSame(['A', 'B', 'C', 'D', 'E'], $komponen->keys()->all());
        $this->assertSame(['Pengembangan Diri Guru', 'Pengembangan Diri', ['berubah' => $komponen['B']['jumlah']]], [$komponen['B']['nama'], $komponen['B']['nama_lama'], $komponen['B']['status']]);

        Livewire::test(UnggahMaster::class, ['tahun' => 2026])
            ->set('cakupan', 'edm')
            ->set('berkas', $csv($this->csvEdm(['B' => 'Pengembangan Diri Guru'])))
            ->call('lanjut')
            ->assertSeeHtml('data-testid="simpan-master"')
            ->call('simpan')
            ->assertDispatched('notify', type: 'success');
        $this->assertSame('Pengembangan Diri Guru', MasterKode::komponenEdm(2026)['B']);
    }
}
