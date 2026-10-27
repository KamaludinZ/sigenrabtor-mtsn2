<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\DasarHukumRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Livewire\Pengaturan\DasarHukum;
use App\Support\Pengaturan\DaftarDasarHukum;
use App\Support\Stub\StubData;
use Livewire\Livewire;
use Tests\TestCase;

class DaftarDasarHukumTest extends TestCase
{
    private const RKTM = 'Surat Keputusan Kepala MTsN 2 Kota Malang tentang Rencana Kerja Tahunan Madrasah (RKTM) MTsN 2 Kota Malang Tahun';

    protected function setUp(): void
    {
        parent::setUp();
        session(['stub_role' => 'admin']);
    }

    /** @return list<string> */
    private function tersimpan(int $tahun = 2026): array
    {
        return array_column(app(DasarHukumRepository::class)->untukTahun($tahun)['dasar_hukum'], 'teks');
    }

    private function riwayatTerakhir(): array
    {
        return app(RiwayatPengaturanRepository::class)->semua()[0];
    }

    public function test_aturan_rapikan_cetak_dan_galat(): void
    {
        $this->assertSame('Peraturan Menteri Agama Nomor 1', DaftarDasarHukum::rapikan("  Peraturan   Menteri\nAgama Nomor 1 ; "));
        $this->assertSame('Tahun 2026 (beserta perubahannya)', DaftarDasarHukum::rapikan('Tahun 2026 (beserta perubahannya).'));
        $this->assertSame(['A Tahun 2027;', 'B;', 'C.'], DaftarDasarHukum::cetak(['A Tahun {tahun}.', 'B', 'C;'], 2027));
        $this->assertSame(['Satu-satunya.'], DaftarDasarHukum::cetak(['Satu-satunya;'], 2026));
        $this->assertSame([], DaftarDasarHukum::cetak([], 2026));

        $daftar = [['id' => 'a', 'teks' => 'Undang-Undang Nomor 20 Tahun 2003;'], ['id' => 'b', 'teks' => 'SK RKTM Tahun {tahun}']];
        $this->assertSame('Butir dasar hukum wajib diisi.', DaftarDasarHukum::galat('', $daftar));
        $this->assertSame('Terlalu panjang (maksimal 500 karakter).', DaftarDasarHukum::galat(str_repeat('a', 501), $daftar));
        $this->assertSame('Butir ini sudah ada di nomor 1.', DaftarDasarHukum::galat('undang-undang nomor 20 tahun 2003', $daftar));
        $this->assertNull(DaftarDasarHukum::galat('Undang-Undang Nomor 20 Tahun 2003', $daftar, 'a'), 'Butir yang sedang diubah tidak dibandingkan dengan dirinya.');
        $this->assertSame('Penanda {nama_kegiatan}, {thn} tidak dikenal — di dasar hukum hanya {tahun} (tahun anggaran) yang bisa dipakai.', DaftarDasarHukum::galat('Kegiatan {nama_kegiatan} {thn} {tahun}', $daftar));
        $this->assertNull(DaftarDasarHukum::galat('Peraturan baru Tahun {tahun}', $daftar));
    }

    public function test_tambah_butir_di_akhir_dengan_tanda_baca_otomatis(): void
    {
        Livewire::test(DasarHukum::class)
            ->assertSeeHtml('wire:click="tambahButir"')
            ->call('tambahButir')
            ->assertSet('suntingButir', 'baru')
            ->assertSeeHtml('data-testid="butir-baru"')
            ->set('teksButir', "  Peraturan Menteri Keuangan  tentang Standar Biaya\nMasukan Tahun {tahun}. ")
            ->call('simpanButir')
            ->assertHasNoErrors()
            ->assertSet('suntingButir', null)
            ->assertSet('teksButir', '')
            ->assertDispatched('notify', message: 'Butir dasar hukum ditambahkan untuk TA 2026.', type: 'success')
            ->assertSee('5 butir · tercetak bernomor')
            ->assertSeeHtmlInOrder([self::RKTM.' 2026;', 'data-butir="5"', 'Peraturan Menteri Keuangan tentang Standar Biaya Masukan Tahun 2026.']);

        $this->assertSame('Peraturan Menteri Keuangan tentang Standar Biaya Masukan Tahun {tahun}', $this->tersimpan()[4]);
        $this->assertSame([self::RKTM.' 2026;', 'Peraturan Menteri Keuangan tentang Standar Biaya Masukan Tahun 2026.'], array_slice(StubData::dasarHukum(2026), 3));
        $this->assertCount(3, $this->tersimpan(2025), 'Tahun lain tidak berubah.');
        $this->assertSame('ubah_dasar_hukum', $this->riwayatTerakhir()['aksi']);
        $this->assertSame('Dasar hukum TA 2026 — tambah butir 5: Peraturan Menteri Keuangan tentang Standar Biaya Masukan Tahun {tahun}', $this->riwayatTerakhir()['keterangan']);
    }

