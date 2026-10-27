<?php

namespace Tests\Feature\Pemeriksaan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Pemeriksaan\Daftar;
use App\Support\Pemeriksaan\AntreanPemeriksaan;
use Livewire\Livewire;
use Tests\TestCase;

class DaftarPemeriksaanTest extends TestCase
{
    /** Kegiatan contoh berstatus tertentu dengan riwayat pengajuan pada $pada. */
    private function kegiatan(string $nama, string $status, string $pada, string $leading = '1.1', int $userId = 3): int
    {
        $riwayat = [['aksi' => 'dibuat', 'status' => 'draf', 'oleh' => 'Siti Rohmah, M.Pd.', 'peran' => 'leading', 'pada' => '2026-01-02T08:00:00+07:00']];
        $riwayat[] = ['aksi' => 'ajukan', 'status' => 'diperiksa', 'oleh' => 'Siti Rohmah, M.Pd.', 'peran' => 'leading', 'pada' => $pada];
        if ($status !== 'diperiksa') {
            $riwayat[] = ['aksi' => ['dikembalikan' => 'kembalikan', 'disetujui' => 'setujui', 'final' => 'finalkan'][$status], 'status' => $status, 'oleh' => 'Koordinator PUSKOM', 'peran' => 'admin', 'pada' => '2026-02-10T09:00:00+07:00'];
        }

        return app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => $nama,
            'status' => $status,
            'leading' => $leading,
            'user_id' => $userId,
            'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]],
            'riwayat_status' => $riwayat,
        ]);
    }

    public function test_hanya_admin_dan_menu_aktif(): void
    {
        $this->get(route('pemeriksaan.index'))->assertForbidden();

        $this->withSession(['stub_role' => 'admin'])->get(route('pemeriksaan.index'))
            ->assertOk()
            ->assertSeeLivewire(Daftar::class)
            ->assertSee('<title>Pemeriksaan · ', false)
            ->assertSeeHtml('href="'.route('pemeriksaan.index').'"')
            ->assertSeeHtml('aria-current="page"');
    }

    public function test_antrean_kosong_saat_semua_contoh_masih_draf(): void
    {
        Livewire::test(Daftar::class)
            ->assertSet('tab', 'diperiksa')
            ->assertSeeHtml('data-testid="pemeriksaan-kosong"')
            ->assertSee('Tidak ada pengajuan yang menunggu diperiksa.')
            ->set('tab', 'draf')
            ->assertDontSeeHtml('data-testid="pemeriksaan-kosong"')
            ->assertSeeHtml('data-kegiatan="1"')
            ->assertSeeHtml('data-kegiatan="3"')
            ->assertSee('Koordinator PUSKOM')
            ->assertSeeHtml('data-sumber="Komite"')
            ->assertSee('Melebihi pagu');
    }

    public function test_jumlah_per_tab_dan_urutan_antrean(): void
    {
        $baru = $this->kegiatan('Lomba Kebersihan Kelas', 'diperiksa', '2026-03-05T10:00:00+07:00');
        $lama = $this->kegiatan('Pelatihan Guru Penggerak', 'diperiksa', '2026-03-01T10:00:00+07:00', '1.2', 4);
        $this->kegiatan('Studi Banding', 'dikembalikan', '2026-02-01T10:00:00+07:00');
        $this->kegiatan('Wisuda Kelas IX', 'final', '2026-01-20T10:00:00+07:00');

        $this->assertSame(
            ['diperiksa' => 2, 'dikembalikan' => 1, 'disetujui' => 0, 'final' => 1, 'draf' => $this->jumlahContohKegiatan()],
            Livewire::test(Daftar::class)->instance()->jumlah(),
        );

        Livewire::test(Daftar::class)
            ->assertSeeHtmlInOrder(['data-kegiatan="'.$lama.'"', 'data-kegiatan="'.$baru.'"'])
            ->assertSeeInOrder(['Pelatihan Guru Penggerak', 'Ahmad Fauzan, S.Pd.', 'Diajukan 1 Maret 2026 oleh Siti Rohmah, M.Pd.'])
            ->assertSee('Periksa')
            ->set('tab', 'dikembalikan')
            ->assertSee('Studi Banding')
            ->assertSee('Dikembalikan untuk perbaikan 10 Februari 2026 oleh Koordinator PUSKOM')
            ->assertDontSee('Lomba Kebersihan Kelas')
            ->set('tab', 'final')
            ->assertSee('Wisuda Kelas IX')
            ->assertSee(AntreanPemeriksaan::KETERANGAN['final']);
    }

    public function test_cari_dan_saringan_leading_di_url(): void
    {
        $this->kegiatan('Lomba Kebersihan Kelas', 'diperiksa', '2026-03-05T10:00:00+07:00');
        $this->kegiatan('Pelatihan Guru Penggerak', 'diperiksa', '2026-03-01T10:00:00+07:00', '1.2', 4);

        Livewire::test(Daftar::class)
            ->set('cari', 'fauzan')
            ->assertSee('Pelatihan Guru Penggerak')
            ->assertDontSee('Lomba Kebersihan Kelas')
            ->set('cari', '')
            ->set('leading', '1.1')
            ->assertSee('Lomba Kebersihan Kelas')
            ->assertDontSee('Pelatihan Guru Penggerak')
            ->set('cari', 'tidak ada')
            ->assertSee('Tidak ada kegiatan yang cocok.')
            ->call('hapusSaringan')
            ->assertSet('cari', '')
            ->assertSet('leading', '')
            ->assertSee('Pelatihan Guru Penggerak');

        $lain = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Draf Kesiswaan', 'leading' => '1.2']);

        Livewire::withQueryParams(['status' => 'acak'])->test(Daftar::class)->assertSet('tab', 'diperiksa');
        Livewire::withQueryParams(['status' => 'draf', 'leading' => '1.6'])->test(Daftar::class)
            ->assertSet('tab', 'draf')
            ->assertSeeHtml('data-kegiatan="2"')
            ->assertDontSeeHtml('data-kegiatan="'.$lain.'"');
    }

    public function test_hanya_kegiatan_tahun_aktif(): void
    {
        app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Kegiatan Tahun Lalu', 'status' => 'diperiksa', 'tahun_anggaran' => 2025]);

        Livewire::test(Daftar::class)
            ->assertSeeHtml('data-testid="pemeriksaan-kosong"')
            ->assertDontSee('Kegiatan Tahun Lalu');
    }
}
