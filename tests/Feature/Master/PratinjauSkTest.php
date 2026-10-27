<?php

namespace Tests\Feature\Master;

use App\Livewire\Master\UnggahMaster;
use App\Support\Master\Impor\GagalPratinjauMaster;
use App\Support\Master\Impor\PemeriksaSk;
use App\Support\Master\Impor\PratinjauMaster;
use App\Support\MasterKode;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PratinjauSkTest extends TestCase
{
    /** @param  list<list<string|int|null>>  $baris */
    private function lembar(array $baris, string $nama = 'Perkin'): Worksheet
    {
        $lembar = (new Spreadsheet)->getActiveSheet()->setTitle($nama);
        $lembar->fromArray($baris, null, 'A1', true);

        return $lembar;
    }

    private function uraian(string $kode): string
    {
        return array_column(MasterKode::kodeSk(2026), 'uraian', 'kode')[$kode];
    }

    public function test_normalkan_kode_sk(): void
    {
        $this->assertSame('SK.10', PemeriksaSk::normalkanKode('SK.10'));
        $this->assertSame('SK.10', PemeriksaSk::normalkanKode(' sk 10 '));
        $this->assertSame('SK.9', PemeriksaSk::normalkanKode('SK-09'));
        $this->assertSame('SK.12', PemeriksaSk::normalkanKode('SK12'));
        $this->assertNull(PemeriksaSk::normalkanKode('IKSK 10.2'));
        $this->assertNull(PemeriksaSk::normalkanKode('SK.10.1'));
        $this->assertNull(PemeriksaSk::normalkanKode(''));
    }

    public function test_baca_menggabungkan_baris_berulang_dan_menandai_galat(): void
    {
        $baris = PemeriksaSk::baca($this->lembar([
            ['Daftar Perkin TA 2026'],
            [],
            ['Kode SK', 'Uraian SK', 'Kode IKSK', 'Uraian IKSK'],
            ['SK.10', 'Layanan pendidikan', 'IKSK 10.1', 'Nilai asesmen'],
            ['sk 10', 'Layanan pendidikan', 'IKSK 10.2', 'Rencana kerja'],
            [null, null, null, null],
            ['SK.10', 'Uraian lain', 'IKSK 10.3', 'Survei'],
            ['SK.X', 'Kode salah', 'IKSK 1', '-'],
            ['', 'Tanpa kode', '', ''],
            ['SK.20', '', 'IKSK 20.1', 'Baru'],
            ['SK.21', 'Sasaran baru', 'IKSK 21.1', 'Baru'],
        ]));

        $this->assertSame([
            ['baris' => [4, 5], 'kode' => 'SK.10', 'tampil' => ['uraian' => 'Layanan pendidikan'], 'status' => null, 'pesan' => []],
            ['baris' => [7], 'kode' => 'SK.10', 'tampil' => ['uraian' => 'Uraian lain'], 'status' => 'gagal', 'pesan' => ['Uraian berbeda dengan baris 4 untuk kode yang sama.']],
            ['baris' => [8], 'kode' => 'SK.X', 'tampil' => ['uraian' => 'Kode salah'], 'status' => 'gagal', 'pesan' => ['Kode "SK.X" tidak berformat SK.n (mis. SK.10).']],
            ['baris' => [9], 'kode' => '', 'tampil' => ['uraian' => 'Tanpa kode'], 'status' => 'gagal', 'pesan' => ['Kode SK kosong.']],
            ['baris' => [10], 'kode' => 'SK.20', 'tampil' => ['uraian' => ''], 'status' => 'gagal', 'pesan' => ['Uraian SK kosong.']],
            ['baris' => [11], 'kode' => 'SK.21', 'tampil' => ['uraian' => 'Sasaran baru'], 'status' => null, 'pesan' => []],
        ], $baris);

        $this->expectException(GagalPratinjauMaster::class);
        $this->expectExceptionMessage('Judul kolom "Kode SK" dan "Uraian SK" tidak ditemukan di sheet Perkin.');
        PemeriksaSk::baca($this->lembar([['Nomor', 'Nama'], ['1', 'A']]));
    }

    public function test_bandingkan_dengan_master_tahun_ini(): void
    {
        $baca = PemeriksaSk::baca($this->lembar([
            ['Kode SK', 'Uraian SK'],
            ['SK.8', $this->uraian('SK.8')],
            ['SK.10', 'Uraian SK.10 yang diperbarui'],
            ['SK.30', 'Sasaran yang baru ada'],
            ['SK.Y', 'Salah'],
        ]));
        $hasil = collect(PratinjauMaster::bandingkan($baca, 'sk', 2026, ['SK.10' => 2, 'SK.11' => 1]))->keyBy(fn ($b) => $b['status'].':'.$b['kode']);

        $this->assertSame(['tetap:SK.8', 'berubah:SK.10', 'baru:SK.30', 'gagal:SK.Y'], $hasil->keys()->take(4)->all());
        $this->assertSame(['uraian' => $this->uraian('SK.10')], $hasil['berubah:SK.10']['lama']);
        $this->assertSame(2, $hasil['berubah:SK.10']['dipakai']);
        $this->assertNull($hasil['tetap:SK.8']['lama']);

        $hilang = $hasil->filter(fn ($b) => $b['status'] === 'hilang');
        $this->assertCount(count(MasterKode::kodeSk(2026)) - 2, $hilang);
        $this->assertSame(['uraian' => $this->uraian('SK.11')], $hilang['hilang:SK.11']['tampil']);
        $this->assertSame(['Tidak ada di berkas, padahal dipakai 1 kegiatan TA 2026 — periksa sebelum menyimpan.'], $hilang['hilang:SK.11']['pesan']);
        $this->assertSame(['Tidak ada di berkas.'], $hilang['hilang:SK.12']['pesan']);
        $this->assertSame([], $hilang['hilang:SK.12']['baris']);
    }

    public function test_pratinjau_berkas_contoh_dan_alur_komponen(): void
    {
        session(['stub_role' => 'admin']);
        $berkas = UploadedFile::fake()->createWithContent('master-kode-contoh.xlsx', (string) file_get_contents(base_path('tests/fixtures/master-kode-contoh.xlsx')));
        $jumlahSk = count(MasterKode::kodeSk(2026));

        $komponen = Livewire::test(UnggahMaster::class, ['tahun' => 2026])
            ->set('berkas', $berkas)
            ->call('lanjut')
            ->assertSet('tahap', 'pratinjau')
            ->assertSet('tabPratinjau', 'sk')
            ->assertSet('pratinjau.sk.sheet', 'Perkin')
            ->assertSet('pratinjau.sk.galat', null)
            // Berkas contoh memuat SK.8, SK.9, SK.10 (uraian sama dengan master); SK lain hilang.
            ->assertSet('pratinjau.sk.ringkasan', ['baru' => 0, 'berubah' => 0, 'tetap' => 3, 'hilang' => $jumlahSk - 3, 'gagal' => 0])
            ->assertSeeHtml('data-testid="pratinjau-master"')
            ->assertSeeHtmlInOrder(['data-kode="SK.8" data-status="tetap"', 'data-kode="SK.10" data-status="tetap"', 'dipakai 2 kegiatan', 'data-kode="SK.11" data-status="hilang"', 'padahal dipakai 1 kegiatan TA 2026'])
            ->assertSee('Baris 2')
            ->set('saring', 'hilang')
            ->assertDontSeeHtml('data-kode="SK.8"')
            ->assertSeeHtml('data-kode="SK.19" data-status="hilang"')
            ->set('saring', 'ngawur')
            ->assertSet('saring', 'semua')
            ->set('tabPratinjau', 'budaya')
            ->assertSeeHtml('data-kode="1" data-status="tetap"')
            ->set('tabPratinjau', 'tidak-ada')
            ->assertSet('tabPratinjau', 'sk');
        $this->assertCount(3 + $jumlahSk - 3, $komponen->instance()->barisPratinjau());
        $this->assertCount($jumlahSk, MasterKode::kodeSk(2026), 'Pratinjau tidak mengubah master.');

        $komponen->call('kembali')
            ->assertSet('tahap', 'unggah')
            ->assertSet('pratinjau', [])
            ->assertSet('terpilih.nama', 'master-kode-contoh.xlsx')
            ->call('lanjut')
            ->assertSet('tahap', 'pratinjau')
            ->call('gantiBerkas')
            ->assertSet('tahap', 'unggah')
            ->assertSet('terpilih', null);
    }

    public function test_sheet_tanpa_judul_kolom_tampil_sebagai_galat_tab(): void
    {
        session(['stub_role' => 'admin']);
        $buku = new Spreadsheet;
        $buku->getActiveSheet()->setTitle('Perkin')->fromArray([['Nomor', 'Nama'], ['1', 'A']]);
        $path = tempnam(sys_get_temp_dir(), 'sk').'.xlsx';
        (new Xlsx($buku))->save($path);

        Livewire::test(UnggahMaster::class, ['tahun' => 2026])
            ->set('cakupan', 'sk')
            ->set('berkas', UploadedFile::fake()->createWithContent('sk.xlsx', (string) file_get_contents($path)))
            ->call('lanjut')
            ->assertSet('pratinjau.sk.galat', 'Judul kolom "Kode SK" dan "Uraian SK" tidak ditemukan di sheet Perkin.')
            ->assertSeeHtml('data-testid="galat-pratinjau"');
        @unlink($path);
    }
}