    public function test_ubah_butir_dan_galat_isian(): void
    {
        $komponen = Livewire::test(DasarHukum::class)
            ->call('ubahButir', 'dh-2026-2')
            ->assertSet('suntingButir', 'dh-2026-2')
            ->assertSet('teksButir', 'Peraturan Menteri Agama Nomor 10 Tahun 2010 tentang Organisasi dan Tata Kerja Kementerian Agama')
            ->assertSeeHtml('data-testid="isian-butir"')
            ->assertDontSeeHtml('aria-label="Ubah butir 1"')
            ->set('teksButir', '   ')
            ->call('simpanButir')
            ->assertHasErrors(['teksButir'])
            ->assertSee('Butir dasar hukum wajib diisi.')
            ->set('teksButir', 'Undang-Undang Republik Indonesia Nomor 20 Tahun 2003 tentang Sistem Pendidikan Nasional;')
            ->call('simpanButir')
            ->assertSee('Butir ini sudah ada di nomor 1.')
            ->set('teksButir', 'Untuk {nama_kegiatan}')
            ->call('simpanButir')
            ->assertSee('Penanda {nama_kegiatan} tidak dikenal');
        $this->assertStringContainsString('Nomor 10 Tahun 2010', $this->tersimpan()[1], 'Isian yang salah tidak tersimpan.');

        $komponen->set('teksButir', 'Peraturan Menteri Agama Nomor 42 Tahun 2016 tentang Organisasi dan Tata Kerja Kementerian Agama')
            ->call('simpanButir')
            ->assertHasNoErrors()
            ->assertSet('suntingButir', null)
            ->assertDispatched('notify', message: 'Butir dasar hukum diperbarui untuk TA 2026.', type: 'success')
            ->assertSeeHtmlInOrder(['data-id="dh-2026-2"', 'Peraturan Menteri Agama Nomor 42 Tahun 2016 tentang Organisasi dan Tata Kerja Kementerian Agama;']);
        $this->assertSame('Peraturan Menteri Agama Nomor 42 Tahun 2016 tentang Organisasi dan Tata Kerja Kementerian Agama', $this->tersimpan()[1]);
        $this->assertStringStartsWith('Dasar hukum TA 2026 — ubah butir 2: Peraturan Menteri Agama Nomor 10 Tahun 2010', $this->riwayatTerakhir()['keterangan']);

        $jumlahRiwayat = count(app(RiwayatPengaturanRepository::class)->semua());
        $komponen->call('ubahButir', 'dh-2026-1')
            ->call('simpanButir')
            ->assertDispatched('notify', message: 'Tidak ada yang berubah.', type: 'info')
            ->assertSet('suntingButir', null);
        $this->assertCount($jumlahRiwayat, app(RiwayatPengaturanRepository::class)->semua());

        $komponen->call('ubahButir', 'tidak-ada')->assertSet('suntingButir', null);
    }

    /** @return list<string> */
    private function urutanId(int $tahun = 2026): array
    {
        return array_column(app(DasarHukumRepository::class)->untukTahun($tahun)['dasar_hukum'], 'id');
    }

