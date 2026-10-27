<?php

namespace Tests\Feature\Master;

use App\Livewire\Master\UnggahMaster;
use App\Support\Master\Impor\GagalPratinjauMaster;
use App\Support\Master\Impor\PemeriksaRktm;
use App\Support\Master\Impor\PratinjauMaster;
use App\Support\MasterKode;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

class PratinjauRktmTest extends TestCase
{
    private const JUDUL = ['Kode', 'SNP', 'Kegiatan', 'Sub Kegiatan'];

    /** @param  list<list<string|int|float|null>>  $baris */
    private function lembar(array $baris): Worksheet
    {
        $lembar = (new Spreadsheet)->getActiveSheet()->setTitle('SNP');
        $lembar->fromArray($baris, null, 'A1', true);

        return $lembar;
    }

    public function test_normalkan_kode_rktm(): void
    {
        $this->assertSame('6.17.12', PemeriksaRktm::normalkanKode('6.17.12'));
        $this->assertSame('6.17.12', PemeriksaRktm::normalkanKode(' 06 . 17 . 12 '));
        $this->assertSame('1.1.2', PemeriksaRktm::normalkanKode('1,1,2'));
        $this->assertNull(PemeriksaRktm::normalkanKode('6.17'));
        $this->assertNull(PemeriksaRktm::normalkanKode('6.17.12.1'));
        $this->assertNull(PemeriksaRktm::normalkanKode('A.1.2'));
    }

    public function test_baca_rktm_isi_ke_bawah_dan_galat(): void
    {
        $baris = PemeriksaRktm::baca($this->lembar([
            self::JUDUL,
            [null, 'STANDAR ISI', 'Kurikulum', null],
            ['1.1.1', null, null, 'Workshop kurikulum'],
            ['1.1.2', '', '', 'Program tahunan'],
            ['1.2.1', null, 'Pembelajaran', 'Supervisi'],
            ['1.2.1', null, null, 'Supervisi'],
            ['1.2.1', null, null, 'Supervisi lain'],
            ['1.3.1', 'STANDAR LAIN', 'Lain', 'SNP tidak sejalan'],
            [6.17, 'STANDAR PENGELOLAAN', 'Kegiatan', 'Terbaca angka'],
            ['2.1.1', 'STANDAR PROSES', 'Proses', ''],
            ['', 'STANDAR PROSES', 'Proses', 'Tanpa kode'],
        ]));

        $this->assertSame(['1.1.1', '1.1.2', '1.2.1', '1.2.1', '1.3.1', '6.17', '2.1.1', ''], array_column($baris, 'kode'));
        $this->assertSame(['sub_kegiatan' => 'Workshop kurikulum', 'kegiatan' => 'Kurikulum', 'snp' => 'STANDAR ISI'], $baris[0]['tampil']);
        $this->assertSame(['sub_kegiatan' => 'Program tahunan', 'kegiatan' => 'Kurikulum', 'snp' => 'STANDAR ISI'], $baris[1]['tampil'], 'SNP & kegiatan kosong diisi dari baris di atasnya.');
        $this->assertSame(['sub_kegiatan' => 'Supervisi', 'kegiatan' => 'Pembelajaran', 'snp' => 'STANDAR ISI'], $baris[2]['tampil']);
        $this->assertSame([5, 6], $baris[2]['baris']);
        $this->assertSame(['gagal', ['Isi berbeda dengan baris 5 untuk kode yang sama.']], [$baris[3]['status'], $baris[3]['pesan']]);
        $this->assertNull($baris[4]['status']);
        $this->assertSame(['SNP berbeda dengan kode 1.x di baris 3 (STANDAR ISI).'], $baris[4]['pesan']);
        $this->assertSame(['Kode "6.17" tidak berformat n.n.n (mis. 6.17.12) — format sel Teks agar tidak terbaca angka.'], $baris[5]['pesan']);
        $this->assertSame(['Sub kegiatan kosong.'], $baris[6]['pesan']);
        $this->assertSame(['Kode RKTM kosong.'], $baris[7]['pesan']);

        $kosong = PemeriksaRktm::baca($this->lembar([self::JUDUL, ['1.1.1', '', '', 'Tanpa SNP & kegiatan']]));
        $this->assertSame(['Standar (SNP) kosong.', 'Kegiatan kosong.'], $kosong[0]['pesan']);

        $this->expectException(GagalPratinjauMaster::class);
        $this->expectExceptionMessage('Judul kolom "Kode", "SNP", "Kegiatan", dan "Sub Kegiatan" tidak ditemukan di sheet SNP.');
        PemeriksaRktm::baca($this->lembar([['Kode', 'Uraian'], ['1.1.1', 'x']]));
    }

    public function test_bandingkan_dan_tampil_di_komponen(): void
    {
        $lama = collect(MasterKode::kodeRktm(2026))->firstWhere('kode', '6.17.12');
        $baca = PemeriksaRktm::baca($this->lembar([
            self::JUDUL,
            ['6.17.12', $lama['snp'], $lama['kegiatan'], 'Sub kegiatan yang diubah'],
            ['9.1.1', 'STANDAR BARU', 'Kegiatan baru', 'Sub baru'],
        ]));
        $hasil = collect(PratinjauMaster::bandingkan($baca, 'rktm', 2026, ['6.17.12' => 1]))->keyBy('kode');
        $this->assertSame('berubah', $hasil['6.17.12']['status']);
        $this->assertSame(['sub_kegiatan' => $lama['sub_kegiatan'], 'kegiatan' => $lama['kegiatan'], 'snp' => $lama['snp']], $hasil['6.17.12']['lama']);
        $this->assertSame('baru', $hasil['9.1.1']['status']);
        $this->assertSame('hilang', $hasil['6.19.1']['status']);

        session(['stub_role' => 'admin']);
        Livewire::test(UnggahMaster::class, ['tahun' => 2026])
            ->set('berkas', UploadedFile::fake()->createWithContent('master.xlsx', (string) file_get_contents(base_path('tests/fixtures/master-kode-contoh.xlsx'))))
            ->call('lanjut')
            ->set('tabPratinjau', 'rktm')
            ->assertSet('pratinjau.rktm.ringkasan', ['baru' => 0, 'berubah' => 0, 'tetap' => 8, 'hilang' => count(MasterKode::kodeRktm(2026)) - 8, 'gagal' => 0])
            ->assertSeeHtmlInOrder(['data-kode="1.1.2" data-status="tetap"', 'Kegiatan Penyusunan Program Tahunan', 'data-kolom="kegiatan"', 'Pengelolaan Kurikulum 2013', 'data-kolom="snp"', 'PENGEMBANGAN STANDAR ISI'])
            ->assertSeeHtmlInOrder(['data-kode="6.17.12" data-status="hilang"', 'padahal dipakai 1 kegiatan TA 2026']);
    }
}
