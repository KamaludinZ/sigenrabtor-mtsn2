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
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\MasterKodeDb;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SimpanMasterIkskTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    /**
     * CSV IKSK dari master IKSK TA 2026: $ubah kode → [kode SK, uraian] (null = dibuang), $tambah baris tambahan.
     *
     * @param  array<string, array{0: string, 1: string}|null>  $ubah
     * @param  list<string>  $tambah
     */
    private function csvIksk(array $ubah = [], array $tambah = []): string
    {
        $baris = ['Kode IKSK;Kode SK;Uraian IKSK'];
        foreach (MasterKode::kodeIksk(tahun: 2026) as $i) {
            if (! array_key_exists($i['kode'], $ubah)) {
                $baris[] = "{$i['kode']};{$i['kode_sk']};{$i['uraian']}";
            } elseif ($ubah[$i['kode']] !== null) {
                $baris[] = "{$i['kode']};{$ubah[$i['kode']][0]};{$ubah[$i['kode']][1]}";
            }
        }

        return implode("\n", [...$baris, ...$tambah])."\n";
    }

    private function berkas(string $isi): string
    {
        $path = tempnam(sys_get_temp_dir(), 'iksk');
        file_put_contents($path, $isi);

        return $path;
    }

    #[DataProvider('penyimpanan')]
    public function test_simpan_iksk_terkait_sk(bool $database): void
    {
        if ($database) {
            RujukanDb::siapkan();
            MasterKodeDb::muat(2026);
            $this->app->singleton(MasterKodeRepository::class, EloquentMasterKodeRepository::class);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE'); // FK IKSK → SK diperiksa seketika.
        }
        $lama = MasterKode::kodeIksk(tahun: 2026);
        $sk = MasterKode::kodeSk(2026);
        $hasil = SimpanMasterKode::simpan($this->berkas($this->csvIksk(
            ['IKSK 9.1' => ['SK.9', 'Persentase sarana prasarana layak pakai'], 'IKSK 8.1' => null],
            ['IKSK 19.9;SK.19;Indikator baru di bawah SK.19'],
        )), 'iksk.csv', 'iksk', 2026, self::ADMIN);

        $this->assertSame(['baru' => 1, 'berubah' => 1, 'tetap' => count($lama) - 2, 'hilang' => 1, 'gagal' => 0], $hasil['pratinjau']['iksk']['ringkasan']);
        $baru = collect(MasterKode::kodeIksk(tahun: 2026))->keyBy('kode');
        $this->assertSame(['kode' => 'IKSK 19.9', 'kode_sk' => 'SK.19', 'uraian' => 'Indikator baru di bawah SK.19'], $baru['IKSK 19.9']);
        $this->assertSame('Persentase sarana prasarana layak pakai', $baru['IKSK 9.1']['uraian']);
        $this->assertArrayNotHasKey('IKSK 8.1', $baru->all());
        $this->assertContains('IKSK 19.9', array_column(MasterKode::kodeIksk('SK.19', 2026), 'kode'), 'IKSK baru di bawah SK.19.');
        $this->assertSame($sk, MasterKode::kodeSk(2026), 'SK tidak tersentuh.');
        $this->assertSame('Master kode TA 2026 dari iksk.csv — IKSK: 1 baru, 1 berubah, '.(count($lama) - 2).' tetap, 1 dihapus', app(RiwayatPengaturanRepository::class)->semua()[0]['keterangan']);
        $this->assertSame('iksk', app(MasterKodeRepository::class)->unggahan(2026)[0]['cakupan']);
    }

    public function test_induk_wajib_ada_dan_nomor_tak_sejalan_hanya_peringatan(): void
    {
        try {
            SimpanMasterKode::simpan($this->berkas($this->csvIksk([], ['IKSK 77.1;SK.77;Indikator tanpa induk'])), 'iksk.csv', 'iksk', 2026, self::ADMIN);
            $this->fail('IKSK tanpa SK induk harus ditahan.');
        } catch (GagalSimpanMaster $e) {
            $this->assertSame(['IKSK: 1 baris gagal — perbaiki berkasnya lalu unggah lagi.'], $e->kendala);
        }
        $this->assertNotContains('IKSK 77.1', array_column(MasterKode::kodeIksk(tahun: 2026), 'kode'));

        $hasil = SimpanMasterKode::simpan($this->berkas($this->csvIksk([], ['IKSK 9.7;SK.10;Indikator nomor tak sejalan'])), 'iksk.csv', 'iksk', 2026, self::ADMIN);
        $baris = collect($hasil['pratinjau']['iksk']['baris'])->firstWhere('kode', 'IKSK 9.7');
        $this->assertSame(['baru', ['Nomor IKSK 9.7 tidak sejalan dengan SK induk SK.10 — periksa lagi.']], [$baris['status'], $baris['pesan']]);
        $this->assertSame('SK.10', collect(MasterKode::kodeIksk(tahun: 2026))->firstWhere('kode', 'IKSK 9.7')['kode_sk']);
    }

    public function test_endpoint_dan_panel_iksk(): void
    {
        $this->withSession(['stub_role' => 'admin']);
        $url = route('master.unggah', ['tahun' => 2026, 'jenis' => 'iksk']);
        $csv = fn (string $isi) => UploadedFile::fake()->createWithContent('iksk.csv', $isi);

        $respons = $this->postJson($url, ['berkas' => $csv($this->csvIksk([], ['IKSK 19.9;SK.19;Indikator baru', 'IKSK 77.1;SK.77;Tanpa induk']))])
            ->assertOk()
            ->assertJsonPath('siap', false)
            ->assertJsonPath('kendala', ['IKSK: 1 baris gagal — perbaiki berkasnya lalu unggah lagi.']);
        $induk = collect($respons->json('induk'))->keyBy('kode_sk');
        $this->assertSame([null, 1, ['gagal' => 1]], [$induk['SK.77']['uraian_sk'], $induk['SK.77']['jumlah'], $induk['SK.77']['status']]);
        $this->assertSame(MasterKode::kodeSk(2026)[0]['uraian'], $induk['SK.8']['uraian_sk']);
        $this->assertSame(array_column($respons->json('induk'), 'kode_sk'), collect($respons->json('induk'))->pluck('kode_sk')->sort(fn ($a, $b) => version_compare(substr($a, 3), substr($b, 3)))->values()->all(), 'Urut nomor SK.');
        $this->assertSame(count(MasterKode::kodeIksk('SK.19', 2026)) + 1, $induk['SK.19']['jumlah']);

        $this->postJson($url, ['berkas' => $csv($this->csvIksk([], ['IKSK 19.9;SK.19;Indikator baru'])), 'simpan' => 1])->assertCreated()->assertJsonPath('disimpan', true);
        $this->assertContains('IKSK 19.9', array_column(MasterKode::kodeIksk(tahun: 2026), 'kode'));

        Livewire::test(UnggahMaster::class, ['tahun' => 2026])
            ->set('cakupan', 'iksk')
            ->set('berkas', $csv($this->csvIksk(['IKSK 19.9' => null])))
            ->call('lanjut')
            ->assertSeeHtml('data-testid="simpan-master"')
            ->call('simpan')
            ->assertDispatched('notify', type: 'success');
        $this->assertNotContains('IKSK 19.9', array_column(MasterKode::kodeIksk(tahun: 2026), 'kode'));
    }
}