    public function test_hapus_langsung_dan_urungkan_ke_posisi_semula(): void
    {
        $komponen = Livewire::test(DasarHukum::class)
            ->call('hapusButir', 'dh-2026-2')
            ->assertSet('butirTerhapus.posisi', 1)
            ->assertSet('butirTerhapus.butir.id', 'dh-2026-2')
            ->assertSeeHtml('data-testid="butir-terhapus"')
            ->assertSee('Butir 2 dihapus: “Peraturan Menteri Agama Nomor 10 Tahun 2010')
            ->assertSee('3 butir · tercetak bernomor');
        $this->assertSame(['dh-2026-1', 'dh-2026-3', 'dh-2026-4'], $this->urutanId());
        $this->assertStringStartsWith('Dasar hukum TA 2026 — hapus butir 2: Peraturan Menteri Agama Nomor 10', $this->riwayatTerakhir()['keterangan']);

        $komponen->call('urungkanHapusButir')
            ->assertSet('butirTerhapus', null)
            ->assertDispatched('notify', message: 'Butir 2 dikembalikan.', type: 'success')
            ->assertDontSeeHtml('data-testid="butir-terhapus"');
        $this->assertSame(['dh-2026-1', 'dh-2026-2', 'dh-2026-3', 'dh-2026-4'], $this->urutanId());
        $this->assertStringStartsWith('Dasar hukum TA 2026 — kembalikan butir 2:', $this->riwayatTerakhir()['keterangan']);

        // Butir terakhir dihapus: butir sebelumnya kini dicetak dengan titik.
        $komponen->call('hapusButir', 'dh-2026-4');
        $this->assertStringEndsWith('Penyelenggaraan Pendidikan Madrasah.', StubData::dasarHukum(2026)[2]);

        // Urungkan hanya untuk tahun yang sama & hilang setelah aksi lain.
        $komponen->set('tahun', '2025')->assertSet('butirTerhapus', null)->call('urungkanHapusButir');
        $this->assertCount(3, $this->tersimpan(2025));
        $komponen->set('tahun', '')->call('hapusButir', 'dh-2026-3')->call('ubahButir', 'dh-2026-1')->assertSet('butirTerhapus', null)->call('urungkanHapusButir');
        $this->assertSame(['dh-2026-1', 'dh-2026-2'], $this->urutanId());

        $komponen->call('hapusButir', 'tidak-ada');
        $this->assertCount(2, $this->tersimpan());
    }

    public function test_urutkan_dengan_seret_dan_naik_turun(): void
    {
        $komponen = Livewire::test(DasarHukum::class)
            ->assertSeeHtml('wire:sort="pindahkanButir"')
            ->assertSeeHtml('wire:sort:item="dh-2026-1"')
            ->assertSee('seret nomor untuk memindahkan')
            ->assertSeeHtml('aria-label="Naikkan butir 1"')
            ->call('pindahkanButir', 'dh-2026-4', 0)
            ->assertSet('pengumuman', 'Butir 4 dipindah ke nomor 1.')
            ->assertSeeHtmlInOrder(['data-butir="1" data-id="dh-2026-4"', self::RKTM.' 2026;', 'data-butir="4" data-id="dh-2026-3"', 'Penyelenggaraan Pendidikan Madrasah.']);
        $this->assertSame(['dh-2026-4', 'dh-2026-1', 'dh-2026-2', 'dh-2026-3'], $this->urutanId());
        $this->assertStringStartsWith('Dasar hukum TA 2026 — pindah butir 4 ke 1: Surat Keputusan', $this->riwayatTerakhir()['keterangan']);
        $this->assertSame(self::RKTM.' 2026;', StubData::dasarHukum(2026)[0], 'Dokumen mengikuti urutan baru.');

        $komponen->call('geserButir', 'dh-2026-4', 1)
            ->assertDispatched('butir-dipindah', id: 'dh-2026-4', arah: 'turun');
        $this->assertSame(['dh-2026-1', 'dh-2026-4', 'dh-2026-2', 'dh-2026-3'], $this->urutanId());
        $komponen->call('geserButir', 'dh-2026-4', -1)
            ->assertDispatched('butir-dipindah', id: 'dh-2026-4', arah: 'naik');
        $this->assertSame(['dh-2026-4', 'dh-2026-1', 'dh-2026-2', 'dh-2026-3'], $this->urutanId());

        // Di ujung, posisi di luar rentang, atau sedang menyunting: tidak berpindah & tidak dicatat.
        $jumlahRiwayat = count(app(RiwayatPengaturanRepository::class)->semua());
        $komponen->call('geserButir', 'dh-2026-4', -1)->assertNotDispatched('butir-dipindah')
            ->call('pindahkanButir', 'dh-2026-3', 99)
            ->call('ubahButir', 'dh-2026-1')
            ->assertDontSeeHtml('wire:sort="pindahkanButir"')
            ->assertDontSeeHtml('aria-label="Naikkan butir 2"')
            ->call('pindahkanButir', 'dh-2026-1', 3);
        $this->assertSame(['dh-2026-4', 'dh-2026-1', 'dh-2026-2', 'dh-2026-3'], $this->urutanId());
        $this->assertCount($jumlahRiwayat, app(RiwayatPengaturanRepository::class)->semua());

        // Pindah membatalkan urungkan hapus (posisinya tidak lagi berarti).
        $komponen->call('batalButir')->call('hapusButir', 'dh-2026-2')->call('pindahkanButir', 'dh-2026-3', 0)->assertSet('butirTerhapus', null);
        $this->assertSame(['dh-2026-3', 'dh-2026-4', 'dh-2026-1'], $this->urutanId());
    }

