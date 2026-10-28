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
use InvalidArgumentException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\MasterKodeDb;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SimpanMasterSkTest extends TestCase
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
        DB::table('tahun_anggaran')->insert(['tahun' => 2027, 'is_active' => false, 'is_locked' => false]);
        MasterKodeDb::muat(2025);
        MasterKodeDb::muat(2026);
        $this->app->singleton(MasterKodeRepository::class, EloquentMasterKodeRepository::class);
    }

    /**
     * CSV SK dari master SK TA 2026: $ubah kode → uraian baru (null = dibuang), $tambah baris tambahan.
     *
     * @param  array<string, string|null>  $ubah
     * @param  list<string>  $tambah
     */
    private function csvSk(array $ubah = [], array $tambah = []): string
    {
        $baris = ['Kode SK;Uraian SK'];
        foreach (MasterKode::kodeSk(2026) as $sk) {
            if (! array_key_exists($sk['kode'], $ubah)) {
                $baris[] = "{$sk['kode']};{$sk['uraian']}";
            } elseif ($ubah[$sk['kode']] !== null) {
                $baris[] = "{$sk['kode']};{$ubah[$sk['kode']]}";
            }
        }

        return implode("\n", [...$baris, ...$tambah])."\n";
    }

    private function berkas(string $isi, string $nama = 'sk.csv'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sk');
        file_put_contents($path, $isi);

        return $path;
    }

    /** SK pertama yang menjadi induk IKSK master TA 2026. */
    private function skInduk(): string
    {
        return MasterKode::kodeIksk(tahun: 2026)[0]['kode_sk'];
    }

    #[DataProvider('penyimpanan')]
    public function test_repositori_ganti_bagian_dan_catat_unggahan(bool $database): void
    {
        if ($database) {
            $this->pakaiDatabase();
        }
        $repo = app(MasterKodeRepository::class);
        $rktm = $repo->kodeRktm(2026);

        $repo->ganti(2026, ['kode_sk' => [['kode' => 'SK.2', 'uraian' => 'Dua'], ['kode' => 'SK.1', 'uraian' => 'Satu']]]);
        $this->assertSame([['kode' => 'SK.2', 'uraian' => 'Dua'], ['kode' => 'SK.1', 'uraian' => 'Satu']], $repo->kodeSk(2026), 'Urutan berkas.');
        $this->assertSame($rktm, $repo->kodeRktm(2026), 'Bagian lain tetap.');

        $this->assertNotContains(2027, $repo->tahunTersedia());
        $repo->ganti(2027, ['kode_sk' => [['kode' => 'SK.1', 'uraian' => 'Satu']], 'komponen_edm' => ['A' => 'Kedisiplinan'], 'kode_edm' => [['kode' => 'A1', 'komponen' => 'A', 'uraian' => 'Hadir']], 'budaya_mutu' => [['nomor' => 1, 'nama' => 'Jujur']]]);
        $this->assertContains(2027, $repo->tahunTersedia());
        $this->assertSame([['A' => 'Kedisiplinan'], [['kode' => 'A1', 'komponen' => 'A', 'uraian' => 'Hadir']], [['nomor' => 1, 'nama' => 'Jujur']], []], [$repo->komponenEdm(2027), $repo->kodeEdm(2027), $repo->budayaMutu(2027), $repo->kodeRktm(2027)]);

        $id = $repo->catatUnggahan(['tahun' => 2026, 'cakupan' => 'sk', 'nama_berkas' => 'sk.csv', 'ringkasan' => ['sk' => ['baru' => 1, 'hilang' => 2]], 'user_id' => 2, 'pengguna' => 'Koordinator PUSKOM']);
        $repo->catatUnggahan(['tahun' => 2027, 'cakupan' => 'semua', 'nama_berkas' => 'master.xlsx', 'ringkasan' => [], 'user_id' => null, 'pengguna' => 'Admin']);
        $unggahan = $repo->unggahan(2026);
        $this->assertCount(1, $unggahan);
        $this->assertSame([$id, 'sk', 'sk.csv', ['sk' => ['baru' => 1, 'hilang' => 2]], 2, 'Koordinator PUSKOM'], [$unggahan[0]['id'], $unggahan[0]['cakupan'], $unggahan[0]['nama_berkas'], $unggahan[0]['ringkasan'], $unggahan[0]['user_id'], $unggahan[0]['pengguna']]);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}\+07:00$/', $unggahan[0]['waktu']);

        $this->expectException(InvalidArgumentException::class);
        $repo->ganti(2026, ['kode_asing' => []]);
    }

    public function test_simpan_sk_dari_berkas(): void
    {
        $lama = MasterKode::kodeSk(2026);
        $berubah = $lama[1]['kode'];
        $hasil = SimpanMasterKode::simpan($this->berkas($this->csvSk([$berubah => 'Uraian sasaran yang diperbarui'], ['SK.99;Sasaran kegiatan baru'])), 'sk.csv', 'sk', 2026, self::ADMIN);

        $this->assertTrue($hasil['disimpan']);
        $this->assertSame(['baru' => 1, 'berubah' => 1, 'tetap' => count($lama) - 1, 'hilang' => 0, 'gagal' => 0], $hasil['pratinjau']['sk']['ringkasan']);
        $baru = array_column(MasterKode::kodeSk(2026), 'uraian', 'kode');
        $this->assertSame(['Uraian sasaran yang diperbarui', 'Sasaran kegiatan baru'], [$baru[$berubah], $baru['SK.99']]);
        $this->assertSame('SK.99', array_key_last($baru), 'Urutan berkas.');

        $unggahan = app(MasterKodeRepository::class)->unggahan(2026)[0];
        $this->assertSame([$hasil['unggahan'], 'sk', 'sk.csv', 2, 'Koordinator PUSKOM'], [$unggahan['id'], $unggahan['cakupan'], $unggahan['nama_berkas'], $unggahan['user_id'], $unggahan['pengguna']]);
        $riwayat = app(RiwayatPengaturanRepository::class)->semua()[0];
        $this->assertSame(['unggah_master_kode', 'Master kode TA 2026 dari sk.csv — SK: 1 baru, 1 berubah, '.(count($lama) - 1).' tetap'], [$riwayat['aksi'], $riwayat['keterangan']]);

        // SK yang bukan induk IKSK (SK.99 baru) boleh dihapus; kegiatan pemakainya ditandai KodeHilang.
        $this->assertNotContains('SK.99', array_column(MasterKode::kodeIksk(tahun: 2026), 'kode_sk'));
        $hasil = SimpanMasterKode::simpan($this->berkas($this->csvSk(['SK.99' => null])), 'sk.csv', 'sk', 2026, self::ADMIN);
        $this->assertSame(1, $hasil['pratinjau']['sk']['ringkasan']['hilang']);
        $this->assertNotContains('SK.99', array_column(MasterKode::kodeSk(2026), 'kode'));
    }

    public function test_kendala_menahan_simpan(): void
    {
        $sebelum = MasterKode::kodeSk(2026);
        $kendala = function (string $isi, string $cakupan = 'sk'): array {
            try {
                SimpanMasterKode::simpan($this->berkas($isi), 'sk.csv', $cakupan, 2026, self::ADMIN);
            } catch (GagalSimpanMaster $e) {
                return $e->kendala;
            }
            $this->fail('Seharusnya ditahan.');
        };

        $induk = $this->skInduk();
        $this->assertSame(["SK: {$induk} tidak ada di berkas padahal masih menjadi induk IKSK master TA 2026 — unggah IKSK bersama SK (berkas master lengkap) atau pertahankan kodenya."], $kendala($this->csvSk([$induk => null])));
        $this->assertSame(['SK: 1 baris gagal — perbaiki berkasnya lalu unggah lagi.'], $kendala($this->csvSk([], ['IKSK 1.1;Bukan kode SK'])));
        $this->assertSame($sebelum, MasterKode::kodeSk(2026), 'Master tidak berubah.');
        $this->assertSame([], app(MasterKodeRepository::class)->unggahan(2026));
    }

    public function test_endpoint_unggah_sk(): void
    {
        $csv = fn (string $isi) => UploadedFile::fake()->createWithContent('sk.csv', $isi);
        $url = route('master.unggah', ['tahun' => 2026, 'jenis' => 'sk']);

        $this->withSession(['stub_role' => 'leading'])->post($url, ['berkas' => $csv($this->csvSk())])->assertForbidden();
        $this->withSession(['stub_role' => 'admin']);
        $this->post(route('master.unggah', ['tahun' => 2026, 'jenis' => 'bos']), ['berkas' => $csv($this->csvSk())])->assertNotFound();
        $this->postJson($url, [])->assertStatus(422)->assertJsonPath('pesan', 'Pilih berkas master kode.');
        $this->postJson(route('master.unggah', ['tahun' => 2025, 'jenis' => 'sk']), ['berkas' => $csv($this->csvSk())])->assertForbidden()->assertJsonPath('pesan', 'Tahun Anggaran 2025 terkunci — master kodenya hanya bisa dilihat.');

        $sebelum = MasterKode::kodeSk(2026);
        $this->postJson($url, ['berkas' => $csv($this->csvSk([], ['SK.99;Sasaran baru']))])
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('berkas.sheet', 'CSV')
            ->assertJsonPath('ringkasan.baru', 1)
            ->assertJsonPath('siap', true)
            ->assertJsonPath('disimpan', false)
            ->assertJsonPath('baris.'.count($sebelum).'.kode', 'SK.99')
            ->assertJsonPath('baris.'.count($sebelum).'.status', 'baru');
        $this->assertSame($sebelum, MasterKode::kodeSk(2026), 'Pratinjau tidak menyimpan.');

        $this->postJson($url, ['berkas' => $csv($this->csvSk([$this->skInduk() => null])), 'simpan' => 1])
            ->assertStatus(422)
            ->assertJsonPath('pesan', 'Master SK belum bisa disimpan.')
            ->assertJsonCount(1, 'kendala');

        $id = $this->postJson($url, ['berkas' => $csv($this->csvSk([], ['SK.99;Sasaran baru'])), 'simpan' => 1])
            ->assertCreated()
            ->assertJsonPath('disimpan', true)
            ->assertJsonPath('kendala', [])
            ->json('unggahan');
        $this->assertContains('SK.99', array_column(MasterKode::kodeSk(2026), 'kode'));
        $this->assertSame($id, app(MasterKodeRepository::class)->unggahan(2026)[0]['id']);
    }

    public function test_panel_simpan_master_sk(): void
    {
        $this->withSession(['stub_role' => 'admin']);
        $komponen = Livewire::test(UnggahMaster::class, ['tahun' => 2026])
            ->set('cakupan', 'sk')
            ->set('berkas', UploadedFile::fake()->createWithContent('sk.csv', $this->csvSk([$this->skInduk() => null])))
            ->call('lanjut')
            ->assertSet('tahap', 'pratinjau')
            ->assertSeeHtml('data-testid="kendala-simpan"')
            ->assertSeeHtml('wire:key="simpan-belum"');

        $komponen->call('kembali')
            ->set('berkas', UploadedFile::fake()->createWithContent('sk.csv', $this->csvSk([], ['SK.99;Sasaran baru'])))
            ->call('lanjut')
            ->assertDontSeeHtml('data-testid="kendala-simpan"')
            ->assertSeeHtml('data-testid="simpan-master"')
            ->call('simpan')
            ->assertSet('tahap', 'unggah')
            ->assertSet('pratinjau', [])
            ->assertDispatched('notify', message: 'Master kode TA 2026 tersimpan — SK: 1 baru, '.(count(MasterKode::kodeSk(2026)) - 1).' tetap.', type: 'success')
            ->assertDispatched('unggah-master-ditutup', dibatalkan: false);
        $this->assertContains('SK.99', array_column(MasterKode::kodeSk(2026), 'kode'));

    }
}
