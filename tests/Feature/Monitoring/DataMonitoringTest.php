<?php

namespace Tests\Feature\Monitoring;

use App\Contracts\KegiatanRepository;
use App\Support\MasterKode;
use App\Support\Monitoring\DataMonitoring;
use App\Support\Monitoring\RingkasanAnggaran;
use Tests\TestCase;

class DataMonitoringTest extends TestCase
{
    private function admin()
    {
        return $this->withSession(['stub_role' => 'admin']);
    }

    public function test_data_halaman_monitoring_dalam_satu_permintaan(): void
    {
        $this->withSession(['stub_role' => 'leading'])->getJson(route('monitoring.data'))->assertForbidden();

        $respons = $this->admin()->getJson(route('monitoring.data'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('tahun', 2026)
            ->assertJsonPath('ringkasan.jumlah_kegiatan', 3)
            ->assertJsonPath('ringkasan.per_status.0', ['status' => 'draf', 'label' => 'Draf', 'jumlah' => 3])
            ->assertJsonPath('per_leading.urut', 'kode')
            ->assertJsonPath('kegiatan.urut', 'total')
            ->assertJsonPath('kegiatan.per_halaman', 20)
            ->assertJsonPath('kegiatan.jumlah', 3);

        $ringkasan = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), ['tahun' => 2026]);
        $this->assertSame(RingkasanAnggaran::sel($ringkasan->total()), $respons->json('ringkasan.gabungan'));
        $this->assertSame(RingkasanAnggaran::sel($ringkasan->perSumber()['Komite']), $respons->json('ringkasan.per_sumber.Komite'));
        $this->assertSame(array_column(MasterKode::leadings(), 'kode'), array_column($respons->json('per_leading.data'), 'kode'), 'Semua leading aktif seperti tabel halaman.');
        $this->assertSame(array_column($ringkasan->rincianKegiatan(), 'id'), array_column($respons->json('kegiatan.data'), 'id'));
    }

    public function test_urutan_leading_saringan_dan_ukuran_halaman(): void
    {
        app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Lomba Kreativitas Siswa', 'leading' => '1.2', 'user_id' => 2, 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 12_000_000]]]);

        $this->assertSame(['1.6', '1.2'], array_slice(array_column($this->admin()->getJson(route('monitoring.data', ['urut_leading' => 'komite_pagu']))->assertJsonPath('per_leading.arah', 'desc')->json('per_leading.data'), 'kode'), 0, 2));
        $this->admin()->getJson(route('monitoring.data', ['urut_leading' => 'dipa_total', 'sumber' => 'Komite']))
            ->assertJsonPath('per_leading.urut', 'kode', 'Kolom DIPA tersembunyi saat saringan Komite.')
            ->assertJsonPath('ringkasan.jumlah_kegiatan', 4);
        $this->admin()->getJson(route('monitoring.data', ['leading' => '1.2']))
            ->assertJsonCount(1, 'per_leading.data')
            ->assertJsonPath('per_leading.data.0.kode', '1.2')
            ->assertJsonPath('kegiatan.jumlah', 1);

        for ($i = 1; $i <= 8; $i++) {
            app(KegiatanRepository::class)->simpan(['nama_kegiatan' => "Kegiatan Uji Data Monitoring {$i}", 'leading' => '1.6']);
        }
        $this->admin()->getJson(route('monitoring.data', ['per' => 10, 'hal' => 2]))
            ->assertJsonPath('kegiatan.per_halaman', 10)
            ->assertJsonPath('kegiatan.jumlah_halaman', 2)
            ->assertJsonPath('kegiatan.halaman', 2)
            ->assertJsonCount(2, 'kegiatan.data');
        $this->admin()->getJson(route('monitoring.data', ['per' => 7, 'hal' => 5]))
            ->assertJsonPath('kegiatan.per_halaman', 20)
            ->assertJsonPath('kegiatan.halaman', 1);

        $this->assertTrue(DataMonitoring::urutLeadingSah('jumlah', 'DIPA'));
        $this->assertFalse(DataMonitoring::urutLeadingSah('komite_pagu', 'DIPA'));
        $this->assertFalse(DataMonitoring::urutLeadingSah('asal', ''));
    }
}
