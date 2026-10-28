<?php

namespace Tests\Feature\Master;

use App\Contracts\MasterKodeRepository;
use App\Livewire\Master\Daftar;
use App\Livewire\Master\UnggahMaster;
use App\Support\Master\Impor\SimpanMasterKode;
use App\Support\MasterKode;
use App\Support\Pengaturan\PengaturanBerubah;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class IntegrasiPratinjauMasterTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    private function csvSk(array $tambah = []): string
    {
        return implode("\n", ['Kode SK;Uraian SK', ...array_map(fn (array $s) => "{$s['kode']};{$s['uraian']}", MasterKode::kodeSk(2026)), ...$tambah])."\n";
    }

    private function master(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('master.xlsx', (string) file_get_contents(base_path('tests/fixtures/master-kode-contoh.xlsx')));
    }

    public function test_ringkasan_khusus_jenis_di_tab_pratinjau(): void
    {
        $this->withSession(['stub_role' => 'admin']);
        $komponen = Livewire::test(UnggahMaster::class, ['tahun' => 2026])
            ->set('berkas', $this->master())
            ->call('lanjut')
            ->assertSet('tabPratinjau', 'sk')
            ->assertDontSeeHtml('data-testid="ringkasan-jenis"');

        $pratinjau = $komponen->get('pratinjau');
        foreach (['iksk' => 'IKSK per SK induk', 'rktm' => 'Struktur standar (SNP)', 'edm' => 'Komponen yang tersimpan', 'budaya' => 'Jumlah butir'] as $jenis => $judul) {
            $ringkasan = SimpanMasterKode::ringkasanJenis($jenis, $pratinjau[$jenis]['baris'], 2026);
            $komponen->set('tabPratinjau', $jenis)
                ->assertSeeHtml('data-testid="ringkasan-jenis"')
                ->assertSee($judul)
                ->assertSee($ringkasan['butir'][0]);
        }
        $this->assertSame(['5 butir tersimpan (baku 5)'], SimpanMasterKode::ringkasanJenis('budaya', $pratinjau['budaya']['baris'], 2026)['butir']);
        $this->assertMatchesRegularExpression('/^SK\.\d+ — \d+ indikator$/', SimpanMasterKode::ringkasanJenis('iksk', $pratinjau['iksk']['baris'], 2026)['butir'][0]);
        $this->assertMatchesRegularExpression('/^A Kedisiplinan — \d+ indikator$/', SimpanMasterKode::ringkasanJenis('edm', $pratinjau['edm']['baris'], 2026)['butir'][0]);
    }

    public function test_master_berubah_sejak_pratinjau_tidak_tertimpa(): void
    {
        $this->withSession(['stub_role' => 'admin']);
        $komponen = Livewire::test(UnggahMaster::class, ['tahun' => 2026])
            ->set('cakupan', 'sk')
            ->set('berkas', UploadedFile::fake()->createWithContent('sk.csv', $this->csvSk(['SK.99;Sasaran baru'])))
            ->call('lanjut');
        $sidik = $komponen->get('sidikMaster');
        $this->assertSame(SimpanMasterKode::sidik(2026, ['sk']), $sidik);

        // Admin lain menambah SK.98 sementara pratinjau masih terbuka.
        app(MasterKodeRepository::class)->ganti(2026, ['kode_sk' => [...MasterKode::kodeSk(2026), ['kode' => 'SK.98', 'uraian' => 'Dari tab lain']]]);
        $komponen->call('simpan')
            ->assertDispatched('notify', message: 'Master kode TA 2026 baru saja diubah di tempat lain — pratinjau sudah diperbarui; periksa lagi lalu simpan.', type: 'error')
            ->assertSet('tahap', 'pratinjau')
            ->assertSeeHtml('data-kode="SK.98" data-status="hilang"');
        $this->assertNotSame($sidik, $komponen->get('sidikMaster'));
        $this->assertContains('SK.98', array_column(MasterKode::kodeSk(2026), 'kode'), 'Belum tertimpa.');

        $komponen->call('simpan')->assertDispatched('notify', type: 'success');
        $this->assertSame(['SK.99', false], [collect(MasterKode::kodeSk(2026))->last()['kode'], in_array('SK.98', array_column(MasterKode::kodeSk(2026), 'kode'), true)]);

        $this->expectException(PengaturanBerubah::class);
        $path = tempnam(sys_get_temp_dir(), 'sk');
        file_put_contents($path, $this->csvSk());
        SimpanMasterKode::simpan($path, 'sk.csv', 'sk', 2026, self::ADMIN, $sidik);
    }

    public function test_endpoint_sidik_dan_unggahan_terakhir_di_halaman(): void
    {
        $this->withSession(['stub_role' => 'admin']);
        $url = route('master.unggah', ['tahun' => 2026, 'jenis' => 'sk']);
        $csv = fn () => UploadedFile::fake()->createWithContent('sk.csv', $this->csvSk(['SK.99;Sasaran baru']));

        $sidik = $this->postJson($url, ['berkas' => $csv()])->assertOk()->json('sidik');
        $this->assertSame(SimpanMasterKode::sidik(2026, ['sk']), $sidik);
        app(MasterKodeRepository::class)->ganti(2026, ['kode_sk' => [...MasterKode::kodeSk(2026), ['kode' => 'SK.98', 'uraian' => 'Dari tab lain']]]);
        $this->postJson($url, ['berkas' => $csv(), 'simpan' => 1, 'sidik' => $sidik])
            ->assertStatus(409)
            ->assertJsonPath('pesan', 'Master kode TA 2026 baru saja diubah di tempat lain. Minta pratinjau lagi lalu simpan dengan sidik yang baru.');

        Livewire::test(Daftar::class)->assertDontSeeHtml('data-testid="unggahan-terakhir"');
        $baru = $this->postJson($url, ['berkas' => $csv()])->json('sidik');
        $this->postJson($url, ['berkas' => $csv(), 'simpan' => 1, 'sidik' => $baru])->assertCreated();
        Livewire::test(Daftar::class)
            ->assertSeeHtml('data-testid="unggahan-terakhir"')
            ->assertSee('oleh Koordinator PUSKOM — sk.csv');
    }
}
