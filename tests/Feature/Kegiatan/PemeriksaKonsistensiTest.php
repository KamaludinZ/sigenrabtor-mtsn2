<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\MatrikJadwal;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\KonsistensiBulan;
use App\Support\Kegiatan\MatrikTahapan;
use App\Support\Kegiatan\PemeriksaKonsistensi;
use App\Support\Kegiatan\SimpanMatrikJadwal;
use Livewire\Livewire;
use Tests\TestCase;

class PemeriksaKonsistensiTest extends TestCase
{
    private function kegiatan(int $id): array
    {
        return app(KegiatanRepository::class)->temukan($id);
    }

    public function test_kata_khas_nama_kegiatan(): void
    {
        $this->assertSame(['workshop' => false, 'kurikulum' => false, 'perangkat' => false, 'pembelajaran' => false], PemeriksaKonsistensi::kataKhas('Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran'));
        $this->assertSame(['review' => false, 'rkjm' => true, 'rktm' => true], PemeriksaKonsistensi::kataKhas('Review RKJM dan Penyusunan RKTM'));
        $this->assertSame(['pelatihan' => false, 'esq' => true], PemeriksaKonsistensi::kataKhas('Pelatihan ESQ Guru'), 'Akronim 3 huruf tetap khas.');
        $this->assertSame([], PemeriksaKonsistensi::kataKhas('Rapat Kerja Ka. TU'), 'Kata umum & akronim 2 huruf diabaikan.');
    }

    public function test_bulan_dari_data_tersimpan_maupun_isian(): void
    {
        $kegiatan = $this->kegiatan(3);
        $tersimpan = PemeriksaKonsistensi::bulan($kegiatan);
        $repo = app(MatrikJadwalRepository::class);

        $this->assertEquals(new KonsistensiBulan([11], 11, $repo->matrik(3)->butir, $repo->jadwal(3)->baris), $tersimpan);
        $this->assertSame(5, $tersimpan->jumlahPeringatan());

        $isian = PemeriksaKonsistensi::bulan($kegiatan, MatrikTahapan::dari([]), JadwalKegiatan::dari(['jadwal' => []]));
        $this->assertSame([], $isian->temuan(), 'Matrik & jadwal yang sedang disunting dipakai bila diberikan.');
    }

    public function test_uraian_tersalin_dari_kegiatan_lain(): void
    {
        // Contoh 3 (Review RKJM) memuat baris jadwal "Pelaksanaan workshop kurikulum" dari contoh 1.
        $temuan = PemeriksaKonsistensi::tersalin($this->kegiatan(3));
        $this->assertSame(['tersalin-jadwal-4'], array_column($temuan, 'kunci'));
        $this->assertSame(['baris-jadwal-4', 'saran', ['id' => 1, 'nama' => 'Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran'], ['workshop', 'kurikulum']], [$temuan[0]['tautan'], $temuan[0]['tingkat'], $temuan[0]['kegiatan'], $temuan[0]['kata']]);
        $this->assertSame('Baris 4 jadwal “Pelaksanaan workshop kurikulum” memuat kata khas kegiatan lain “Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran” (workshop, kurikulum) — periksa apakah tersalin.', $temuan[0]['pesan']);

        // Kata yang juga ada di nama sendiri (mis. "pembelajaran" pada Supervisi … Refleksi Pembelajaran) tidak dihitung.
        $this->assertSame([], PemeriksaKonsistensi::tersalin($this->kegiatan(1)));
        $this->assertSame([], PemeriksaKonsistensi::tersalin($this->kegiatan(2)));
    }

    public function test_rincian_rab_dan_matrik_serta_akronim(): void
    {
        $esq = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Pelatihan ESQ Guru dan Karyawan', 'leading' => '1.6']);
        app(RabRepository::class)->tambahItem(2, 'Komite', ['kode' => '', 'uraian' => 'Honorarium trainer ESQ', 'kategori' => 'honor', 'volume' => 1, 'satuan' => 'OJ', 'harga_satuan' => 500_000]);
        SimpanMatrikJadwal::simpanMatrik(2, ['pelaksanaan' => [['uraian' => 'Review RKJM tim', 'bulan' => [8]]]]);

        $temuan = array_column(PemeriksaKonsistensi::tersalin($this->kegiatan(2)), null, 'kunci');
        $this->assertSame(['tersalin-matrik-pelaksanaan-1', 'tersalin-rab-Komite-4'], array_keys($temuan));
        $this->assertSame([$esq, ['esq'], 'rab-Komite'], [$temuan['tersalin-rab-Komite-4']['kegiatan']['id'], $temuan['tersalin-rab-Komite-4']['kata'], $temuan['tersalin-rab-Komite-4']['tautan']]);
        $this->assertStringStartsWith('Rincian Komite nomor 4 “Honorarium trainer ESQ”', $temuan['tersalin-rab-Komite-4']['pesan']);
        $this->assertSame([3, ['review', 'rkjm']], [$temuan['tersalin-matrik-pelaksanaan-1']['kegiatan']['id'], $temuan['tersalin-matrik-pelaksanaan-1']['kata']]);
        $this->assertSame(['tersalin-matrik-pelaksanaan-1'], array_column(PemeriksaKonsistensi::tersalin($this->kegiatan(2), rab: false), 'kunci'));

        // Kegiatan tahun anggaran lain tidak dibandingkan.
        app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Lomba LKTI Siswa', 'leading' => '1.6', 'tahun_anggaran' => 2025]);
        app(RabRepository::class)->tambahItem(2, 'Komite', ['kode' => '', 'uraian' => 'Hadiah juara LKTI', 'kategori' => 'lain', 'volume' => 1, 'satuan' => 'paket', 'harga_satuan' => 100_000]);
        $this->assertNotContains('tersalin-rab-Komite-5', array_column(PemeriksaKonsistensi::tersalin($this->kegiatan(2)), 'kunci'));
    }

    public function test_ringkasan_endpoint_dan_halaman(): void
    {
        $periksa = PemeriksaKonsistensi::periksa($this->kegiatan(3));
        $this->assertSame([6, 1, 5, 2], [count($periksa['bulan']), count($periksa['tersalin']), $periksa['jumlah_peringatan'], $periksa['jumlah_saran']]);

        $this->getJson(route('kegiatan.matrik_jadwal', ['kegiatan' => 3]))
            ->assertJsonPath('konsistensi.tersalin.0.kunci', 'tersalin-jadwal-4')
            ->assertJsonCount(6, 'konsistensi.temuan');

        $halaman = Livewire::test(MatrikJadwal::class, ['kegiatan' => 3]);
        $this->assertEquals(PemeriksaKonsistensi::bulan($this->kegiatan(3)), $halaman->instance()->konsistensi);
    }
}
