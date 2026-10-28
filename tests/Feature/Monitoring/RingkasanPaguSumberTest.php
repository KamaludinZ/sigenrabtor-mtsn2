<?php

namespace Tests\Feature\Monitoring;

use App\Contracts\KegiatanRepository;
use App\Support\Monitoring\RingkasanAnggaran;
use Tests\TestCase;

class RingkasanPaguSumberTest extends TestCase
{
    private function admin()
    {
        return $this->withSession(['stub_role' => 'admin']);
    }

    public function test_pagu_dipa_dan_komite_tahun_aktif(): void
    {
        $this->withSession(['stub_role' => 'leading'])->getJson(route('monitoring.pagu'))->assertForbidden();

        $respons = $this->admin()->getJson(route('monitoring.pagu'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('tahun', 2026)
            ->assertJsonPath('jumlah_kegiatan', 3)
            ->assertJsonPath('sumber.0.sumber', 'DIPA')
            ->assertJsonPath('sumber.1.sumber', 'Komite')
            ->assertJsonPath('kombinasi_sumber', ['DIPA' => 0, 'Komite' => 1, 'keduanya' => 2, 'belum' => 0]);

        $ringkasan = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), ['tahun' => 2026]);
        $perbandingan = $ringkasan->perbandinganSumber();
        foreach (['DIPA' => 0, 'Komite' => 1] as $sumber => $i) {
            $this->assertSame(
                [$perbandingan[$sumber]['pagu'], $perbandingan[$sumber]['total'], $perbandingan[$sumber]['kelebihan'], $perbandingan[$sumber]['porsi_pagu']],
                [$respons->json("sumber.{$i}.pagu"), $respons->json("sumber.{$i}.total"), $respons->json("sumber.{$i}.kelebihan"), $respons->json("sumber.{$i}.porsi_pagu")],
                $sumber,
            );
        }
        // DIPA memuat RAB tanpa pagu (contoh 3): selisih & persen tidak bisa dihitung; Komite melebihi Rp450.000.
        $this->assertSame([null, null, 900_000], [$respons->json('sumber.0.selisih'), $respons->json('sumber.0.persen'), $respons->json('sumber.0.total_tanpa_pagu')]);
        $this->assertSame([-450_000, 102.3, 450_000], [$respons->json('sumber.1.selisih'), $respons->json('sumber.1.persen'), $respons->json('sumber.1.kelebihan')]);
        $this->assertSame($ringkasan->total()['pagu'], $respons->json('gabungan.pagu'));
        $this->assertSame(['1.6'], array_column($respons->json('per_leading'), 'kode'), 'Hanya leading yang punya kegiatan.');
        $this->assertSame(20_450_000, $respons->json('per_leading.0.sumber.Komite.total'));
    }

    public function test_saringan_sama_dengan_halaman_monitoring(): void
    {
        app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Lomba Kreativitas Siswa', 'leading' => '1.2', 'user_id' => 2, 'status' => 'diperiksa', 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 12_000_000]]]);

        $this->admin()->getJson(route('monitoring.pagu', ['leading' => '1.2']))
            ->assertJsonPath('saringan', ['leading' => '1.2'])
            ->assertJsonPath('label_saringan.leading', 'Waka Kesiswaan')
            ->assertJsonPath('jumlah_kegiatan', 1)
            ->assertJsonPath('sumber.1.pagu', 12_000_000)
            ->assertJsonPath('sumber.0.rab', 0);
        $this->admin()->getJson(route('monitoring.pagu', ['sumber' => 'DIPA', 'status' => 'draf']))
            ->assertJsonPath('jumlah_kegiatan', 2);
        $this->admin()->getJson(route('monitoring.pagu', ['tahun' => '2025']))
            ->assertJsonPath('tahun', 2025)
            ->assertJsonPath('jumlah_kegiatan', 0);
        $this->admin()->getJson(route('monitoring.pagu', ['leading' => '9.9', 'dari' => '2026-12-01', 'sampai' => '2026-01-01']))
            ->assertJsonPath('saringan', ['dari' => '2026-12-01', 'sampai' => '2026-01-01'])
            ->assertJsonPath('rentang_terbalik', true)
            ->assertJsonPath('jumlah_kegiatan', 4);
    }
}
