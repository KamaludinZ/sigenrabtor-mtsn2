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

class SimpanMasterBudayaMutuTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    private function pakaiDatabase(): void
    {
        RujukanDb::siapkan();
        MasterKodeDb::muat(2026);
        $this->app->singleton(MasterKodeRepository::class, EloquentMasterKodeRepository::class);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE'); // FK IKSK → SK diperiksa seketika.
    }

    private function berkas(string $isi): string
    {
        $path = tempnam(sys_get_temp_dir(), 'budaya');
        file_put_contents($path, $isi);

        return $path;
    }

    /** @param  list<string>  $nama */
    private function csv(array $nama, bool $nomor = true): string
    {
        return implode("\n", [$nomor ? 'No;Budaya Mutu' : 'Budaya Mutu', ...array_map(fn (string $n, int $i) => $nomor ? ($i + 1).";{$n}" : $n, $nama, array_keys($nama))])."\n";
    }

    #[DataProvider('penyimpanan')]
    public function test_simpan_5_budaya_mutu(bool $database): void
    {
        if ($database) {
            $this->pakaiDatabase();
        }
        $lama = array_column(MasterKode::budayaMutu(2026), 'nama');
        $nama = $lama;
        $nama[1] = 'Disiplin dan tertib';
        $hasil = SimpanMasterKode::simpan($this->berkas($this->csv($nama)), 'budaya.csv', 'budaya', 2026, self::ADMIN);

        $this->assertSame(['baru' => 0, 'berubah' => 1, 'tetap' => count($lama) - 1, 'hilang' => 0, 'gagal' => 0], $hasil['pratinjau']['budaya']['ringkasan']);
        $this->assertSame(array_map(fn (string $n, int $i) => ['nomor' => $i + 1, 'nama' => $n], $nama, array_keys($nama)), MasterKode::budayaMutu(2026));
        $this->assertSame('Master kode TA 2026 dari budaya.csv — 5 Budaya Mutu: 1 berubah, '.(count($lama) - 1).' tetap', app(RiwayatPengaturanRepository::class)->semua()[0]['keterangan']);

        // Tanpa kolom No: nomor = urutan; jumlah selain lima hanya peringatan.
        $hasil = SimpanMasterKode::simpan($this->berkas($this->csv(['Jujur', 'Disiplin', 'Peduli', 'Santun'], nomor: false)), 'budaya.csv', 'budaya', 2026, self::ADMIN);
        $this->assertSame([['nomor' => 1, 'nama' => 'Jujur'], ['nomor' => 2, 'nama' => 'Disiplin'], ['nomor' => 3, 'nama' => 'Peduli'], ['nomor' => 4, 'nama' => 'Santun']], MasterKode::budayaMutu(2026));
        $this->assertCount(1, $hasil['peringatan']);
        $this->assertStringStartsWith('5 Budaya Mutu: ', $hasil['peringatan'][0]);
    }

    public function test_nama_dobel_menahan_simpan(): void
    {
        try {
            SimpanMasterKode::simpan($this->berkas($this->csv(['Jujur', 'Disiplin', 'jujur', 'Peduli', 'Santun'])), 'budaya.csv', 'budaya', 2026, self::ADMIN);
            $this->fail('Nama dobel harus ditahan.');
        } catch (GagalSimpanMaster $e) {
            $this->assertSame(['5 Budaya Mutu: 1 baris gagal — perbaiki berkasnya lalu unggah lagi.'], $e->kendala);
        }
    }

    #[DataProvider('penyimpanan')]
    public function test_berkas_master_lengkap_disimpan_sekaligus(bool $database): void
    {
        if ($database) {
            $this->pakaiDatabase();
        }
        $path = base_path('tests/fixtures/master-kode-contoh.xlsx');
        $hasil = SimpanMasterKode::simpan($path, 'master-kode-contoh.xlsx', 'semua', 2026, self::ADMIN);

        $tetap = array_map(fn (array $item) => $item['ringkasan']['tetap'] + $item['ringkasan']['baru'] + $item['ringkasan']['berubah'], $hasil['pratinjau']);
        $this->assertSame($tetap, [
            'sk' => count(MasterKode::kodeSk(2026)), 'iksk' => count(MasterKode::kodeIksk(tahun: 2026)), 'rktm' => count(MasterKode::kodeRktm(2026)),
            'edm' => count(MasterKode::kodeEdm(2026)), 'budaya' => count(MasterKode::budayaMutu(2026)),
        ]);
        $this->assertSame([], array_diff(array_column(MasterKode::kodeIksk(tahun: 2026), 'kode_sk'), array_column(MasterKode::kodeSk(2026), 'kode')), 'Setiap IKSK punya SK induk.');
        $this->assertNotEmpty($hasil['peringatan'], 'Kode hilang yang dipakai kegiatan hanya peringatan.');
        $this->assertSame('semua', app(MasterKodeRepository::class)->unggahan(2026)[0]['cakupan']);
        $this->assertStringContainsString('SK: ', app(RiwayatPengaturanRepository::class)->semua()[0]['keterangan']);
        $this->assertStringContainsString('; 5 Budaya Mutu: ', app(RiwayatPengaturanRepository::class)->semua()[0]['keterangan']);
    }

    public function test_endpoint_dan_panel_budaya_mutu(): void
    {
        $this->withSession(['stub_role' => 'admin']);
        $csv = fn (string $isi) => UploadedFile::fake()->createWithContent('budaya.csv', $isi);

        $this->postJson(route('master.unggah', ['tahun' => 2026, 'jenis' => 'budaya']), ['berkas' => $csv($this->csv(['Jujur', 'Disiplin', 'Peduli', 'Santun']))])
            ->assertOk()
            ->assertJsonPath('siap', true)
            ->assertJsonPath('butir', ['jumlah' => 4, 'jumlah_baku' => 5])
            ->assertJsonCount(1, 'peringatan');

        Livewire::test(UnggahMaster::class, ['tahun' => 2026])
            ->set('berkas', UploadedFile::fake()->createWithContent('master.xlsx', (string) file_get_contents(base_path('tests/fixtures/master-kode-contoh.xlsx'))))
            ->call('lanjut')
            ->assertDontSeeHtml('wire:key="simpan-segera"')
            ->assertSeeHtml('data-testid="simpan-master"')
            ->call('simpan')
            ->assertDispatched('notify', type: 'success')
            ->assertDispatched('unggah-master-ditutup');
        $this->assertSame('semua', app(MasterKodeRepository::class)->unggahan(2026)[0]['cakupan']);
    }
}
