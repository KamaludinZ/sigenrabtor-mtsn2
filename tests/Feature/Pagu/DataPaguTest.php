<?php

namespace Tests\Feature\Pagu;

use App\Contracts\DataPaguRepository;
use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Form;
use App\Livewire\Pagu\Daftar;
use App\Livewire\Pagu\ImporPagu;
use App\Support\Pagu\DataPagu;
use App\Support\Pagu\Impor\TemplateImporPagu;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class DataPaguTest extends TestCase
{
    private const PAGU_BARU = '9b2f1c3e-0004-4a51-8c11-000000000004';

    /** @return list<array<string, mixed>> */
    private function semua(): array
    {
        return app(DataPaguRepository::class)->semua(2026);
    }

    private function unggahan(array $baris, string $lembar = TemplateImporPagu::LEMBAR): UploadedFile
    {
        $buku = new Spreadsheet;
        $buku->getActiveSheet()->setTitle($lembar)->fromArray($baris, null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'pagu').'.xlsx';
        (new Xlsx($buku))->save($path);

        return UploadedFile::fake()->createWithContent('data-pagu.xlsx', file_get_contents($path));
    }

    public function test_menu_akses_dan_cakupan_leading(): void
    {
        $this->get(route('pagu.index'))
            ->assertOk()
            ->assertSeeLivewire(Daftar::class)
            ->assertSee('<title>Data Pagu · ', false)
            ->assertSeeHtml('href="'.route('pagu.index').'"')
            ->assertSee('Data Pagu diatur admin.')
            ->assertDontSee('Tambah data pagu')
            ->assertSee('Pelatihan Penulisan Karya Ilmiah Guru')
            ->assertDontSee('Lomba Kreativitas Siswa Tingkat Kota', 'Akun leading 1.6 tidak melihat pagu leading lain.');
        $this->get(route('pagu.template'))->assertForbidden();

        $this->withSession(['stub_role' => 'admin'])->get(route('pagu.index'))
            ->assertSee('Tambah data pagu')
            ->assertSee('Lomba Kreativitas Siswa Tingkat Kota');
        $respons = $this->withSession(['stub_role' => 'admin'])->get(route('pagu.template'))->assertOk();
        $this->assertStringContainsString('template-data-pagu-2026.xlsx', $respons->headers->get('content-disposition'));
    }

    public function test_pemakaian_oleh_kegiatan_dan_saringan(): void
    {
        $pemakaian = DataPagu::pemakaian($this->semua());
        $nama = array_column($this->semua(), 'nama_kegiatan', 'id');
        $dipakai = array_keys(array_filter($pemakaian));
        $this->assertEqualsCanonicalizing(['Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran', 'Supervisi Akademik dan Refleksi Pembelajaran', 'Review RKJM dan Penyusunan RKTM'], array_map(fn ($id) => $nama[$id], $dipakai), 'Nama sama dengan contoh kegiatan.');

        session(['stub_role' => 'admin']);
        Livewire::test(Daftar::class)
            ->assertSeeHtmlInOrder(['data-testid="ringkasan-pagu"', 'data-stat="jumlah"', 'Data pagu', '6', '3 sudah jadi kegiatan', 'data-stat="belum"', '3', 'data-stat="DIPA"', 'Rp', 'data-stat="Komite"', 'Rp', 'data-testid="daftar-pagu"'])
            ->assertSeeHtml('href="'.route('kegiatan.create', ['pagu' => self::PAGU_BARU]).'"')
            ->set('pemakaian', 'belum')
            ->assertDontSee('Review RKJM dan Penyusunan RKTM')
            ->assertSee('Pelatihan Penulisan Karya Ilmiah Guru')
            ->set('pemakaian', '')
            ->set('leading', '1.3')
            ->assertSee('Pengadaan Buku Referensi Perpustakaan')
            ->assertDontSee('Pelatihan Penulisan Karya Ilmiah Guru')
            ->set('leading', '')
            ->set('cari', '  supervisi   AKADEMIK ')
            ->assertSee('Supervisi Akademik dan Refleksi Pembelajaran')
            ->assertDontSee('Pengadaan Buku')
            ->call('hapusSaringan')
            ->assertSet('cari', '');
    }

    public function test_admin_tambah_ubah_hapus_dan_tolak_nama_ganda(): void
    {
        session(['stub_role' => 'admin']);

        $komponen = Livewire::test(Daftar::class)
            ->call('tambah')
            ->assertSeeHtml('data-testid="formulir-pagu"')
            ->set('isian.nama_kegiatan', '  review rkjm  DAN penyusunan rktm ')
            ->set('isian.pagu_dipa', '1.000.000')
            ->call('simpan')
            ->assertHasErrors('isian.nama_kegiatan')
            ->assertSee('Nama ini sudah ada di Data Pagu.')
            ->set('isian.nama_kegiatan', 'Kegiatan Rapat Kerja Madrasah')
            ->set('isian.pagu_dipa', '')
            ->call('simpan')
            ->assertHasErrors('isian.pagu_komite')
            ->assertSee('Isi pagu DIPA atau Komite.')
            ->set('isian.leading', '1.4')
            ->set('isian.pagu_komite', '3500000')
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Data pagu ditambahkan.', type: 'success')
            ->assertSet('ubahId', null);

        $baru = collect($this->semua())->firstWhere('nama_kegiatan', 'Rapat Kerja Madrasah');
        $this->assertSame(['1.4', ['DIPA' => null, 'Komite' => 3_500_000], 'manual'], [$baru['leading'], $baru['pagu'], $baru['asal']], 'Awalan "Kegiatan" dibuang, pagu jadi angka.');

        $komponen->call('ubah', $baru['id'])
            ->assertSet('isian.pagu_komite', '3.500.000')
            ->set('isian.pagu_dipa', '2.000.000')
            ->call('simpan')
            ->assertHasNoErrors()
            ->call('mintaHapus', $baru['id'])
            ->assertSee('Hapus?')
            ->call('hapus')
            ->assertDispatched('notify', message: 'Data pagu dihapus. Kegiatan yang sudah dibuat tidak berubah.', type: 'info');
        $this->assertNull(app(DataPaguRepository::class)->temukan($baru['id']));

        session(['stub_role' => 'leading']);
        Livewire::test(Daftar::class)->call('tambah')->assertForbidden();
    }

    public function test_impor_mengenali_nama_yang_sama(): void
    {
        session(['stub_role' => 'admin']);
        $komponen = Livewire::test(ImporPagu::class)
            ->set('berkas', $this->unggahan([
                ['No', 'Nama Kegiatan *', 'Leading', 'Pagu DIPA', 'Pagu Komite'],
                [1, 'Rapat Kerja Madrasah', '1.4 – Waka Humas', '', 3500000],
                [2, 'supervisi akademik dan  refleksi pembelajaran', '1.6', 6000000, 2500000],
                [3, 'Pelatihan Penulisan Karya Ilmiah Guru', '1.6', 5000000, ''],
                [4, 'Rapat kerja madrasah', '', 100000, ''],
                [5, 'Tanpa Pagu', '', '', ''],
            ]))
            ->assertSet('tahap', 'periksa');

        $status = array_column($komponen->get('baris'), 'status', 'baris');
        $this->assertSame([2 => 'siap', 3 => 'dilewati', 4 => 'siap', 5 => 'gagal', 6 => 'gagal'], $status);
        $baris = array_column($komponen->get('baris'), null, 'baris');
        $this->assertSame(['Sudah ada di Data Pagu dengan leading & pagu yang sama.'], $baris[3]['pesan']);
        $this->assertSame('Perbarui pagu', $baris[4]['tampil']['aksi']);
        $this->assertSame('Nama sudah ada di Data Pagu — akan diperbarui: Pagu DIPA: Rp4.500.000 → Rp5.000.000.', $baris[4]['peringatan'][0]);
        $this->assertSame(['Nama sama dengan baris 2 di berkas ini.'], $baris[5]['pesan']);
        $this->assertSame(['Isi Pagu DIPA atau Pagu Komite.'], $baris[6]['pesan']);
        $this->assertSame(['Sudah ada kegiatan bernama sama di Data Kegiatan; pagu ini tertaut ke kegiatan itu.'], $baris[3]['peringatan']);

        $komponen->call('mulaiImpor');
        while ($komponen->get('tahap') === 'proses') {
            $komponen->call('lanjutkanImpor');
        }
        $komponen->assertSet('tahap', 'selesai')->assertDispatched('pagu-diimpor', jumlah: 2)
            ->assertSee('Impor selesai: 2 data pagu berhasil disimpan (ditambah atau diperbarui).');

        $data = array_column($this->semua(), null, 'nama_kegiatan');
        $this->assertSame(['1.4', ['DIPA' => null, 'Komite' => 3_500_000], 'impor'], [$data['Rapat Kerja Madrasah']['leading'], $data['Rapat Kerja Madrasah']['pagu'], $data['Rapat Kerja Madrasah']['asal']]);
        $this->assertSame(5_000_000, $data['Pelatihan Penulisan Karya Ilmiah Guru']['pagu']['DIPA'], 'Nama yang sama diperbarui, bukan ditambah.');
        $this->assertCount(7, $this->semua());

        // Template impor kegiatan (lembar "Kegiatan", ada kolom Bulan) juga diterima.
        $lagi = Livewire::test(ImporPagu::class)->set('berkas', $this->unggahan([
            ['No', 'Nama Kegiatan *', 'Leading', 'Pagu DIPA', 'Pagu Komite', 'Bulan Pelaksanaan'],
            [1, 'Pentas Seni Akhir Tahun', '', '', 8000000, 'Desember'],
        ], 'Kegiatan'));
        $this->assertSame(['siap'], array_column($lagi->get('baris'), 'status'));

        session(['stub_role' => 'leading']);
        Livewire::test(ImporPagu::class)->set('berkas', $this->unggahan([['Nama Kegiatan *'], ['X']]))->assertForbidden();
    }

    public function test_template_impor_data_pagu(): void
    {
        session(['stub_role' => 'admin']);
        $berkas = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($berkas, $this->get(route('pagu.template'))->streamedContent());
        $buku = IOFactory::load($berkas);

        $this->assertSame([TemplateImporPagu::LEMBAR, 'Referensi', 'Petunjuk'], $buku->getSheetNames());
        $this->assertSame(['No', 'Nama Kegiatan *', 'Leading', 'Pagu DIPA', 'Pagu Komite'], $buku->getSheet(0)->rangeToArray('A1:E1')[0]);
    }

    public function test_kegiatan_baru_dari_data_pagu(): void
    {
        $komponen = Livewire::withQueryParams(['pagu' => self::PAGU_BARU])->test(Form::class)
            ->assertSet('form.nama_kegiatan', 'Pelatihan Penulisan Karya Ilmiah Guru')
            ->assertSet('form.nama_kegiatan_cover', 'PELATIHAN PENULISAN KARYA ILMIAH GURU')
            ->assertSet('form.leading', '1.6')
            ->assertSet('form.dana_dipa', true)
            ->assertSet('form.pagu_dipa', '4.500.000')
            ->assertSet('form.dana_komite', false)
            ->assertSet('form.data_pagu_id', self::PAGU_BARU)
            ->assertSet('adaPerubahan', false)
            ->assertSeeHtml('data-testid="data-pagu-tertaut"');

        $opsi = array_column($komponen->instance()->opsiDataPagu, 'label');
        $this->assertSame(['Pelatihan Penulisan Karya Ilmiah Guru'], $opsi, 'Akun leading 1.6: hanya pagu leading-nya yang belum jadi kegiatan.');

        $komponen->call('saveDraft');
        $kegiatan = collect(app(KegiatanRepository::class)->semua())->firstWhere('nama_kegiatan', 'Pelatihan Penulisan Karya Ilmiah Guru');
        $this->assertSame([self::PAGU_BARU, [['sumber' => 'DIPA', 'pagu' => 4_500_000]]], [$kegiatan['data_pagu_id'], $kegiatan['sumber_dana']]);
        $this->assertSame($kegiatan['id'], DataPagu::pemakaian($this->semua())[self::PAGU_BARU]['id']);

        // Pilih lewat combobox, lalu lepas tautan.
        session(['stub_role' => 'admin']);
        $lomba = collect($this->semua())->firstWhere('nama_kegiatan', 'Lomba Kreativitas Siswa Tingkat Kota');
        Livewire::test(Form::class)
            ->set('pilihanPagu', $lomba['id'])
            ->assertSet('pilihanPagu', '')
            ->assertSet('form.nama_kegiatan', 'Lomba Kreativitas Siswa Tingkat Kota')
            ->assertSet('form.leading', '1.2')
            ->assertSet('form.dana_komite', true)
            ->assertSet('form.pagu_komite', '12.000.000')
            ->assertDispatched('notify', message: 'Nama & pagu diisi dari Data Pagu: Lomba Kreativitas Siswa Tingkat Kota.', type: 'success')
            ->call('lepasDataPagu')
            ->assertSet('form.data_pagu_id', null)
            ->assertSet('form.nama_kegiatan', 'Lomba Kreativitas Siswa Tingkat Kota');

        Livewire::test(Form::class)->call('pakaiDataPagu', self::PAGU_BARU)
            ->assertSet('form.nama_kegiatan', '')
            ->assertDispatched('notify', message: '"Pelatihan Penulisan Karya Ilmiah Guru" sudah menjadi kegiatan; buka kegiatan itu dari Data Kegiatan.', type: 'error');

        session(['stub_role' => 'leading']);
        Livewire::withQueryParams([])->test(Form::class)->call('pakaiDataPagu', $lomba['id'])->assertSet('form.nama_kegiatan', '');
    }
}
