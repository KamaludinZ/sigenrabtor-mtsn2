<?php

namespace Tests\Feature\Pemeriksaan;

use App\Contracts\ArsipFinalRepository;
use App\Contracts\KegiatanRepository;
use App\Livewire\Pemeriksaan\Daftar;
use App\Livewire\Pemeriksaan\Periksa;
use App\Support\Pemeriksaan\RiwayatPemeriksaan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class RiwayatPemeriksaanTest extends TestCase
{
    /** @return list<array<string, mixed>> Dua putaran: dikembalikan setelah 2 hari, lalu disetujui di hari yang sama dan final. */
    private function riwayat(): array
    {
        $r = fn (string $aksi, string $status, string $oleh, string $peran, string $pada, ?string $catatan = null) => array_filter(compact('aksi', 'status', 'oleh', 'peran', 'pada', 'catatan'));

        return [
            $r('dibuat', 'draf', 'Muslimin, M.Pd.', 'leading', '2026-03-01T08:00:00+07:00'),
            $r('ajukan', 'diperiksa', 'Muslimin, M.Pd.', 'leading', '2026-03-02T08:00:00+07:00'),
            $r('kembalikan', 'dikembalikan', 'Koordinator PUSKOM', 'admin', '2026-03-04T10:00:00+07:00', 'Lengkapi sasaran.'),
            $r('ajukan', 'diperiksa', 'Muslimin, M.Pd.', 'leading', '2026-03-05T08:00:00+07:00'),
            $r('setujui', 'disetujui', 'Koordinator PUSKOM', 'admin', '2026-03-05T15:00:00+07:00'),
            $r('finalkan', 'final', 'Muslimin, M.Pd.', 'leading', '2026-03-07T09:00:00+07:00'),
        ];
    }

    private function kegiatan(string $nama = 'Lomba Kebersihan', string $leading = '1.6', string $status = 'final'): int
    {
        return app(KegiatanRepository::class)->simpan(['nama_kegiatan' => $nama, 'status' => $status, 'leading' => $leading, 'riwayat_status' => $this->riwayat(), 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]]]);
    }

    public function test_putaran_per_pengajuan(): void
    {
        $putaran = RiwayatPemeriksaan::putaran(['riwayat_status' => $this->riwayat()], ['DIPA' => ['diunggah_oleh' => 'Muslimin, M.Pd.', 'diunggah_pada' => '2026-03-06T11:00:00+07:00']]);

        $this->assertSame([2, 1, 0], array_column($putaran, 'nomor'), 'Terbaru dulu; putaran 0 = sebelum diajukan.');
        $this->assertSame(['final', 'dikembalikan', 'penyusunan'], array_column($putaran, 'hasil'));
        $this->assertSame([0, 2], [$putaran[0]['lama_hari'], $putaran[1]['lama_hari']]);
        $this->assertSame(['Diputuskan di hari yang sama', 'Diputuskan dalam 2 hari', null], array_map(RiwayatPemeriksaan::teksLama(...), $putaran));
        $this->assertSame(['finalkan', 'unggah_arsip', 'setujui', 'ajukan'], array_column($putaran[0]['peristiwa'], 'aksi'), 'Arsip masuk putaran tempat diunggah, urut waktu terbaru dulu.');
        $this->assertSame('DIPA', $putaran[0]['peristiwa'][1]['sumber']);
        $this->assertSame('Lengkapi sasaran.', $putaran[1]['peristiwa'][0]['catatan']);

        Carbon::setTestNow('2026-03-10 09:00:00');
        $menunggu = RiwayatPemeriksaan::putaran(['riwayat_status' => array_slice($this->riwayat(), 0, 4)])[0];
        $this->assertSame(['berjalan', 5, 'Menunggu 5 hari'], [$menunggu['hasil'], $menunggu['lama_hari'], RiwayatPemeriksaan::teksLama($menunggu)]);
        Carbon::setTestNow();

        $this->assertSame([], RiwayatPemeriksaan::putaran([]));
    }

    public function test_log_tersaring_dan_terbaru_dulu(): void
    {
        $this->kegiatan();
        $this->kegiatan('Studi Banding', '1.2', 'disetujui');
        app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Tahun Lalu', 'tahun_anggaran' => 2025, 'riwayat_status' => $this->riwayat()]);
        $semua = app(KegiatanRepository::class)->semua();

        $log = RiwayatPemeriksaan::log($semua, 2026);
        $this->assertCount(10, $log, 'Lima peristiwa pemeriksaan (tanpa "dibuat") × dua kegiatan tahun aktif.');
        $this->assertSame('finalkan', $log[0]['aksi']);
        $this->assertSame(['kembalikan', 'kembalikan'], array_column(RiwayatPemeriksaan::log($semua, 2026, ['aksi' => 'kembalikan']), 'aksi'));
        $this->assertSame(['Studi Banding'], array_unique(array_column(RiwayatPemeriksaan::log($semua, 2026, ['leading' => '1.2']), 'kegiatan')));
        $this->assertCount(2, RiwayatPemeriksaan::log($semua, 2026, ['cari' => 'lengkapi sasaran']), 'Cari juga di catatan.');
    }

    public function test_tampilan_riwayat_di_halaman_pemeriksaan(): void
    {
        $id = $this->kegiatan();
        $this->kegiatan('Studi Banding', '1.2', 'disetujui');

        Livewire::test(Daftar::class)
            ->assertSeeHtml('data-testid="tampilan-pemeriksaan"')
            ->assertDontSeeHtml('data-testid="log-riwayat"')
            ->set('tampilan', 'riwayat')
            ->assertSeeHtml('data-testid="log-riwayat"')
            ->assertDontSeeHtml('data-testid="tab-pemeriksaan"')
            ->assertSee('Menampilkan 10 dari 10 peristiwa')
            ->assertSeeInOrder(['Ditandai final', 'Lomba Kebersihan', 'Muslimin, M.Pd. (leading)'])
            ->assertSeeHtml('href="'.route('pemeriksaan.periksa', ['kegiatan' => $id]).'"')
            ->set('aksi', 'kembalikan')
            ->assertSee('Menampilkan 2 dari 2 peristiwa')
            ->assertSee('Lengkapi sasaran.')
            ->set('cari', 'tidak ada')
            ->assertSee('Tidak ada riwayat yang cocok.')
            ->call('hapusSaringan')
            ->assertSet('aksi', '')
            ->assertSee('Menampilkan 10 dari 10 peristiwa');

        Livewire::withQueryParams(['tampilan' => 'acak', 'aksi' => 'hapus'])->test(Daftar::class)
            ->assertSet('tampilan', 'antrean')
            ->assertSet('aksi', '');

        // Paging: 30 per halaman.
        foreach (range(1, 6) as $i) {
            $this->kegiatan("Kegiatan {$i}");
        }
        Livewire::withQueryParams(['tampilan' => 'riwayat'])->test(Daftar::class)
            ->assertSee('Menampilkan 30 dari 40 peristiwa')
            ->assertSee('Tampilkan 10 lagi')
            ->call('tampilkanLebih')
            ->assertSee('Menampilkan 40 dari 40 peristiwa')
            ->assertDontSee('Tampilkan 10 lagi');
    }

    public function test_riwayat_per_putaran_di_detail_pemeriksaan(): void
    {
        $id = $this->kegiatan();
        Carbon::setTestNow('2026-03-06 11:00:00');
        app(ArsipFinalRepository::class)->simpan($id, 'DIPA', UploadedFile::fake()->createWithContent('dipa.pdf', '%PDF-1.4'), ['id' => 1, 'name' => 'Muslimin, M.Pd.']);
        Carbon::setTestNow();
        session(['stub_role' => 'admin']);

        Livewire::test(Periksa::class, ['kegiatan' => $id])
            ->assertSee('2× diajukan')
            ->assertSeeHtml('data-putaran="2" data-hasil="final"')
            ->assertSeeHtml('data-putaran="1" data-hasil="dikembalikan"')
            ->assertSeeInOrder(['Pengajuan ke-2', 'Final', 'Diputuskan di hari yang sama', 'Ditandai final', 'Arsip final RAB DIPA diunggah', 'Disetujui', 'Pengajuan ke-1', 'Dikembalikan', 'Diputuskan dalam 2 hari', 'Lengkapi sasaran.', 'Sebelum diajukan', 'Draf dibuat']);
    }
}
