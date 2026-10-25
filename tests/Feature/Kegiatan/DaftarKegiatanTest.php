<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\Daftar;
use Livewire\Livewire;
use Tests\TestCase;

class DaftarKegiatanTest extends TestCase
{
    private const WORKSHOP = 'Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran';

    private const SUPERVISI = 'Supervisi Akademik dan Refleksi Pembelajaran';

    private const RKTM = 'Review RKJM dan Penyusunan RKTM';

    /** Nama kegiatan pada tabel, sesuai urutan tampil. */
    private function nama($komponen): array
    {
        return array_column($komponen->instance()->halamanIni, 'nama');
    }

    public function test_tabel_lengkap_dengan_ringkasan_pagu_dan_total(): void
    {
        $komponen = Livewire::test(Daftar::class)
            ->assertSeeHtml('data-testid="tabel-kegiatan"')
            ->assertSeeInOrder(['No', 'Kegiatan', 'Leading', 'Bulan', 'Sumber dana &amp; pagu', 'Total RAB', 'Status', 'Diubah', 'Aksi'], false)
            ->assertSee('6.19.1')
            ->assertSee('Agu – Sep')
            ->assertSee('Di bawah pagu')
            ->assertSee('Sesuai pagu')
            ->assertSee('Melebihi pagu')
            ->assertSee('1 melebihi pagu')
            ->assertSeeHtml('aria-label="RAB '.self::SUPERVISI.'"')
            ->assertSeeHtml('aria-label="Kode '.self::SUPERVISI.'"');

        // Pagu DIPA contoh Review RKJM belum diisi: tidak menambah total pagu.
        $this->assertSame(['kegiatan' => 3, 'pagu' => ['DIPA' => 6_000_000, 'Komite' => 20_000_000], 'total' => 26_750_000, 'melebihi' => 1], $komponen->instance()->ringkasan);
        $komponen->assertSeeInOrder(['Jumlah 3 kegiatan', 'Rp26.000.000', 'Rp26.750.000']);
    }

    public function test_filter_sumber_bulan_kondisi_dan_cari(): void
    {
        $komponen = Livewire::test(Daftar::class);

        foreach ([
            ['sumber', 'keduanya', [self::SUPERVISI, self::RKTM]],
            ['sumber', 'dipa', [self::SUPERVISI, self::RKTM]],
            ['sumber', 'komite', [self::WORKSHOP, self::SUPERVISI, self::RKTM]],
            ['bulan', '7', [self::WORKSHOP]],
            ['bulan', '9', [self::SUPERVISI]],
            ['bulan', '11', [self::RKTM]],
            ['statusPagu', 'sesuai', [self::WORKSHOP]],
            ['statusPagu', 'sisa', [self::SUPERVISI]],
            ['statusPagu', 'melebihi', [self::RKTM]],
            ['cari', '6.19', [self::SUPERVISI]],
            ['cari', 'KURIKULUM', [self::WORKSHOP]],
            ['cari', '6.17.13', [self::RKTM]],
        ] as [$properti, $nilai, $harapan]) {
            $komponen->call('hapusSemuaFilter')->set('urut', 'nama')->set('arah', 'desc')->set($properti, $nilai);
            $this->assertSame($harapan, $this->nama($komponen), "{$properti}={$nilai}");
        }

        $komponen->call('hapusSemuaFilter')
            ->set('sumber', 'belum')
            ->assertSee('Tidak ada kegiatan yang cocok dengan filter.')
            ->assertSee('Hapus semua filter');
    }

