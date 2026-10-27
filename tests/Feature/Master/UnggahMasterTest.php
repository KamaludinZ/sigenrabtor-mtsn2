<?php

namespace Tests\Feature\Master;

use App\Livewire\Master\Daftar;
use App\Livewire\Master\UnggahMaster;
use App\Support\Master\Impor\BerkasMaster;
use App\Support\MasterKode;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class UnggahMasterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['stub_role' => 'admin']);
    }

    private function berkas(string $fixture, ?string $nama = null): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama ?? $fixture, (string) file_get_contents(base_path("tests/fixtures/{$fixture}")));
    }

    public function test_aturan_cakupan_pengenalan_sheet_dan_ukuran(): void
    {
        $this->assertSame(['semua', 'sk', 'iksk', 'rktm', 'edm', 'budaya'], array_keys(BerkasMaster::cakupan()));
        $this->assertSame(['sk', 'iksk', 'rktm', 'edm', 'budaya'], BerkasMaster::jenisUntuk('semua'));
        $this->assertSame(['rktm'], BerkasMaster::jenisUntuk('rktm'));

        $lembar = BerkasMaster::lembar(base_path('tests/fixtures/master-kode-contoh.xlsx'), 'master.xlsx');
        $this->assertSame(['Perkin', 'SNP', 'EDM', '5 Budaya Mutu'], $lembar);
        $this->assertSame(['sk' => 'Perkin', 'iksk' => 'Perkin', 'rktm' => 'SNP', 'edm' => 'EDM', 'budaya' => '5 Budaya Mutu'], BerkasMaster::kenali($lembar, 'semua'));
        $this->assertSame(['sk' => 'Perkin', 'iksk' => 'Perkin', 'rktm' => 'Kode RKTM', 'edm' => null, 'budaya' => ' budaya  mutu '], BerkasMaster::kenali(['Perkin', 'Kode RKTM', ' budaya  mutu '], 'semua'));
        $this->assertSame(['rktm' => 'Lembar1'], BerkasMaster::kenali(['Lembar1'], 'rktm'), 'Satu jenis dari berkas satu sheet memakai sheet itu.');
        $this->assertSame(['rktm' => null], BerkasMaster::kenali(['Lembar1', 'Lembar2'], 'rktm'));
        $this->assertSame(['CSV'], BerkasMaster::lembar('/tidak/dipakai.csv', 'rktm.csv'));

        $this->assertSame('1 KB', BerkasMaster::ukuran(200));
        $this->assertSame('24 KB', BerkasMaster::ukuran(24 * 1024));
        $this->assertSame('1,5 MB', BerkasMaster::ukuran((int) (1.5 * 1024 * 1024)));
    }

    public function test_panel_dibuka_dari_halaman_dan_ditutup_lewat_event(): void
    {
        Livewire::test(Daftar::class)
            ->assertSeeHtml('wire:click="bukaUnggah"')
            ->call('bukaUnggah')
            ->assertSet('unggahTerbuka', true)
            ->assertSeeLivewire(UnggahMaster::class)
            ->dispatch('unggah-master-ditutup')
            ->assertSet('unggahTerbuka', false)
            ->call('bukaUnggah')
            ->set('tahun', '2025')
            ->assertSet('unggahTerbuka', false)
            ->assertDontSeeHtml('wire:click="bukaUnggah"')
            ->call('bukaUnggah')
            ->assertSet('unggahTerbuka', false);
    }

    public function test_berkas_master_lengkap_dikenali_per_jenis_dan_peringatan_ganti(): void
    {
        Livewire::test(UnggahMaster::class, ['tahun' => 2026])
            ->assertSet('cakupan', 'semua')
            ->assertSeeHtml('data-testid="akan-diganti"')
            ->assertSee('Master TA 2026 yang ada akan diganti isi berkas setelah pratinjau disetujui:')
            ->assertSeeHtmlInOrder(['data-jenis="sk"', 'SK — '.count(MasterKode::kodeSk(2026)).' kode · dipakai 3 kegiatan'])
            ->assertSee('Kode yang dipakai kegiatan tetapi tidak ada di berkas akan ditandai di pratinjau.')
            ->assertSeeHtml('data-testid="zona-berkas-master"')
            ->set('berkas', $this->berkas('master-kode-contoh.xlsx'))
            ->assertSet('galat', null)
            ->assertSet('terpilih.nama', 'master-kode-contoh.xlsx')
            ->assertSet('terpilih.lembar', ['Perkin', 'SNP', 'EDM', '5 Budaya Mutu'])
            ->assertSeeHtml('data-testid="berkas-master-terpilih"')
            ->assertSeeHtmlInOrder(['data-jenis="sk" data-dikenali="ya"', 'sheet Perkin', 'data-jenis="rktm" data-dikenali="ya"', 'sheet SNP', 'data-jenis="budaya" data-dikenali="ya"', 'sheet 5 Budaya Mutu'])
            ->assertSeeHtml('wire:key="lanjut-siap"')
            ->call('gantiBerkas')
            ->assertSet('terpilih', null)
            ->assertSet('berkas', null)
            ->assertSeeHtml('wire:key="lanjut-belum"');
    }

    public function test_satu_jenis_csv_dan_galat_berkas(): void
    {
        $komponen = Livewire::test(UnggahMaster::class, ['tahun' => 2026])
            ->set('cakupan', 'rktm')
            ->assertSee('Sheet SNP — standar, kegiatan, dan sub kegiatan.')
            ->set('berkas', UploadedFile::fake()->createWithContent('rktm.csv', "Kode;SNP;Kegiatan;Sub Kegiatan\n1.1.1;Standar Isi;Kurikulum;Workshop\n"))
            ->assertSet('galat', null)
            ->assertSeeHtml('data-jenis="rktm" data-dikenali="ya"')
            ->assertSee('sheet CSV')
            ->set('cakupan', 'semua')
            ->assertSet('galat', 'Berkas CSV hanya untuk satu jenis kode. Pilih jenisnya, atau unggah berkas .xlsx berisi semua sheet.')
            ->assertSeeHtml('wire:key="lanjut-belum"')
            ->set('cakupan', 'edm')
            ->assertSet('galat', null)
            ->set('cakupan', 'ngawur')
            ->assertSet('cakupan', 'semua');

        $komponen->set('berkas', $this->berkas('master-kode-asing.xlsx'))
            ->assertSet('galat', 'Tidak ada sheet yang dikenali. Beri nama sheet Perkin (SK & IKSK), SNP (RKTM), EDM, dan 5 Budaya Mutu seperti template.')
            ->assertSeeHtml('data-jenis="sk" data-dikenali="tidak"');

        $komponen->set('berkas', UploadedFile::fake()->create('master.pdf', 10, 'application/pdf'))
            ->assertSet('galat', 'Pilih berkas Excel (.xlsx, .xls) atau CSV.')
            ->assertSet('terpilih', null);
        $komponen->set('berkas', UploadedFile::fake()->create('besar.xlsx', BerkasMaster::MAKS_KB + 1))
            ->assertSet('galat', 'Ukuran berkas maksimal 5 MB.');
        $komponen->set('berkas', UploadedFile::fake()->createWithContent('rusak.xlsx', 'bukan excel'))
            ->assertSet('galat', 'Berkas "rusak.xlsx" tidak dapat dibaca sebagai Excel. Simpan ulang sebagai .xlsx lalu unggah lagi.');
    }

    public function test_khusus_admin_dan_tahun_tidak_terkunci(): void
    {
        Livewire::test(UnggahMaster::class, ['tahun' => 2025])->assertForbidden();
        Livewire::test(UnggahMaster::class, ['tahun' => 2031])->assertForbidden();

        session(['stub_role' => 'leading']);
        Livewire::test(UnggahMaster::class, ['tahun' => 2026])->assertForbidden();
    }

    public function test_tutup_memberi_tahu_halaman(): void
    {
        Livewire::test(UnggahMaster::class, ['tahun' => 2026])
            ->set('berkas', $this->berkas('master-kode-contoh.xlsx'))
            ->call('tutup')
            ->assertDispatched('unggah-master-ditutup')
            ->assertSet('berkas', null);
    }
}
