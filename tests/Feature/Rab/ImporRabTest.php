<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\ImporRab;
use App\Livewire\Kegiatan\Rab;
use App\Support\Impor\GagalBacaImpor;
use App\Support\Rab\Impor\PembacaImporRab;
use App\Support\Rab\Impor\PemeriksaImporRab;
use App\Support\Rab\Impor\TemplateImporRab;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImporRabTest extends TestCase
{
    /** Tabel seperti Form RAB workshop di REFERENSI: judul dokumen di atas, TOTAL KESELURUHAN, lalu blok tanda tangan. */
    private function tabelWorkshop(): array
    {
        return [
            ['RENCANA ANGGARAN BIAYA'],
            ['KEGIATAN WORKSHOP PENYUSUNAN KURIKULUM'],
            [],
            ['No', 'Uraian', 'Vol', 'Satuan', 'Harga Satuan (Rp)', 'Jumlah (Rp)'],
            ['1.', 'Honorarium Pemateri', 6, 'OJ', 600000, 3600000],
            ['2.', 'Nasi Kotak Guru', 54, 'kotak', 30000, '1.620.000'],
            ['3.', 'Transport Pengawas', 1, 'OK', 150000, 150000],
            ['4.', 'Penyelesaian tugas penyusunan perangkat di luar jam kerja (6 orang)', 2, 'OJ', 25000, 300000],
            ['', 'TOTAL KESELURUHAN', '', '', '', 5670000],
            [],
            ['Mengetahui,', '', '', 'Malang, 1 Juli 2026'],
        ];
    }

    private function xlsx(array $baris): string
    {
        $buku = new Spreadsheet;
        $buku->getActiveSheet()->fromArray($baris, null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'rab').'.xlsx';
        (new Xlsx($buku))->save($path);

        return $path;
    }

    private function unggahan(array $baris, string $nama = 'rincian.xlsx'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, file_get_contents($this->xlsx($baris)));
    }

    public function test_pembaca_menemukan_tabel_rab_lama_dan_berhenti_di_total(): void
    {
        $tabel = PembacaImporRab::baca($this->xlsx($this->tabelWorkshop()), 'rab.xlsx');

        $this->assertSame([5, 6, 7, 8], array_column($tabel['baris'], 'baris'));
        $this->assertSame(5_670_000, $tabel['total_berkas']);
        $this->assertSame(9, $tabel['baris_total']);
        $this->assertSame(['uraian' => 'Honorarium Pemateri', 'volume' => 6, 'satuan' => 'OJ', 'harga_satuan' => 600000, 'jumlah' => 3600000],
            array_diff_key($tabel['baris'][0]['nilai'], ['no' => true]));
    }

    public function test_pembaca_mengenali_judul_format_komite(): void
    {
        $tabel = PembacaImporRab::baca($this->xlsx([
            ['NO', 'KODE KEGIATAN', 'URAIAN', 'VOL ', 'JUMLAH SATUAN (Rp.)', 'JUMLAH / (Rp.)'],
            [1, '521213', 'Honor narasumber', '6 OJ', 'Rp 600.000', 'Rp 3.600.000,-'],
            ['JUMLAH', '', '', '', '', '3.600.000'],
        ]), 'komite.xlsx');

        $this->assertSame(['kode' => 521213, 'volume' => '6 OJ', 'harga_satuan' => 'Rp 600.000', 'jumlah' => 'Rp 3.600.000,-'],
            array_intersect_key($tabel['baris'][0]['nilai'], array_flip(['kode', 'volume', 'harga_satuan', 'jumlah'])));
        $this->assertSame('3.600.000', $tabel['total_berkas']);
    }

    public function test_pembaca_menolak_berkas_tanpa_tabel_rincian(): void
    {
        foreach ([
            [[['Nama Kegiatan', 'Leading'], ['Rapat', '1.6']], 'Tabel rincian tidak ditemukan'],
            [[['Uraian', 'Vol', 'Harga Satuan', 'Jumlah'], ['TOTAL', '', '', 0]], 'belum berisi item belanja'],
        ] as [$isi, $pesan]) {
            try {
                PembacaImporRab::baca($this->xlsx($isi), 'x.xlsx');
                $this->fail('Seharusnya ditolak: '.$pesan);
            } catch (GagalBacaImpor $e) {
                $this->assertStringContainsString($pesan, $e->getMessage());
            }
        }
    }

    public function test_pemeriksa_menyesuaikan_format_otomatis(): void
    {
        $b = fn (int $n, array $nilai) => ['baris' => $n, 'nilai' => $nilai + ['kode' => null, 'satuan' => null, 'harga_satuan' => null, 'jumlah' => null, 'volume' => null]];

        $hasil = PemeriksaImporRab::periksa([
            $b(2, ['uraian' => 'Penyelesaian tugas di luar jam kerja (6 orang)', 'volume' => 2, 'satuan' => 'OJ', 'harga_satuan' => 25000, 'jumlah' => 300000]),
            $b(3, ['uraian' => 'Honorarium Pemateri', 'volume' => '6 OJ', 'jumlah' => '3.600.000']),
            $b(4, ['uraian' => '521211 Belanja Bahan']),
            $b(5, ['uraian' => 'Nasi Kotak Guru', 'volume' => '1,5', 'satuan' => 'kotak', 'harga_satuan' => 'Rp30.000,-']),
            $b(6, ['uraian' => 'Transport Pengawas', 'kode' => 524113.0, 'volume' => 1, 'satuan' => 'OK', 'harga_satuan' => 150000]),
            $b(7, ['uraian' => 'Fotokopi modul', 'volume' => 3, 'satuan' => 'pack', 'harga_satuan' => 7000, 'jumlah' => 20000]),
            $b(8, ['uraian' => 'ATK', 'volume' => 'dua', 'harga_satuan' => 'gratis']),
            $b(9, ['uraian' => 'Air Mineral', 'volume' => 3, 'satuan' => 'dus', 'harga_satuan' => 40000]),
        ], [['uraian' => 'air  mineral', 'volume' => 3, 'harga_satuan' => 40000]]);

        $this->assertSame(['siap', 'siap', 'dilewati', 'siap', 'siap', 'gagal', 'gagal', 'dilewati'], array_column($hasil, 'status'));

        // Pengali di uraian: volume 2 → 12 agar 12 × 25.000 = 300.000 seperti berkas.
        $this->assertSame(['kategori' => 'jasa', 'volume' => 12, 'satuan' => 'OJ', 'harga_satuan' => 25000],
            array_intersect_key($hasil[0]['item'], array_flip(['volume', 'satuan', 'harga_satuan', 'kategori'])));
        $this->assertSame(300_000, $hasil[0]['jumlah']);
        $this->assertSame(['Volume disesuaikan dari 2 menjadi 12 agar volume × harga sama dengan jumlah di berkas (Rp300.000).'], $hasil[0]['peringatan']);

        // "6 OJ" dipisah; harga dihitung dari jumlah ÷ volume.
        $this->assertSame(['volume' => 6, 'satuan' => 'OJ', 'harga_satuan' => 600000], array_intersect_key($hasil[1]['item'], array_flip(['volume', 'satuan', 'harga_satuan'])));
        $this->assertSame(['Harga satuan dihitung dari jumlah ÷ volume: Rp600.000.'], $hasil[1]['peringatan']);

        // Judul kelompok dilewati; kodenya turun ke item di bawahnya yang tanpa kode.
        $this->assertSame(['521211', 'bahan', 1.5, 45_000], [$hasil[3]['item']['kode'], $hasil[3]['item']['kategori'], $hasil[3]['item']['volume'], $hasil[3]['jumlah']]);
        $this->assertSame(['Kode akun 521211 diambil dari judul kelompok di atasnya.'], $hasil[3]['peringatan']);
        $this->assertSame(['524113', 'lain'], [$hasil[4]['item']['kode'], $hasil[4]['item']['kategori']]);

        $this->assertSame(['Jumlah di berkas Rp20.000 tidak sama dengan volume × harga (Rp21.000) dan tidak dapat disesuaikan otomatis.'], $hasil[5]['pesan']);
        $this->assertSame([
            'Volume "dua" bukan angka (mis. 6 atau 1,5).',
            'Satuan belum diisi (isi kolom Satuan, atau tulis mis. "6 OJ" di kolom Vol).',
            'Harga satuan "gratis" bukan angka rupiah.',
        ], $hasil[6]['pesan']);
        $this->assertSame(['Item yang sama (uraian, volume, harga) sudah ada di RAB ini.'], $hasil[7]['pesan']);
    }

    public function test_format_rincian_bisa_diunduh_dan_kosongnya_ditolak(): void
    {
        $respons = $this->get(route('kegiatan.rab.template'))->assertOk();
        $this->assertStringContainsString('format-rincian-rab.xlsx', $respons->headers->get('content-disposition'));

        $path = tempnam(sys_get_temp_dir(), 'fmt').'.xlsx';
        file_put_contents($path, $respons->streamedContent());
        $this->assertSame(TemplateImporRab::KOLOM, IOFactory::load($path)->getSheetByName('Rincian')->rangeToArray('A1:G1')[0]);

        $this->expectExceptionMessage('belum berisi item belanja');
        PembacaImporRab::baca($path, 'format-rincian-rab.xlsx');
    }

    public function test_csv_titik_ribuan_tetap_rupiah_penuh(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv').'.csv';
        file_put_contents($path, "No;Uraian;Vol;Satuan;Harga Satuan;Jumlah\n1;Nasi Kotak Guru;54;kotak;30.000;1.620.000\n2;Snack;1,5;dus;600.000;900.000\n");

        $hasil = PemeriksaImporRab::periksa(PembacaImporRab::baca($path, 'rincian.csv')['baris'], []);

        $this->assertSame(['siap', 'siap'], array_column($hasil, 'status'));
        $this->assertSame([30_000, 600_000], array_column(array_column($hasil, 'item'), 'harga_satuan'));
        $this->assertSame([1_620_000, 900_000], array_column($hasil, 'jumlah'));
        $this->assertSame([[], []], array_column($hasil, 'peringatan'));
    }

    public function test_alur_impor_rab_menambah_rincian_dan_aman_diulang(): void
    {
        $rab = app(RabRepository::class);
        // Kegiatan baru dengan RAB Komite kosong (contoh kegiatan 1 sudah memuat item serupa).
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Workshop Impor', 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 6_000_000]]]);
        $awal = count($rab->rincian($id, 'Komite'));

        $komponen = Livewire::test(ImporRab::class, ['kegiatanId' => $id, 'sumber' => 'Komite'])
            ->set('berkas', $this->unggahan($this->tabelWorkshop()))
            ->assertSet('tahap', 'periksa')
            ->assertSet('totalBerkas', 5_670_000)
            ->assertSee('Siap (4)')
            ->assertSeeHtml('data-testid="total-berkas-cocok"')
            ->assertSee('Volume disesuaikan dari 2 menjadi 12')
            ->assertSee('Impor 4 item')
            ->call('mulaiImpor');

        while ($komponen->get('tahap') === 'proses') {
            $komponen->call('lanjutkanImpor');
        }

        $komponen->assertSet('tahap', 'selesai')
            ->assertSee('Impor selesai: 4 item berhasil ditambahkan.')
            ->assertDispatched('rab-diimpor', jumlah: 4, sumber: 'Komite');

        $rincian = $rab->rincian($id, 'Komite');
        $this->assertCount($awal + 4, $rincian);
        $terakhir = end($rincian);
        $this->assertSame(['uraian' => 'Penyelesaian tugas penyusunan perangkat di luar jam kerja (6 orang)', 'kategori' => 'jasa', 'volume' => 12, 'satuan' => 'OJ', 'harga_satuan' => 25000],
            array_intersect_key($terakhir, array_flip(['uraian', 'kategori', 'volume', 'satuan', 'harga_satuan'])));

        // Halaman RAB memuat ulang rincian & memberi tahu.
        Livewire::test(Rab::class, ['kegiatan' => $id])
            ->dispatch('rab-diimpor', jumlah: 4, sumber: 'Komite')
            ->assertDispatched('notify')
            ->assertSee('Honorarium Pemateri');

        // Berkas yang sama diunggah lagi: semua item dilewati.
        Livewire::test(ImporRab::class, ['kegiatanId' => $id, 'sumber' => 'Komite'])
            ->set('berkas', $this->unggahan($this->tabelWorkshop()))
            ->assertSee('Siap (0)')
            ->assertSee('Dilewati (4)');
    }

    public function test_tombol_impor_di_halaman_rab_dan_terkunci_di_luar_draf(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 2])
            ->assertSee('Impor Excel')
            ->call('bukaImpor')
            ->assertSet('imporTerbuka', true)
            ->assertSeeLivewire(ImporRab::class)
            ->assertSee('Impor rincian RAB DIPA dari Excel')
            ->call('pilihSumber', 'Komite')
            ->assertSet('imporTerbuka', false);

        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Terkunci', 'status' => 'diperiksa', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1]]]);
        Livewire::test(Rab::class, ['kegiatan' => $id])
            ->assertDontSee('Impor Excel')
            ->call('bukaImpor')
            ->assertForbidden();
    }
}
