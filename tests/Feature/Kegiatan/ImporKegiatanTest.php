<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Daftar;
use App\Livewire\Kegiatan\ImporKegiatan;
use App\Support\Impor\GagalBacaImpor;
use App\Support\Kegiatan\Impor\PembacaImporKegiatan;
use App\Support\Kegiatan\Impor\PemeriksaImporKegiatan;
use App\Support\Kegiatan\Impor\TemplateImporKegiatan;
use App\Support\MasterKode;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImporKegiatanTest extends TestCase
{
    /**
     * Berkas .xlsx sementara: baris pertama = judul, sisanya data.
     *
     * @param  list<list<mixed>>  $baris
     */
    private function xlsx(array $baris, string $lembar = 'Kegiatan'): string
    {
        $buku = new Spreadsheet;
        $buku->getActiveSheet()->setTitle($lembar)->fromArray($baris, null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'impor').'.xlsx';
        (new Xlsx($buku))->save($path);

        return $path;
    }

    private function unggahan(array $baris, string $nama = 'kegiatan.xlsx'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, file_get_contents($this->xlsx($baris)));
    }

    private function judul(): array
    {
        return array_values(TemplateImporKegiatan::KOLOM);
    }

    public function test_template_berisi_kolom_dropdown_leading_dan_petunjuk(): void
    {
        $respons = $this->get(route('kegiatan.template'))->assertOk();
        $this->assertStringContainsString('template-impor-kegiatan-2026.xlsx', $respons->headers->get('content-disposition'));

        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($path, $respons->streamedContent());
        $buku = IOFactory::load($path);

        $this->assertSame(['Kegiatan', 'Petunjuk', 'Referensi'], $buku->getSheetNames());
        $this->assertSame(Worksheet::SHEETSTATE_HIDDEN, $buku->getSheetByName('Referensi')->getSheetState());
        $kegiatan = $buku->getSheetByName('Kegiatan');
        $this->assertSame($this->judul(), $kegiatan->rangeToArray('A1:F1')[0]);
        $this->assertStringStartsWith("'Referensi'!", $kegiatan->getDataValidation('C2')->getFormula1());
        $this->assertSame(TemplateImporKegiatan::labelLeading(MasterKode::leadings()[0]), $buku->getSheetByName('Referensi')->getCell('A2')->getValue());

        // Template kosong yang diunggah apa adanya ditolak dengan pesan yang jelas.
        $this->expectExceptionObject(new GagalBacaImpor('Berkas tidak berisi data kegiatan. Isi lembar "Kegiatan" mulai baris 2.'));
        PembacaImporKegiatan::baca($path);
    }

    public function test_pembaca_mengenali_judul_di_mana_saja_dan_melewati_baris_kosong(): void
    {
        $path = $this->xlsx([
            ['DAFTAR KEGIATAN 2026'],
            [],
            ['Bulan Pelaksanaan', 'nama kegiatan', 'PAGU KOMITE'],
            ['Juli', 'Rapat Kerja', 5000000],
            [],
            ['', '  Lomba   Kebersihan ', ''],
        ], 'Data');

        $baris = PembacaImporKegiatan::baca($path);

        $this->assertSame([4, 6], array_column($baris, 'baris'));
        $this->assertSame(['bulan' => 'Juli', 'nama_kegiatan' => 'Rapat Kerja', 'pagu_komite' => 5000000], $baris[0]['nilai']);
        $this->assertSame('Lomba Kebersihan', $baris[1]['nilai']['nama_kegiatan']);
    }

    public function test_pembaca_menolak_berkas_yang_bukan_template(): void
    {
        $tanpaJudul = $this->xlsx([['Uraian', 'Volume'], ['Snack', 10]]);
        $rusak = tempnam(sys_get_temp_dir(), 'rusak').'.xlsx';
        file_put_contents($rusak, 'bukan excel');

        foreach ([[$tanpaJudul, 'Kolom "Nama Kegiatan" tidak ditemukan'], [$rusak, 'tidak dapat dibaca sebagai Excel/CSV']] as [$path, $pesan]) {
            try {
                PembacaImporKegiatan::baca($path, 'berkas.xlsx');
                $this->fail('Seharusnya ditolak: '.$pesan);
            } catch (GagalBacaImpor $e) {
                $this->assertStringContainsString($pesan, $e->getMessage());
            }
        }
    }

    public function test_csv_titik_koma_dari_excel_indonesia(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv').'.csv';
        file_put_contents($path, "No;Nama Kegiatan *;Leading;Pagu DIPA;Pagu Komite;Bulan Pelaksanaan\n1;Rapat Kerja;1.6;6.000.000;600.000;7-8\n");

        $baris = PembacaImporKegiatan::baca($path);
        $this->assertSame('Rapat Kerja', $baris[0]['nilai']['nama_kegiatan']);
        $this->assertSame('7-8', $baris[0]['nilai']['bulan']);
        // "600.000" tetap teks rupiah (bukan 600 desimal), sehingga pagunya Rp600.000.
        $this->assertSame('600.000', $baris[0]['nilai']['pagu_komite']);
        $periksa = PemeriksaImporKegiatan::periksa($baris, [], MasterKode::leadings(), MasterKode::leadings());
        $this->assertSame([['sumber' => 'DIPA', 'pagu' => 6_000_000], ['sumber' => 'Komite', 'pagu' => 600_000]], $periksa[0]['sumber_dana']);
    }

    public function test_pemeriksa_menjelaskan_setiap_kondisi_baris(): void
    {
        $leadings = MasterKode::leadings();
        $baris = fn (int $n, array $nilai) => ['baris' => $n, 'nilai' => $nilai];

        $hasil = PemeriksaImporKegiatan::periksa([
            $baris(2, ['nama_kegiatan' => 'Rapat Kerja', 'leading' => '1.6 – Tim Penjamin Mutu', 'pagu_dipa' => 'Rp6.000.000,-', 'pagu_komite' => 2500000.0, 'bulan' => 'Juli – September']),
            $baris(3, ['nama_kegiatan' => '', 'leading' => 'waka kurikulum']),
            $baris(4, ['nama_kegiatan' => 'rapat   kerja', 'leading' => '1.1']),
            $baris(5, ['nama_kegiatan' => 'Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran']),
            $baris(6, ['nama_kegiatan' => 'Lomba', 'leading' => 'Bagian Tak Dikenal', 'pagu_dipa' => 'sejuta', 'pagu_komite' => 1500.5, 'bulan' => '13']),
            $baris(7, ['nama_kegiatan' => str_repeat('a', 256), 'pagu_dipa' => -5, 'bulan' => '9-7']),
            $baris(8, ['nama_kegiatan' => 'Pentas Seni', 'bulan' => '3, 6; jun']),
        ], ['Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran'], $leadings, $leadings);

        $this->assertSame(['siap', 'gagal', 'gagal', 'dilewati', 'gagal', 'gagal', 'siap'], array_column($hasil, 'status'));

        $this->assertSame('1.6', $hasil[0]['leading']);
        $this->assertSame([['sumber' => 'DIPA', 'pagu' => 6_000_000], ['sumber' => 'Komite', 'pagu' => 2_500_000]], $hasil[0]['sumber_dana']);
        $this->assertSame([7, 8, 9], $hasil[0]['bulan']);
        $this->assertSame([], $hasil[0]['peringatan']);

        $this->assertSame(['Nama kegiatan wajib diisi.'], $hasil[1]['pesan']);
        $this->assertSame(['Nama sama dengan baris 2 di berkas ini.'], $hasil[2]['pesan']);
        $this->assertSame(['Kegiatan dengan nama ini sudah ada di aplikasi.'], $hasil[3]['pesan']);
        $this->assertSame([
            'Leading "Bagian Tak Dikenal" tidak ada di master tahun anggaran aktif.',
            'Pagu DIPA "sejuta" bukan angka rupiah.',
            'Pagu Komite harus rupiah bulat (tertulis 1500.5).',
            'Bulan pelaksanaan "13" tidak dikenali (contoh: 7, Juli, 7-9, Juli – September).',
        ], $hasil[4]['pesan']);
        $this->assertSame(['Nama kegiatan lebih dari 255 karakter (256).', 'Pagu DIPA tidak boleh negatif.', 'Rentang bulan "9-7" terbalik.'], $hasil[5]['pesan']);
        $this->assertSame([3, 6], $hasil[6]['bulan']);
        $this->assertSame(['Pagu DIPA dan Komite kosong — sumber dana dipilih nanti di Data Kegiatan.'], $hasil[6]['peringatan']);
    }

    public function test_akun_leading_hanya_untuk_leading_sendiri_dan_bawaan_terisi(): void
    {
        $semua = MasterKode::leadings();
        $milik = [collect($semua)->firstWhere('kode', '1.6')];

        $hasil = PemeriksaImporKegiatan::periksa([
            ['baris' => 2, 'nilai' => ['nama_kegiatan' => 'A', 'leading' => 'Waka Kurikulum']],
            ['baris' => 3, 'nilai' => ['nama_kegiatan' => 'B']],
        ], [], $milik, $semua, '1.6');

        $this->assertSame(['Leading "1.1 – Waka Kurikulum" bukan leading akun Anda.'], $hasil[0]['pesan']);
        $this->assertSame(['siap', '1.6'], [$hasil[1]['status'], $hasil[1]['leading']]);
    }

    public function test_alur_impor_lengkap_dari_unggah_sampai_laporan(): void
    {
        session(['stub_role' => 'admin']);
        $repo = app(KegiatanRepository::class);
        $awal = count($repo->semua());
        $berkas = [
            $this->judul(),
            [1, 'Rapat Kerja Awal Tahun', '1.6', 6000000, '', 'Juli'],
            [2, 'Lomba Kebersihan Kelas', 'Waka Kesiswaan', '', 1500000, '8-9'],
            [3, '', '1.1', '', '', ''],
            [4, 'Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran', '1.1', '', 10000000, 'Juli'],
        ];

        $komponen = Livewire::test(ImporKegiatan::class)
            ->assertSet('tahap', 'unggah')
            ->set('berkas', $this->unggahan($berkas))
            ->assertSet('tahap', 'periksa')
            ->assertSet('galat', null)
            ->assertSee('Siap (2)')
            ->assertSee('Gagal (1)')
            ->assertSee('Dilewati (1)')
            ->assertSee('Impor 2 kegiatan')
            ->assertSee('Nama kegiatan wajib diisi.')
            ->call('mulaiImpor')
            ->assertSet('tahap', 'proses');

        // Klien memanggil lanjutkanImpor berulang sampai tahap selesai.
        while ($komponen->get('tahap') === 'proses') {
            $komponen->call('lanjutkanImpor');
        }

        $komponen->assertSet('tahap', 'selesai')
            ->assertDispatched('kegiatan-diimpor', jumlah: 2)
            ->assertSee('Impor selesai: 2 kegiatan berhasil ditambahkan.')
            ->assertSee('2 berhasil · 1 dilewati · 1 gagal dari 4 baris');

        $this->assertSame(['berhasil', 'berhasil', 'gagal', 'dilewati'], array_column($komponen->get('hasil'), 'status'));
        $this->assertCount($awal + 2, $repo->semua());

        $baru = collect($repo->semua())->firstWhere('nama_kegiatan', 'Lomba Kebersihan Kelas');
        $this->assertSame(['1.2', [['sumber' => 'Komite', 'pagu' => 1_500_000]], [8, 9], 'draf', 'LOMBA KEBERSIHAN KELAS', 'impor_excel'],
            [$baru['leading'], $baru['sumber_dana'], $baru['bulan_pelaksanaan'], $baru['status'], $baru['nama_kegiatan_cover'], $baru['asal']]);
        $this->assertSame(route('kegiatan.edit', ['kegiatan' => $baru['id']]), $komponen->get('hasil')[1]['tautan']);

        $komponen->call('unduhLaporan')->assertFileDownloaded();

        // Berkas yang sama diimpor ulang: baris yang sudah masuk dilewati, tidak terduplikasi.
        Livewire::test(ImporKegiatan::class)
            ->set('berkas', $this->unggahan($berkas))
            ->assertSee('Siap (0)')
            ->assertSee('Tidak ada kegiatan yang siap diimpor')
            ->call('mulaiImpor')
            ->assertSet('tahap', 'periksa');
        $this->assertCount($awal + 2, $repo->semua());
    }

    public function test_berkas_salah_ditolak_dengan_pesan(): void
    {
        Livewire::test(ImporKegiatan::class)
            ->set('berkas', UploadedFile::fake()->createWithContent('catatan.txt', 'halo'))
            ->assertSet('tahap', 'unggah')
            ->assertSet('galat', 'Pilih berkas Excel (.xlsx, .xls) atau CSV.')
            ->set('berkas', $this->unggahan([['Uraian', 'Volume'], ['Snack', 1]], 'rab.xlsx'))
            ->assertSet('tahap', 'unggah')
            ->assertSee('Kolom &quot;Nama Kegiatan&quot; tidak ditemukan', false);
    }

    public function test_proses_bisa_dihentikan_dan_sisa_baris_dicatat(): void
    {
        $berkas = [$this->judul()];
        for ($i = 1; $i <= 12; $i++) {
            $berkas[] = [$i, "Kegiatan Massal {$i}", '1.6', '', 1000000, 'Mei'];
        }

        $komponen = Livewire::test(ImporKegiatan::class)
            ->set('berkas', $this->unggahan($berkas))
            ->call('mulaiImpor');

        $komponen->call('lanjutkanImpor')
            ->assertSet('tahap', 'proses')
            ->assertSet('posisi', ImporKegiatan::UKURAN_KELOMPOK)
            ->assertSee('Memproses baris 5 dari 12')
            ->call('hentikan')
            ->assertSet('tahap', 'selesai')
            ->assertSet('dihentikan', true)
            ->assertSee('Impor dihentikan: 5 kegiatan berhasil sebelum dihentikan.')
            ->assertSee('7 dibatalkan');

        $this->assertSame(array_merge(array_fill(0, 5, 'berhasil'), array_fill(0, 7, 'dibatalkan')), array_column($komponen->get('hasil'), 'status'));
    }

    public function test_halaman_daftar_tombol_cari_dan_muat_ulang_setelah_impor(): void
    {
        $this->get('/')->assertRedirect('/kegiatan');

        Livewire::test(Daftar::class)
            ->assertSee(['Unduh template', 'Impor dari Excel', 'Kegiatan baru'])
            ->assertSeeHtml('href="'.route('kegiatan.template').'"')
            ->assertSee('Supervisi Akademik dan Refleksi Pembelajaran')
            ->set('cari', 'workshop')
            ->assertSee('Workshop Penyusunan Kurikulum')
            ->assertDontSee('Supervisi Akademik dan Refleksi Pembelajaran')
            ->set('cari', '')
            ->call('bukaImpor')
            ->assertSet('imporTerbuka', true)
            ->assertSeeLivewire(ImporKegiatan::class)
            ->tap(fn () => app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Kegiatan Hasil Impor', 'asal' => 'impor_excel']))
            ->dispatch('kegiatan-diimpor', jumlah: 1)
            ->assertSee('Kegiatan Hasil Impor')
            ->assertSee('IMPOR')
            ->assertDispatched('notify');
    }
}
