<?php

namespace Tests\Feature\Master;

use App\Livewire\Master\UnggahMaster;
use App\Support\Master\Impor\GagalPratinjauMaster;
use App\Support\Master\Impor\PemeriksaEdm;
use App\Support\Master\Impor\PratinjauMaster;
use App\Support\MasterKode;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

class PratinjauEdmTest extends TestCase
{
    /** @param  list<list<string|int|null>>  $baris */
    private function lembar(array $baris): Worksheet
    {
        $lembar = (new Spreadsheet)->getActiveSheet()->setTitle('EDM');
        $lembar->fromArray($baris, null, 'A1', true);

        return $lembar;
    }

    public function test_normalkan_kode_edm(): void
    {
        $this->assertSame('A1', PemeriksaEdm::normalkanKode('A1'));
        $this->assertSame('C3', PemeriksaEdm::normalkanKode(' c.3 '));
        $this->assertSame('B12', PemeriksaEdm::normalkanKode('B-012'));
        $this->assertNull(PemeriksaEdm::normalkanKode('AB1'));
        $this->assertNull(PemeriksaEdm::normalkanKode('1A'));
        $this->assertNull(PemeriksaEdm::normalkanKode('A'));
    }

    public function test_baca_edm_dengan_komponen_opsional(): void
    {
        $master = ['A' => 'Kedisiplinan', 'B' => 'Pengembangan Diri'];
        $baris = PemeriksaEdm::baca($this->lembar([
            ['Kode', 'Komponen', 'Uraian'],
            ['a.1', 'Kedisiplinan', 'Siswa religius'],
            ['A2', '', 'Guru hadir'],
            ['A3', 'Disiplin', 'Supervisi'],
            ['F1', '', 'Komponen baru tanpa nama'],
            ['A1', 'Kedisiplinan', 'Siswa religius'],
            ['A1', 'Kedisiplinan', 'Uraian lain'],
            ['XX', '', 'Kode salah'],
            ['B1', 'Pengembangan Diri', ''],
        ]), $master);

        $this->assertSame(['A1', 'A2', 'A3', 'F1', 'A1', 'XX', 'B1'], array_column($baris, 'kode'));
        $this->assertSame([2, 6], $baris[0]['baris']);
        $this->assertSame(['uraian' => 'Guru hadir', 'komponen' => 'Kedisiplinan'], $baris[1]['tampil'], 'Komponen kosong memakai nama master.');
        $this->assertSame(['Nama komponen A berbeda dengan baris 2 (Kedisiplinan).'], $baris[2]['pesan']);
        $this->assertNull($baris[2]['status']);
        $this->assertSame(['uraian' => 'Komponen baru tanpa nama', 'komponen' => 'F'], $baris[3]['tampil']);
        $this->assertSame(['Isi berbeda dengan baris 2 untuk kode yang sama.'], $baris[4]['pesan']);
        $this->assertSame(['Kode "XX" tidak berformat huruf + nomor (mis. A1, C3).'], $baris[5]['pesan']);
        $this->assertSame(['Uraian indikator kosong.'], $baris[6]['pesan']);

        // Tanpa kolom Komponen sama sekali.
        $tanpa = PemeriksaEdm::baca($this->lembar([['Kode', 'Uraian'], ['B1', 'Pengembangan']]), $master);
        $this->assertSame(['uraian' => 'Pengembangan', 'komponen' => 'Pengembangan Diri'], $tanpa[0]['tampil']);

        $this->expectException(GagalPratinjauMaster::class);
        $this->expectExceptionMessage('Judul kolom "Kode" dan "Uraian" tidak ditemukan di sheet EDM.');
        PemeriksaEdm::baca($this->lembar([['Nomor', 'Isi'], ['A1', 'x']]));
    }

    public function test_bandingkan_memakai_nama_komponen_dan_tampil_di_komponen(): void
    {
        $a1 = collect(MasterKode::kodeEdm(2026))->firstWhere('kode', 'A1');
        $baca = PemeriksaEdm::baca($this->lembar([
            ['Kode', 'Komponen', 'Uraian'],
            ['A1', 'Kedisiplinan', $a1['uraian']],
            ['A2', 'Disiplin Madrasah', 'Uraian baru'],
        ]), MasterKode::komponenEdm(2026));
        $hasil = collect(PratinjauMaster::bandingkan($baca, 'edm', 2026, ['A7' => 1]))->keyBy('kode');
        $this->assertSame('tetap', $hasil['A1']['status'], 'Huruf komponen master dibandingkan sebagai namanya.');
        $this->assertSame('berubah', $hasil['A2']['status']);
        $this->assertSame('Kedisiplinan', $hasil['A2']['lama']['komponen']);
        $this->assertSame('hilang', $hasil['A7']['status']);
        $this->assertSame('Kedisiplinan', $hasil['A7']['tampil']['komponen']);
        $this->assertSame(1, $hasil['A7']['dipakai']);

        session(['stub_role' => 'admin']);
        Livewire::test(UnggahMaster::class, ['tahun' => 2026])
            ->set('berkas', UploadedFile::fake()->createWithContent('master.xlsx', (string) file_get_contents(base_path('tests/fixtures/master-kode-contoh.xlsx'))))
            ->call('lanjut')
            ->set('tabPratinjau', 'edm')
            ->assertSet('pratinjau.edm.ringkasan', ['baru' => 0, 'berubah' => 0, 'tetap' => 6, 'hilang' => count(MasterKode::kodeEdm(2026)) - 6, 'gagal' => 0])
            ->assertSeeHtmlInOrder(['data-kode="A3" data-status="tetap"', 'data-kolom="komponen"', 'Kedisiplinan', 'dipakai 1 kegiatan'])
            ->assertSeeHtmlInOrder(['data-kode="A7" data-status="hilang"', 'padahal dipakai 1 kegiatan TA 2026']);
    }
}