    public function test_filter_status_leading_asal_dan_rab(): void
    {
        $kegiatan = app(KegiatanRepository::class);
        $diperiksa = $kegiatan->simpan(['nama_kegiatan' => 'Lomba Kebersihan', 'status' => 'diperiksa', 'leading' => '1.2', 'asal' => 'impor_excel',
            'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 100_000]]]);
        $kosong = $kegiatan->simpan(['nama_kegiatan' => 'Rapat Tanpa Leading', 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 500_000]]]);
        app(RabRepository::class)->tambahItem($diperiksa, 'DIPA', ['kode' => '', 'uraian' => 'Hadiah', 'kategori' => 'bahan', 'volume' => 3, 'satuan' => 'paket', 'harga_satuan' => 50_000]);

        $komponen = Livewire::test(Daftar::class);
        foreach ([
            ['status', 'diperiksa', ['Lomba Kebersihan']],
            ['leading', '1.2', ['Lomba Kebersihan']],
            ['leading', 'belum', ['Rapat Tanpa Leading']],
            ['asal', 'impor', ['Lomba Kebersihan']],
            ['statusPagu', 'melebihi', ['Lomba Kebersihan', self::RKTM]],
            ['statusPagu', 'rab_kosong', ['Rapat Tanpa Leading']],
        ] as [$properti, $nilai, $harapan]) {
            $komponen->call('hapusSemuaFilter')->set($properti, $nilai);
            $this->assertSame($harapan, $this->nama($komponen), "{$properti}={$nilai}");
        }

        $this->assertSame(2, $komponen->call('hapusSemuaFilter')->instance()->ringkasan['melebihi']);
        $komponen->assertSee('2 melebihi pagu')->assertSee('RAB belum diisi')->assertSee('IMPOR');
        $this->assertNotNull($kosong);
    }

    public function test_chip_filter_aktif_bisa_dihapus_satu_atau_semua(): void
    {
        $komponen = Livewire::withQueryParams(['sumber' => 'komite', 'bulan' => '8', 'cari' => 'supervisi'])
            ->test(Daftar::class)
            ->assertSet('sumber', 'komite')
            ->assertSeeHtml('data-testid="filter-aktif"');

        $this->assertSame(['cari' => 'Cari: “supervisi”', 'sumber' => 'Dibiayai Komite', 'bulan' => 'Agustus'], $komponen->instance()->filterAktif());
        $this->assertSame([self::SUPERVISI], $this->nama($komponen));

        $komponen->call('hapusFilter', 'bulan')->assertSet('bulan', '')->assertSet('sumber', 'komite')
            ->call('hapusFilter', 'tidak-ada')
            ->call('hapusSemuaFilter')
            ->assertSet('cari', '')
            ->assertSet('sumber', '')
            ->assertDontSeeHtml('data-testid="filter-aktif"');
    }

    public function test_urut_kolom_dan_balik_arah(): void
    {
        $komponen = Livewire::withQueryParams([])->test(Daftar::class)
            ->call('urutkan', 'nama')
            ->assertSet('urut', 'nama')
            ->assertSet('arah', 'asc');
        $this->assertSame([self::RKTM, self::SUPERVISI, self::WORKSHOP], $this->nama($komponen));

        $komponen->call('urutkan', 'nama')->assertSet('arah', 'desc');
        $this->assertSame([self::WORKSHOP, self::SUPERVISI, self::RKTM], $this->nama($komponen));

        $komponen->call('urutkan', 'total')->assertSet('arah', 'desc')->assertSeeHtml('aria-sort="descending"');
        $this->assertSame([self::WORKSHOP, self::RKTM, self::SUPERVISI], $this->nama($komponen));

        $komponen->call('urutkan', 'bulan')->assertSet('arah', 'asc');
        $this->assertSame([self::WORKSHOP, self::SUPERVISI, self::RKTM], $this->nama($komponen));

        $komponen->call('urutkan', 'sembarang')->assertSet('urut', 'bulan');
    }

    public function test_halaman_dan_jumlah_per_halaman(): void
    {
        $repo = app(KegiatanRepository::class);
        for ($i = 1; $i <= 30; $i++) {
            $repo->simpan(['nama_kegiatan' => sprintf('Kegiatan Uji %02d', $i)]);
        }

        $komponen = Livewire::withQueryParams([])->test(Daftar::class)
            ->set('perHalaman', 10)
            ->assertSee('Menampilkan 1–10 dari 33')
            ->assertSeeHtml('aria-label="Halaman tabel"')
            ->call('keHalaman', 4)
            ->assertSet('halaman', 4)
            ->assertSee('Menampilkan 31–33 dari 33')
            ->call('keHalaman', 99)
            ->assertSet('halaman', 4);
        $this->assertCount(3, $komponen->instance()->halamanIni);

        // Mengubah filter atau jumlah per halaman kembali ke halaman 1; nilai tak dikenal kembali ke 25.
        $komponen->set('cari', 'Kegiatan Uji')->assertSet('halaman', 1)->assertSee('Menampilkan 1–10 dari 30')
            ->set('perHalaman', 7)->assertSet('perHalaman', 25)->assertSee('Menampilkan 1–25 dari 30');
    }
}
