<?php

namespace Tests\Feature;

use App\Contracts\KegiatanRepository;
use App\Contracts\NotifikasiRepository;
use App\Livewire\Notifikasi;
use App\Support\Notifikasi\NotifikasiStatus;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class NotifikasiTest extends TestCase
{
    /** Kegiatan dengan riwayat: diajukan leading 1.6, dikembalikan admin (catatan), diajukan ulang, disetujui. */
    private function kegiatan(string $leading = '1.6', int $userId = 1, int $tahun = 2026): int
    {
        return app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Lomba Kebersihan', 'status' => 'disetujui', 'leading' => $leading, 'user_id' => $userId, 'tahun_anggaran' => $tahun,
            'riwayat_status' => [
                ['aksi' => 'dibuat', 'status' => 'draf', 'oleh' => 'Muslimin, M.Pd.', 'peran' => 'leading', 'pada' => '2026-03-01T08:00:00+07:00'],
                ['aksi' => 'ajukan', 'status' => 'diperiksa', 'oleh' => 'Muslimin, M.Pd.', 'peran' => 'leading', 'pada' => '2026-03-02T08:00:00+07:00'],
                ['aksi' => 'kembalikan', 'status' => 'dikembalikan', 'oleh' => 'Koordinator PUSKOM', 'peran' => 'admin', 'pada' => '2026-03-03T08:00:00+07:00', 'catatan' => 'Lengkapi sasaran.'],
                ['aksi' => 'ajukan', 'status' => 'diperiksa', 'oleh' => 'Muslimin, M.Pd.', 'peran' => 'leading', 'pada' => '2026-03-04T08:00:00+07:00'],
                ['aksi' => 'setujui', 'status' => 'disetujui', 'oleh' => 'Koordinator PUSKOM', 'peran' => 'admin', 'pada' => '2026-03-05T08:00:00+07:00'],
            ],
        ]);
    }

    public function test_penerima_sesuai_peran_dan_cakupan(): void
    {
        $id = $this->kegiatan();
        $this->kegiatan('1.2', 4);
        $this->kegiatan('1.6', 1, 2025);
        $semua = app(KegiatanRepository::class)->semua();
        $leading = ['id' => 1, 'role' => 'leading', 'leadings' => ['1.6']];
        $admin = ['id' => 2, 'role' => 'admin', 'leadings' => []];

        $untukLeading = NotifikasiStatus::untuk($leading, $semua, 2026, null);
        $this->assertSame(['setujui', 'kembalikan'], array_column($untukLeading, 'aksi'), 'Hanya keputusan admin, terbaru dulu, kegiatan leading-nya di tahun aktif.');
        $this->assertSame([$id, $id], array_column($untukLeading, 'kegiatan_id'));
        $this->assertSame('Lengkapi sasaran.', $untukLeading[1]['catatan']);
        $this->assertSame('Disetujui — cetak & unggah arsip final', $untukLeading[0]['judul']);

        $untukAdmin = NotifikasiStatus::untuk($admin, $semua, 2026, null);
        $this->assertSame(['ajukan', 'ajukan', 'ajukan', 'ajukan'], array_column($untukAdmin, 'aksi'), 'Admin: pengajuan leading semua kegiatan tahun aktif.');

        $dibaca = NotifikasiStatus::untuk($leading, $semua, 2026, '2026-03-04T00:00:00+07:00');
        $this->assertSame([true, false], array_column($dibaca, 'baru'));
        $this->assertSame(1, NotifikasiStatus::jumlahBaru($dibaca));

        // Akun leading lain hanya melihat kegiatan buatannya/leading-nya.
        $this->assertSame([], NotifikasiStatus::untuk(['id' => 3, 'role' => 'leading', 'leadings' => ['1.1']], $semua, 2026, null));
    }

    public function test_lonceng_menandai_dibaca_saat_dibuka(): void
    {
        Carbon::setTestNow('2026-03-06 09:00:00');
        $this->kegiatan();

        Livewire::test(Notifikasi::class)
            ->assertSeeHtml('data-testid="jumlah-notifikasi"')
            ->assertSee('Notifikasi — 2 belum dibaca')
            ->assertSeeInOrder(['Disetujui — cetak &amp; unggah arsip final', 'Lomba Kebersihan', 'Dikembalikan untuk perbaikan', 'Lengkapi sasaran.'], false)
            ->assertSeeHtml('href="'.route('kegiatan.edit', ['kegiatan' => $this->idKegiatanBaru()]).'"')
            ->call('buka')
            ->assertDontSeeHtml('data-testid="jumlah-notifikasi"')
            ->assertSeeHtml('data-baru');
        $this->assertSame('2026-03-06T09:00:00+07:00', app(NotifikasiRepository::class)->dibacaSampai(1));

        // Dimuat ulang: sudah dibaca, tidak disorot lagi.
        Livewire::test(Notifikasi::class)
            ->assertDontSeeHtml('data-testid="jumlah-notifikasi"')
            ->assertDontSeeHtml('data-baru');

        session(['stub_role' => 'admin']);
        Livewire::test(Notifikasi::class)
            ->assertSee('Notifikasi — 2 belum dibaca')
            ->assertSee('Pengajuan baru untuk diperiksa')
            ->assertSeeHtml('href="'.route('pemeriksaan.periksa', ['kegiatan' => $this->idKegiatanBaru()]).'"');
        Carbon::setTestNow();
    }

    public function test_lonceng_tampil_di_bilah_atas(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeLivewire(Notifikasi::class)
            ->assertSee('Belum ada notifikasi.');
    }
}
