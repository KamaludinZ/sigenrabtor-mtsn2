<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\DaftarIsiRepository;
use App\Contracts\KegiatanRepository;
use App\Livewire\Pengaturan\TemplateDaftarIsi;
use App\Livewire\Pengaturan\TemplateDokumen;
use App\Support\Dokumen\DaftarIsi;
use Livewire\Livewire;
use Tests\TestCase;

class DaftarIsiTest extends TestCase
{
    public function test_konfigurasi_dirapikan(): void
    {
        $this->assertSame(DaftarIsi::BAWAAN, DaftarIsi::rapikan(DaftarIsi::BAWAAN));

        $this->assertSame([
            'judul' => 'DAFTAR KEGIATAN', 'subjudul' => 'Subjudul rapi', 'kolom' => ['no', 'nama_kegiatan', 'bulan', 'halaman'],
            'urutan' => 'kode_rktm', 'tampilkan_jumlah' => false, 'catatan' => '',
        ], DaftarIsi::rapikan(['judul' => '   ', 'subjudul' => "  Subjudul \n rapi ", 'kolom' => ['bulan', 'tidak-ada'], 'urutan' => 'acak', 'tampilkan_jumlah' => true, 'catatan' => 5]));

        $this->assertSame('Tim Penjamin Mutu · DIPA · 2026', DaftarIsi::subjudul('{leading} · {sumber} · {tahun}', '1.6', 'DIPA'));
    }