    public function test_tahun_kosong_bisa_diisi_dan_tahun_terkunci_hanya_dilihat(): void
    {
        app(DasarHukumRepository::class)->simpan(2026, ['dasar_hukum' => [], 'latar_belakang' => 'Paragraf {tahun}.', 'kalimat_penutup' => 'Kegiatan {nama_kegiatan}.']);

        Livewire::test(DasarHukum::class)
            ->assertSeeHtml('data-testid="dasar-hukum-kosong"')
            ->call('tambahButir')
            ->assertDontSeeHtml('data-testid="dasar-hukum-kosong"')
            ->set('teksButir', 'Butir pertama')
            ->call('simpanButir')
            ->assertSeeHtmlInOrder(['data-butir="1"', 'Butir pertama.']);
        $this->assertSame('Paragraf {tahun}.', app(DasarHukumRepository::class)->untukTahun(2026)['latar_belakang'], 'Paragraf baku tidak ikut berubah.');

        $terkunci = Livewire::test(DasarHukum::class)
            ->call('tambahButir')
            ->set('tahun', '2025')
            ->assertSet('suntingButir', null)
            ->assertDontSeeHtml('wire:click="tambahButir"')
            ->assertDontSeeHtml('aria-label="Ubah butir 1"')
            ->assertDontSeeHtml('wire:sort="pindahkanButir"');
        $terkunci->call('tambahButir')->assertSet('suntingButir', null)
            ->call('ubahButir', 'dh-2025-1')->assertSet('suntingButir', null)
            ->call('hapusButir', 'dh-2025-1')
            ->call('pindahkanButir', 'dh-2025-3', 0);
        $this->assertSame(['dh-2025-1', 'dh-2025-2', 'dh-2025-3'], $this->urutanId(2025));
    }

    public function test_batas_jumlah_butir(): void
    {
        $penuh = array_map(fn (int $i) => ['id' => "b{$i}", 'teks' => "Butir {$i}"], range(1, DaftarDasarHukum::MAKS_BUTIR));
        app(DasarHukumRepository::class)->simpan(2026, ['dasar_hukum' => $penuh, 'latar_belakang' => 'P.', 'kalimat_penutup' => 'K {nama_kegiatan}.']);

        Livewire::test(DasarHukum::class)
            ->call('tambahButir')
            ->assertSet('suntingButir', null)
            ->assertDispatched('notify', message: 'Dasar hukum paling banyak 30 butir.', type: 'error');
    }
}
