<?php

namespace Tests\Feature\Master;

use App\Livewire\Master\UnggahMaster;
use App\Support\Master\Impor\GagalPratinjauMaster;
use App\Support\Master\Impor\PemeriksaIksk;
use App\Support\Master\Impor\PratinjauMaster;
use App\Support\MasterKode;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PratinjauIkskTest extends TestCase
{
    private const JUDUL = ['Kode SK', 'Uraian SK', 'Kode IKSK', 'Uraian IKSK'];

    /** @param  list<list<string|int|null>>  $baris */
    private function lembar(array $baris): Worksheet
    {
        $lembar = (new Spreadsheet)->getActiveSheet()->setTitle('Perkin');
        $lembar->fromArray($baris, null, 'A1', true);

        return $lembar;
    }

    /** @return array<string, string> */
    private function iksk(string $kode): array
    {
        return collect(MasterKode::kodeIksk(null, 2026))->firstWhere('kode', $kode);
    }

    /** @param  list<list<string|int|null>>  $baris */
    private function unggah(array $baris, string $cakupan = 'semua'): Testable
    {
        session(['stub_role' => 'admin']);
        $buku = new Spreadsheet;
        $buku->getActiveSheet()->setTitle('Perkin')->fromArray($baris, null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'iksk');
        (new Xlsx($buku))->save($path);
        $isi = (string) file_get_contents($path);
        @unlink($path);

        return Livewire::test(UnggahMaster::class, ['tahun' => 2026])
            ->set('cakupan', $cakupan)
            ->set('berkas', UploadedFile::fake()->createWithContent('perkin.xlsx', $isi))
            ->call('lanjut');
    }

    public function test_normalkan_kode_iksk(): void
    {
        $this->assertSame('IKSK 10.2', PemeriksaIksk::normalkanKode('IKSK 10.2'));
        $this->assertSame('IKSK 10.15', PemeriksaIksk::normalkanKode(' iksk10-15 '));
        $this->assertSame('IKSK 9.5', PemeriksaIksk::normalkanKode('IKSK 09.05'));
        $this->assertNull(PemeriksaIksk::normalkanKode('SK.10'));
        $this->assertNull(PemeriksaIksk::normalkanKode('IKSK 10'));
    }

    public function test_baca_iksk_dari_perkin(): void
    {
        $baris = PemeriksaIksk::baca($this->lembar([
            self::JUDUL,
            ['SK.10', 'Layanan', 'IKSK 10.2', 'Rencana kerja'],
            ['SK.10', 'Layanan', 'iksk 10.2', 'Rencana kerja'],
            ['SK.10', 'Layanan', 'IKSK 10.2', 'Uraian lain'],
            ['SK.11', 'Guru', 'IKSK 10.9', 'Nomor tidak sejalan'],
            ['SK.12', 'Perencanaan', null, null],
            ['', '', 'IKSK 12.1', 'Tanpa SK'],
            ['SK.12', '', 'IKSK.12', 'Kode salah'],
            ['SK.13', '', 'IKSK 13.1', ''],
        ]));

        $this->assertSame([
            ['baris' => [2, 3], 'kode' => 'IKSK 10.2', 'tampil' => ['kode_sk' => 'SK.10', 'uraian' => 'Rencana kerja'], 'status' => null, 'pesan' => []],
            ['baris' => [4], 'kode' => 'IKSK 10.2', 'tampil' => ['kode_sk' => 'SK.10', 'uraian' => 'Uraian lain'], 'status' => 'gagal', 'pesan' => ['SK induk atau uraian berbeda dengan baris 2 untuk kode yang sama.']],
            ['baris' => [5], 'kode' => 'IKSK 10.9', 'tampil' => ['kode_sk' => 'SK.11', 'uraian' => 'Nomor tidak sejalan'], 'status' => null, 'pesan' => ['Nomor IKSK 10.9 tidak sejalan dengan SK induk SK.11 — periksa lagi.']],
            ['baris' => [7], 'kode' => 'IKSK 12.1', 'tampil' => ['kode_sk' => '', 'uraian' => 'Tanpa SK'], 'status' => 'gagal', 'pesan' => ['Kode SK induk kosong.']],
            ['baris' => [8], 'kode' => 'IKSK.12', 'tampil' => ['kode_sk' => 'SK.12', 'uraian' => 'Kode salah'], 'status' => 'gagal', 'pesan' => ['Kode "IKSK.12" tidak berformat IKSK n.m (mis. IKSK 10.2).']],
            ['baris' => [9], 'kode' => 'IKSK 13.1', 'tampil' => ['kode_sk' => 'SK.13', 'uraian' => ''], 'status' => 'gagal', 'pesan' => ['Uraian IKSK kosong.']],
        ], $baris);

        $this->expectException(GagalPratinjauMaster::class);
        $this->expectExceptionMessage('Judul kolom "Kode IKSK", "Kode SK", dan "Uraian IKSK" tidak ditemukan di sheet Perkin.');
        PemeriksaIksk::baca($this->lembar([['Kode SK', 'Uraian SK'], ['SK.10', 'Layanan']]));
    }

    public function test_bandingkan_dan_induk_sk(): void
    {
        $lama = $this->iksk('IKSK 10.6');
        $baca = PemeriksaIksk::baca($this->lembar([
            self::JUDUL,
            ['SK.10', 'x', 'IKSK 10.2', $this->iksk('IKSK 10.2')['uraian']],
            ['SK.10', 'x', 'IKSK 10.6', 'Uraian 10.6 baru'],
            ['SK.40', 'x', 'IKSK 40.1', 'Induk tidak ada'],
            ['SK.10', 'x', 'IKSK 10.99', 'Indikator baru'],
        ]));
        $banding = PratinjauMaster::bandingkan($baca, 'iksk', 2026, ['IKSK 10.6' => 1, 'IKSK 11.5' => 1]);
        $baris = collect(PratinjauMaster::periksaInduk($banding, null, 2026))->keyBy('kode');

        $this->assertSame('tetap', $baris['IKSK 10.2']['status']);
        $this->assertSame('berubah', $baris['IKSK 10.6']['status']);
        $this->assertSame(['kode_sk' => 'SK.10', 'uraian' => $lama['uraian']], $baris['IKSK 10.6']['lama']);
        $this->assertSame(1, $baris['IKSK 10.6']['dipakai']);
        $this->assertSame('baru', $baris['IKSK 10.99']['status']);
        $this->assertSame('gagal', $baris['IKSK 40.1']['status']);
        $this->assertSame(['SK induk SK.40 tidak ada di master SK TA 2026.'], $baris['IKSK 40.1']['pesan']);
        $this->assertSame('hilang', $baris['IKSK 11.5']['status']);
        $this->assertSame(['kode_sk' => 'SK.11', 'uraian' => $this->iksk('IKSK 11.5')['uraian']], $baris['IKSK 11.5']['tampil']);

        // Induk dari SK berkas yang sama: SK yang gagal/hilang tidak dihitung.
        $sk = ['galat' => null, 'baris' => [['kode' => 'SK.10', 'status' => 'tetap'], ['kode' => 'SK.40', 'status' => 'gagal'], ['kode' => 'SK.11', 'status' => 'hilang']]];
        $dariBerkas = collect(PratinjauMaster::periksaInduk($banding, $sk, 2026))->keyBy('kode');
        $this->assertSame(['SK induk SK.40 tidak ada di SK berkas ini.'], $dariBerkas['IKSK 40.1']['pesan']);
        $this->assertSame('tetap', $dariBerkas['IKSK 10.2']['status']);
    }

    public function test_pratinjau_iksk_di_komponen(): void
    {
        $jumlahIksk = count(MasterKode::kodeIksk(null, 2026));
        session(['stub_role' => 'admin']);
        Livewire::test(UnggahMaster::class, ['tahun' => 2026])
            ->set('berkas', UploadedFile::fake()->createWithContent('master.xlsx', (string) file_get_contents(base_path('tests/fixtures/master-kode-contoh.xlsx'))))
            ->call('lanjut')
            ->set('tabPratinjau', 'iksk')
            // Berkas contoh: 8 IKSK pertama master 2026 apa adanya.
            ->assertSet('pratinjau.iksk.ringkasan', ['baru' => 0, 'berubah' => 0, 'tetap' => 8, 'hilang' => $jumlahIksk - 8, 'gagal' => 0])
            ->assertSeeHtmlInOrder(['data-kode="IKSK 10.2" data-status="tetap"', 'SK induk:', 'SK.10', 'dipakai 1 kegiatan'])
            ->assertSeeHtmlInOrder(['data-kode="IKSK 11.5" data-status="hilang"', 'padahal dipakai 1 kegiatan TA 2026']);

        // Hanya IKSK yang diunggah: induk diperiksa terhadap master SK.
        $this->unggah([self::JUDUL, ['SK.40', 'x', 'IKSK 40.1', 'Baru'], ['SK.10', 'x', 'IKSK 10.99', 'Baru juga']], 'iksk')
            ->assertSet('tabPratinjau', 'iksk')
            ->assertSet('pratinjau.iksk.ringkasan.gagal', 1)
            ->assertSet('pratinjau.iksk.ringkasan.baru', 1)
            ->assertSee('SK induk SK.40 tidak ada di master SK TA 2026.');

        // SK & IKSK bersama: SK baru di berkas menjadi induk yang sah.
        $this->unggah([self::JUDUL, ['SK.40', 'Sasaran baru', 'IKSK 40.1', 'Indikator baru']])
            ->assertSet('pratinjau.sk.ringkasan.baru', 1)
            ->assertSet('pratinjau.iksk.ringkasan.baru', 1)
            ->assertSet('pratinjau.iksk.ringkasan.gagal', 0);
    }
}