    public function test_baris_buku_disaring_dan_diurutkan(): void
    {
        $repo = app(KegiatanRepository::class);
        foreach ([['6.17.2', 'Zeta', [3]], [null, 'Alfa', [9]], ['6.17.12', 'Beta', [1]]] as [$kode, $nama, $bulan]) {
            $repo->simpan(['nama_kegiatan' => $nama, 'leading' => '1.6', 'kode_rktm' => $kode, 'bulan_pelaksanaan' => $bulan, 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]]]);
        }
        $repo->simpan(['nama_kegiatan' => 'Leading lain', 'leading' => '1.1', 'kode_rktm' => '1.1.2', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => null]]]);
        $semua = $repo->semua();

        $nama = fn (string $urutan) => array_column(DaftarIsi::baris($semua, '1.6', 'DIPA', $urutan), 'nama_kegiatan');
        // Contoh 2 (Supervisi, 6.19.1) & 3 (Review RKJM, 6.17.13) juga milik TPM dengan dana DIPA.
        $this->assertSame(['Zeta', 'Beta', 'Review RKJM dan Penyusunan RKTM', 'Supervisi Akademik dan Refleksi Pembelajaran', 'Alfa'], $nama('kode_rktm'), '6.17.2 sebelum 6.17.12; tanpa kode di akhir.');
        $this->assertSame(['Alfa', 'Beta', 'Review RKJM dan Penyusunan RKTM', 'Supervisi Akademik dan Refleksi Pembelajaran', 'Zeta'], $nama('nama'));
        $this->assertSame('Beta', $nama('bulan')[0]);
        $baris = DaftarIsi::baris($semua, '1.6', 'DIPA', 'kode_rktm');
        $this->assertSame([1, '6.17.2', 'Maret 2026', 0], [$baris[0]['no'], $baris[0]['kode_rktm'], $baris[0]['bulan'], $baris[0]['total']]);
        $this->assertSame([], DaftarIsi::baris($semua, '1.3', 'Komite', 'kode_rktm'));
    }

    public function test_repository_bawaan_dan_khusus(): void
    {
        $repo = app(DaftarIsiRepository::class);
        $this->assertSame(DaftarIsi::BAWAAN, $repo->bawaan(2026));
        $this->assertSame([], $repo->khusus(2026));

        $repo->simpanBawaan(2026, DaftarIsi::rapikan(['judul' => 'DAFTAR ISI'] + DaftarIsi::BAWAAN));
        $repo->simpanKhusus(2026, '1.6', 'DIPA', DaftarIsi::rapikan(['urutan' => 'nama'] + DaftarIsi::BAWAAN));
        $this->assertSame('DAFTAR ISI', $repo->bawaan(2026)['judul']);
        $this->assertSame(['1.6|DIPA'], array_keys($repo->khusus(2026)));
        $this->assertSame(DaftarIsi::BAWAAN, $repo->bawaan(2025), 'Per tahun anggaran.');

        $repo->hapusKhusus(2026, '1.6', 'DIPA');
        $this->assertSame([], $repo->khusus(2026));
    }

    public function test_tab_daftar_isi_pratinjau_dan_simpan_bawaan(): void
    {
        Livewire::withQueryParams(['tab' => 'daftar_isi'])->test(TemplateDokumen::class)
            ->assertSet('tab', 'daftar_isi')
            ->assertSeeLivewire(TemplateDaftarIsi::class);

        Livewire::test(TemplateDaftarIsi::class)
            ->assertSet('leading', null)
            ->assertSet('konfigurasi', DaftarIsi::BAWAAN)
            ->assertSeeHtml('data-testid="pilih-bawaan"')
            ->assertSeeHtml('data-testid="buku-1.6-dipa" data-khusus="tidak"')
            ->assertSee('Contoh buku Tim Penjamin Mutu · DIPA · 2 kegiatan')
            ->assertSeeHtmlInOrder(['data-testid="di-judul-tercetak">DAFTAR KEGIATAN', 'Tim Penjamin Mutu · Sumber Dana DIPA · Tahun Anggaran 2026', 'Review RKJM dan Penyusunan RKTM', 'Supervisi Akademik dan Refleksi Pembelajaran', 'data-testid="di-baris-jumlah"'])
            ->assertSeeHtml('data-kolom-tercetak="halaman"')
            ->assertDontSeeHtml('data-kolom-tercetak="bulan"')
            ->assertSeeHtml('wire:key="simpan-di-terkunci" disabled')
            ->set('konfigurasi.judul', 'DAFTAR ISI KEGIATAN')
            ->set('konfigurasi.kolom', ['kode_rktm', 'bulan'])
            ->assertSeeHtml('data-testid="daftar-isi-berubah"')
            ->assertSeeHtml('data-testid="di-judul-tercetak">DAFTAR ISI KEGIATAN')
            ->assertSeeHtml('data-kolom-tercetak="bulan"')
            ->assertDontSeeHtml('data-testid="di-baris-jumlah"')
            ->assertSee('Aktif bila kolom Jumlah Anggaran ditampilkan.')
            ->call('simpan')
            ->assertDispatched('notify', message: 'Pengaturan bawaan daftar isi tersimpan.')
            ->assertDontSeeHtml('data-testid="daftar-isi-berubah"');

        $bawaan = app(DaftarIsiRepository::class)->bawaan(2026);
        $this->assertSame(['DAFTAR ISI KEGIATAN', ['no', 'kode_rktm', 'nama_kegiatan', 'bulan', 'halaman'], false], [$bawaan['judul'], $bawaan['kolom'], $bawaan['tampilkan_jumlah']]);
    }

    public function test_pengaturan_khusus_per_buku_lalu_kembali_ke_bawaan(): void
    {
        $komponen = Livewire::test(TemplateDaftarIsi::class)
            ->call('pilih', '1.6', 'Komite')
            ->assertSet('leading', '1.6')
            ->assertSet('sumber', 'Komite')
            ->assertSee('Daftar isi Tim Penjamin Mutu · Komite')
            ->assertSee('Mengikuti pengaturan bawaan')
            ->assertSee('Simpan sebagai pengaturan khusus')
            ->set('konfigurasi.urutan', 'nama')
            ->set('konfigurasi.catatan', 'Sumber: RKTM 2026.')
            ->assertSeeHtml('data-testid="di-catatan-tercetak">Sumber: RKTM 2026.')
            ->call('simpan')
            ->assertDispatched('notify', message: 'Daftar isi Tim Penjamin Mutu · Komite tersimpan.')
            ->assertSee('Memakai pengaturan khusus.')
            ->assertSeeHtml('data-testid="buku-1.6-komite" data-khusus="ya"');

        $this->assertSame('nama', app(DaftarIsiRepository::class)->khusus(2026)['1.6|Komite']['urutan']);

        // Bawaan tidak ikut berubah; buku lain tetap mengikuti bawaan.
        $komponen->call('pilih', null, null)->assertSet('konfigurasi.urutan', 'kode_rktm')
            ->call('pilih', '1.6', 'Komite')->assertSet('konfigurasi.urutan', 'nama')
            ->call('kembalikanBawaan')
            ->assertDispatched('notify', message: 'Daftar isi Tim Penjamin Mutu · Komite kembali memakai pengaturan bawaan.')
            ->assertSet('konfigurasi.urutan', 'kode_rktm')
            ->assertSeeHtml('data-testid="buku-1.6-komite" data-khusus="tidak"');

        // Pilihan tak dikenal diabaikan; batalkan mengembalikan isian tersimpan.
        $komponen->call('pilih', '9.9', 'DIPA')->assertSet('leading', '1.6')
            ->call('pilih', '1.6', 'BOS')->assertSet('sumber', 'Komite')
            ->set('konfigurasi.judul', 'X')->call('batalkan')->assertSet('konfigurasi.judul', 'DAFTAR KEGIATAN')
            ->call('pilih', '1.3', 'DIPA')->assertSee('Belum ada kegiatan di buku ini.');
    }
}
