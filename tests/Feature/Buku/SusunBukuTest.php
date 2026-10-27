<?php

namespace Tests\Feature\Buku;

use App\Contracts\BukuDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Livewire\Buku\Daftar;
use App\Support\Dokumen\PengambilanBuku;
use Livewire\Livewire;
use Tests\TestCase;

class SusunBukuTest extends TestCase
{
    public function test_riwayat_contoh_tiap_buku(): void
    {
        $riwayat = app(BukuDokumenRepository::class)->riwayat(2026);
        $this->assertSame([['1.6', 'Komite', 3], ['1.6', 'DIPA', 2]], array_map(fn ($b) => [$b['leading'], $b['sumber'], $b['jumlah_kegiatan']], $riwayat), 'Terbaru dulu.');
        $this->assertSame([], app(BukuDokumenRepository::class)->riwayat(2025));

        Livewire::test(Daftar::class)
            ->assertSeeHtml('data-testid="riwayat-buku"')
            ->assertSee('Koordinator PUSKOM · 2 kegiatan · ± 18 halaman')
            ->assertSee('PDF belum digabung');
    }

    public function test_susun_buku_mengambil_kegiatan_bertahap_lalu_mencatat_riwayat(): void
    {
        $komponen = Livewire::test(Daftar::class)
            ->assertSeeHtml('data-testid="susun-buku"')
            ->call('susun')
            ->assertSet('proses.tahap', 'ambil')
            ->assertSet('proses.antrean', [3, 2])
            ->assertSee('Mengambil kegiatan 1 dari 2…');

        $komponen->call('lanjutkanSusun')
            ->assertSet('proses.posisi', 1)
            ->assertSet('proses.tahap', 'ambil')
            ->assertSee('Mengambil kegiatan 2 dari 2…')
            ->call('lanjutkanSusun')
            ->assertSet('proses.tahap', 'selesai')
            ->assertSet('proses.posisi', 2)
            ->assertDispatched('notify', message: 'Buku tersusun: 2 kegiatan, 2 masih DRAF. Berkas PDF digabung setelah mesin dokumen tersedia.')
            ->assertSee('Buku tersusun')
            ->assertSee('2 dari 2 kegiatan diambil · 0 siap · 2 DRAF')
            ->assertSee('Belum disetujui admin (status Draf).');

        $terbaru = app(BukuDokumenRepository::class)->riwayat(2026)[0];
        $this->assertSame(['1.6', 'DIPA', 2, true, 'Muslimin, M.Pd.'], [$terbaru['leading'], $terbaru['sumber'], $terbaru['jumlah_kegiatan'], $terbaru['ada_draf'], $terbaru['dibuat_oleh']]);
        $this->assertSame([3, 2], array_column($terbaru['kegiatan'], 'id'));

        $komponen->call('tutupSusun')->assertSet('proses', null);
    }

    public function test_hentikan_dan_ganti_buku(): void
    {
        $jumlah = count(app(BukuDokumenRepository::class)->riwayat(2026));

        $komponen = Livewire::test(Daftar::class)->call('susun')->call('lanjutkanSusun')->call('hentikanSusun')
            ->assertSet('proses.tahap', 'dihentikan')
            ->assertSee('Penyusunan dihentikan')
            ->assertSee('1 dari 2 kegiatan diambil');
        $komponen->call('lanjutkanSusun')->assertSet('proses.posisi', 1);
        $this->assertCount($jumlah, app(BukuDokumenRepository::class)->riwayat(2026), 'Penyusunan yang dihentikan tidak dicatat.');

        $komponen->call('pilih', '1.6', 'Komite')->assertSet('proses', null);

        // Buku tanpa kegiatan tidak bisa disusun.
        session(['stub_role' => 'admin']);
        Livewire::withQueryParams(['leading' => '1.1', 'sumber' => 'DIPA'])->test(Daftar::class)
            ->assertDontSeeHtml('data-testid="susun-buku"')
            ->call('susun')
            ->assertSet('proses', null);
    }

    public function test_pengambilan_satu_kegiatan(): void
    {
        $hasil = PengambilanBuku::ambil(app(KegiatanRepository::class)->temukan(3), 'Komite', 11);

        $this->assertSame(['id' => 3, 'nama' => 'Review RKJM dan Penyusunan RKTM', 'halaman' => 11, 'hasil' => 'draf'], array_diff_key($hasil, ['pesan' => true]));
        $this->assertSame('Belum disetujui admin (status Draf).', $hasil['pesan']);
    }
}
