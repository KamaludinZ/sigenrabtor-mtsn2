<?php

namespace Tests\Feature\Pemeriksaan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Kegiatan\RiwayatStatus;
use App\Support\Pemeriksaan\AntreanPemeriksaan;
use App\Support\Pemeriksaan\DaftarPemeriksaan;
use Tests\TestCase;

class DataPemeriksaanTest extends TestCase
{
    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    /** Kegiatan diajukan $hariLalu hari yang lalu. */
    private function diajukan(string $nama, int $hariLalu, string $leading = '1.6'): int
    {
        $this->travel(-$hariLalu)->days();
        $ajukan = RiwayatStatus::entri('ajukan', StatusKegiatan::Diperiksa, self::LEADING);
        $this->travelBack();

        return app(KegiatanRepository::class)->simpan(['nama_kegiatan' => $nama, 'leading' => $leading, 'user_id' => 1, 'status' => 'diperiksa', 'riwayat_status' => [$ajukan], 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 2_000_000]]]);
    }

    public function test_saringan_dirapikan(): void
    {
        $this->assertSame(['status' => 'diperiksa', 'cari' => '', 'leading' => '', 'hal' => 1], DaftarPemeriksaan::saringan([]));
        $this->assertSame(['status' => 'diperiksa', 'cari' => 'rapat kerja', 'leading' => '', 'hal' => 1], DaftarPemeriksaan::saringan(['status' => 'acak', 'cari' => "  rapat \n kerja ", 'leading' => '9.9', 'hal' => 'x']));
        $this->assertSame(['status' => 'final', 'cari' => '', 'leading' => '1.9', 'hal' => 3], DaftarPemeriksaan::saringan(['status' => 'final', 'leading' => '1.9', 'hal' => '3']), 'Leading nonaktif tetap bisa disaring.');
    }

    public function test_endpoint_antrean_per_tab_cari_dan_leading(): void
    {
        $lama = $this->diajukan('Rapat Kerja Madrasah', 5);
        $baru = $this->diajukan('Lomba Sains Siswa', 1);
        $lain = $this->diajukan('Pentas Seni Kesiswaan', 3, '1.2');

        $this->withSession(['stub_role' => 'leading'])->getJson(route('pemeriksaan.data'))->assertForbidden();
        $this->withSession(['stub_role' => 'admin']);

        $respons = $this->getJson(route('pemeriksaan.data'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('tahun', 2026)
            ->assertJsonPath('saringan.status', 'diperiksa')
            ->assertJsonPath('jumlah', 3);
        $this->assertSame([$lama, $lain, $baru], array_column($respons->json('baris'), 'id'), 'Perlu diperiksa: pengajuan terlama dulu.');
        $baris = $respons->json('baris.0');
        $this->assertSame([5, ['kunci' => 'diperiksa', 'label' => 'Diperiksa'], 'Muslimin, M.Pd.', 'Tim Penjamin Mutu', route('pemeriksaan.periksa', ['kegiatan' => $lama])], [$baris['menunggu_hari'], $baris['status'], $baris['pembuat'], $baris['nama_leading'], $baris['tautan']]);
        $this->assertSame([['sumber' => 'Komite', 'total' => 0, 'status_pagu' => 'sisa', 'selisih' => 2_000_000, 'keterangan_pagu' => 'Sisa pagu Rp2.000.000']], $baris['anggaran']);

        $tab = collect($respons->json('tab'))->keyBy('kunci');
        $this->assertSame(array_keys(AntreanPemeriksaan::TAB), $tab->keys()->all());
        $this->assertSame(3, $tab['diperiksa']['jumlah']);
        $this->assertSame(count(DaftarPemeriksaan::semua()), $tab->sum('jumlah'));

        $this->getJson(route('pemeriksaan.data', ['cari' => 'SAINS']))->assertJsonPath('jumlah', 1)->assertJsonPath('baris.0.id', $baru);
        $this->getJson(route('pemeriksaan.data', ['cari' => 'muslimin']))->assertJsonPath('jumlah', 3);
        $this->getJson(route('pemeriksaan.data', ['leading' => '1.2']))->assertJsonPath('jumlah', 1)->assertJsonPath('baris.0.id', $lain);
        $this->getJson(route('pemeriksaan.data', ['status' => 'draf']))->assertJsonPath('saringan.status', 'draf')->assertJsonPath('baris.0.menunggu_hari', null);
        $this->getJson(route('pemeriksaan.data', ['hal' => 9]))->assertJsonPath('halaman', ['ke' => 1, 'dari' => 1, 'per' => DaftarPemeriksaan::PER_HALAMAN]);
    }
}
