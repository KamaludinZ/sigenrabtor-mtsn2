<?php

namespace Tests\Feature\Monitoring;

use App\Contracts\KegiatanRepository;
use App\Livewire\Monitoring\Anggaran;
use App\Support\Monitoring\DaftarKegiatanMonitoring;
use App\Support\Monitoring\RingkasanAnggaran;
use Tests\TestCase;

class DaftarKegiatanMonitoringTest extends TestCase
{
    private function admin()
    {
        return $this->withSession(['stub_role' => 'admin']);
    }

    public function test_daftar_kegiatan_beserta_total_rab(): void
    {
        $this->withSession(['stub_role' => 'leading'])->getJson(route('monitoring.kegiatan'))->assertForbidden();

        $respons = $this->admin()->getJson(route('monitoring.kegiatan'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('urut', 'total')
            ->assertJsonPath('arah', 'desc')
            ->assertJsonPath('jumlah', 3)
            ->assertJsonPath('halaman', 1);

        $rincian = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), ['tahun' => 2026])->rincianKegiatan();
        $this->assertSame(array_column($rincian, 'id'), array_column($respons->json('data'), 'id'), 'Urutan bawaan sama dengan tabel halaman (total RAB terbesar).');
        $this->assertSame(array_column($rincian, 'total'), array_column($respons->json('data'), 'total'));

        $tiga = collect($respons->json('data'))->firstWhere('id', 3);
        $this->assertSame(['Review RKJM dan Penyusunan RKTM', 'draf', 'Draf', null, null, 450_000, 1, 1], [$tiga['nama'], $tiga['status'], $tiga['status_label'], $tiga['selisih'], $tiga['persen'], $tiga['kelebihan'], $tiga['melebihi'], $tiga['tanpa_pagu']]);
        $this->assertSame(
            [['DIPA', null, 'tanpa_pagu', 'Pagu belum diisi'], ['Komite', 7_500_000, 'melebihi', 'Melebihi pagu Rp450.000']],
            array_map(fn (array $r) => [$r['sumber'], $r['pagu'], $r['status_pagu'], $r['keterangan_pagu']], $tiga['rab']),
        );
        $this->assertSame(route('pemeriksaan.periksa', ['kegiatan' => 3]), $tiga['tautan']);
    }

    public function test_urutan_saringan_dan_halaman(): void
    {
        $this->assertSame(['Review RKJM dan Penyusunan RKTM', 'Supervisi Akademik dan Refleksi Pembelajaran', 'Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran'], array_column($this->admin()->getJson(route('monitoring.kegiatan', ['urut' => 'nama']))->assertJsonPath('arah', 'asc')->json('data'), 'nama'));
        $this->assertSame(3, $this->admin()->getJson(route('monitoring.kegiatan', ['urut' => 'kelebihan']))->json('data.0.id'), 'Kelebihan pagu terbesar dulu.');
        $this->admin()->getJson(route('monitoring.kegiatan', ['urut' => 'asal', 'arah' => 'ke-atas']))->assertJsonPath('urut', 'total')->assertJsonPath('arah', 'desc');
        $this->admin()->getJson(route('monitoring.kegiatan', ['sumber' => 'DIPA']))->assertJsonPath('jumlah', 2);
        $this->admin()->getJson(route('monitoring.kegiatan', ['hal' => 9]))->assertJsonPath('halaman', 1);

        for ($i = 1; $i <= DaftarKegiatanMonitoring::PER_HALAMAN; $i++) {
            app(KegiatanRepository::class)->simpan(['nama_kegiatan' => "Kegiatan Uji Monitoring {$i}", 'leading' => '1.6']);
        }
        $this->admin()->getJson(route('monitoring.kegiatan', ['hal' => 2]))
            ->assertJsonPath('jumlah', 23)
            ->assertJsonPath('jumlah_halaman', 2)
            ->assertJsonPath('halaman', 2)
            ->assertJsonCount(3, 'data');
        $this->assertSame(Anggaran::PER_HALAMAN, DaftarKegiatanMonitoring::PER_HALAMAN);
    }
}
