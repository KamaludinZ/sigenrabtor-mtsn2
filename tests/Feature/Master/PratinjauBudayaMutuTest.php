<?php

namespace Tests\Feature\Master;

use App\Livewire\Master\UnggahMaster;
use App\Support\Master\Impor\GagalPratinjauMaster;
use App\Support\Master\Impor\PemeriksaBudayaMutu;
use App\Support\Master\Impor\PratinjauMaster;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PratinjauBudayaMutuTest extends TestCase
{
    /** @param  list<list<string|int|null>>  $baris */
    private function lembar(array $baris): Worksheet
    {
        $lembar = (new Spreadsheet)->getActiveSheet()->setTitle('5 Budaya Mutu');
        $lembar->fromArray($baris, null, 'A1', true);

        return $lembar;
    }

    public function test_baca_nomor_opsional_dan_galat(): void
    {
        $baris = PemeriksaBudayaMutu::baca($this->lembar([
            ['No', 'Budaya Mutu'],
            [1, 'Integritas'],
            ['', 'Profesionalitas'],
            ['3.0', 'Inovasi'],
            [1, 'Tanggung Jawab'],
            [5, 'integritas'],
            ['x', 'Keteladanan'],
            [7, ''],
        ]));

        $this->assertSame(['1', '2', '3', '1', '5', 'x', '7'], array_column($baris, 'kode'));
        $this->assertSame([null, null, null, 'gagal', 'gagal', 'gagal', 'gagal'], array_column($baris, 'status'));
        $this->assertSame(['Nomor 1 sudah dipakai di baris 2.'], $baris[3]['pesan']);
        $this->assertSame(['Nama sama dengan baris 2.'], $baris[4]['pesan']);
        $this->assertSame(['Nomor "x" harus bilangan bulat mulai 1.'], $baris[5]['pesan']);
        $this->assertSame(['Nama budaya mutu kosong.'], $baris[6]['pesan']);

        $tanpaNomor = PemeriksaBudayaMutu::baca($this->lembar([['Budaya Mutu'], ['Integritas'], ['Inovasi']]));
        $this->assertSame(['1', '2'], array_column($tanpaNomor, 'kode'));

        $this->assertNull(PemeriksaBudayaMutu::peringatanJumlah(5));
        $this->assertSame('Berkas berisi 4 butir budaya mutu — biasanya 5 butir. Periksa lagi sebelum menyimpan.', PemeriksaBudayaMutu::peringatanJumlah(4));

        $this->expectException(GagalPratinjauMaster::class);
        $this->expectExceptionMessage('Judul kolom "Budaya Mutu" tidak ditemukan di sheet 5 Budaya Mutu.');
        PemeriksaBudayaMutu::baca($this->lembar([['No', 'Isi'], [1, 'x']]));
    }

    public function test_bandingkan_dan_pratinjau_di_komponen(): void
    {
        $baca = PemeriksaBudayaMutu::baca($this->lembar([['No', 'Budaya Mutu'], [1, 'Integritas'], [2, 'Profesional'], [6, 'Kolaborasi']]));
        $hasil = collect(PratinjauMaster::bandingkan($baca, 'budaya', 2026, ['2' => 3]))->keyBy('kode');
        $this->assertSame('tetap', $hasil['1']['status']);
        $this->assertSame(['berubah', ['nama' => 'Profesionalitas'], 3], [$hasil['2']['status'], $hasil['2']['lama'], $hasil['2']['dipakai']]);
        $this->assertSame('baru', $hasil['6']['status']);
        $this->assertSame(['hilang', ['nama' => 'Inovasi']], [$hasil['3']['status'], $hasil['3']['tampil']]);

        session(['stub_role' => 'admin']);
        Livewire::test(UnggahMaster::class, ['tahun' => 2026])
            ->set('berkas', UploadedFile::fake()->createWithContent('master.xlsx', (string) file_get_contents(base_path('tests/fixtures/master-kode-contoh.xlsx'))))
            ->call('lanjut')
            ->set('tabPratinjau', 'budaya')
            ->assertSet('pratinjau.budaya.ringkasan', ['baru' => 0, 'berubah' => 0, 'tetap' => 5, 'hilang' => 0, 'gagal' => 0])
            ->assertSet('pratinjau.budaya.peringatan', [])
            ->assertSeeHtmlInOrder(['data-kode="2" data-status="tetap"', 'Profesionalitas', 'dipakai 3 kegiatan'])
            ->assertDontSeeHtml('data-testid="peringatan-pratinjau"');

        // Hanya 5 Budaya Mutu, empat butir: peringatan jumlah.
        $buku = new Spreadsheet;
        $buku->getActiveSheet()->setTitle('Budaya')->fromArray([['No', 'Budaya Mutu'], [1, 'Integritas'], [2, 'Profesionalitas'], [3, 'Inovasi'], [4, 'Tanggung Jawab']]);
        $path = tempnam(sys_get_temp_dir(), 'bm');
        (new Xlsx($buku))->save($path);
        $isi = (string) file_get_contents($path);
        @unlink($path);

        Livewire::test(UnggahMaster::class, ['tahun' => 2026])
            ->set('cakupan', 'budaya')
            ->set('berkas', UploadedFile::fake()->createWithContent('budaya.xlsx', $isi))
            ->call('lanjut')
            ->assertSet('pratinjau.budaya.ringkasan.hilang', 1)
            ->assertSeeHtml('data-testid="peringatan-pratinjau"')
            ->assertSee('Berkas berisi 4 butir budaya mutu — biasanya 5 butir.');
    }
}
