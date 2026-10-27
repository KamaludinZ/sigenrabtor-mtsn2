<?php

namespace Tests\Feature\Master;

use App\Contracts\RiwayatPengaturanRepository;
use App\Livewire\Master\Daftar;
use App\Livewire\Master\UnggahMaster;
use App\Support\Master\JenisKode;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/** Unggahan master bisa dibatalkan kapan saja tanpa mengubah data (PRD §14 "dapat dibatalkan sebelum masuk database"). */
class BatalUnggahMasterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['stub_role' => 'admin']);
    }

    private function contoh(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('master.xlsx', (string) file_get_contents(base_path('tests/fixtures/master-kode-contoh.xlsx')));
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function master(): array
    {
        return array_map(fn (string $jenis) => JenisKode::baris($jenis, 2026), array_combine(array_keys(JenisKode::DAFTAR), array_keys(JenisKode::DAFTAR)));
    }

    public function test_batal_dari_pratinjau_minta_konfirmasi_lalu_membuang_berkas(): void
    {
        $sebelum = $this->master();
        $riwayat = count(app(RiwayatPengaturanRepository::class)->semua());

        $komponen = Livewire::test(UnggahMaster::class, ['tahun' => 2026])
            ->set('berkas', $this->contoh())
            ->call('lanjut');
        $path = $komponen->get('berkas')->getRealPath();
        $this->assertFileExists($path);
        $perubahan = $komponen->instance()->ringkasanPerubahan();
        $this->assertSame(0, $perubahan['baru']);
        $this->assertGreaterThan(0, $perubahan['hilang']);

        $komponen->call('tutup')
            ->assertSet('konfirmasiBatal', true)
            ->assertSet('tahap', 'pratinjau')
            ->assertNotDispatched('unggah-master-ditutup')
            ->assertSeeHtml('data-testid="konfirmasi-batal-unggah"')
            ->assertSee('Perubahan dari berkas ini ('.$perubahan['hilang'].' kode hilang) tidak disimpan; master TA 2026 tetap seperti sekarang.')
            ->set('konfirmasiBatal', false)
            ->assertDontSeeHtml('data-testid="konfirmasi-batal-unggah"')
            ->call('tutup')
            ->call('batalkan')
            ->assertDispatched('unggah-master-ditutup', dibatalkan: true)
            ->assertSet('tahap', 'unggah')
            ->assertSet('terpilih', null)
            ->assertSet('pratinjau', [])
            ->assertSet('berkas', null)
            ->assertSet('konfirmasiBatal', false);

        $this->assertFileDoesNotExist($path, 'Berkas sementara dibuang.');
        $this->assertSame($sebelum, $this->master(), 'Master tidak berubah.');
        $this->assertCount($riwayat, app(RiwayatPengaturanRepository::class)->semua(), 'Batal tidak tercatat sebagai perubahan.');
    }

    public function test_tanpa_perubahan_atau_tanpa_berkas_langsung_ditutup(): void
    {
        Livewire::test(UnggahMaster::class, ['tahun' => 2026])
            ->call('tutup')
            ->assertDispatched('unggah-master-ditutup', dibatalkan: false);

        Livewire::test(UnggahMaster::class, ['tahun' => 2026])
            ->set('berkas', $this->contoh())
            ->call('tutup')
            ->assertSet('konfirmasiBatal', false)
            ->assertDispatched('unggah-master-ditutup', dibatalkan: true);

        // Berkas identik dengan master 5 Budaya Mutu: tidak ada yang dibuang, tanpa konfirmasi.
        $buku = new Spreadsheet;
        $buku->getActiveSheet()->setTitle('5 Budaya Mutu')->fromArray([['No', 'Budaya Mutu'], ...array_map(fn ($b) => [$b['nomor'], $b['nama']], JenisKode::baris('budaya', 2026))]);
        $path = tempnam(sys_get_temp_dir(), 'bm');
        (new Xlsx($buku))->save($path);
        $isi = (string) file_get_contents($path);
        @unlink($path);

        Livewire::test(UnggahMaster::class, ['tahun' => 2026])
            ->set('cakupan', 'budaya')
            ->set('berkas', UploadedFile::fake()->createWithContent('budaya.xlsx', $isi))
            ->call('lanjut')
            ->assertSet('pratinjau.budaya.ringkasan.tetap', 5)
            ->call('tutup')
            ->assertSet('konfirmasiBatal', false)
            ->assertDispatched('unggah-master-ditutup', dibatalkan: true);
    }

    public function test_kembali_menutup_konfirmasi_dan_halaman_memberi_tahu(): void
    {
        Livewire::test(UnggahMaster::class, ['tahun' => 2026])
            ->set('berkas', $this->contoh())
            ->call('lanjut')
            ->call('tutup')
            ->assertSet('konfirmasiBatal', true)
            ->call('kembali')
            ->assertSet('konfirmasiBatal', false)
            ->assertSet('tahap', 'unggah')
            ->assertSet('terpilih.nama', 'master.xlsx');

        Livewire::test(Daftar::class)
            ->call('bukaUnggah')
            ->assertSeeHtmlInOrder(['data-tahun="2025"', 'disabled', 'title="Tutup panel unggah dulu untuk melihat tahun lain"'])
            ->dispatch('unggah-master-ditutup', dibatalkan: true)
            ->assertSet('unggahTerbuka', false)
            ->assertDispatched('notify', message: 'Unggahan dibatalkan — master kode TA 2026 tidak berubah.', type: 'info')
            ->assertDontSeeHtml('Tutup panel unggah dulu');

        Livewire::test(Daftar::class)
            ->call('bukaUnggah')
            ->dispatch('unggah-master-ditutup', dibatalkan: false)
            ->assertNotDispatched('notify');
    }

    public function test_penjaga_pindah_halaman_terpasang(): void
    {
        Livewire::test(UnggahMaster::class, ['tahun' => 2026])
            ->assertSeeHtml('x-on:livewire:navigate.document="if ($wire.terpilih && ! confirm(\'Unggahan master kode belum disimpan dan akan dibatalkan.')
            ->assertSeeHtml('x-on:beforeunload.window="if ($wire.terpilih)');
    }
}
