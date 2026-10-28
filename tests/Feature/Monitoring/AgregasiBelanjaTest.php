<?php

namespace Tests\Feature\Monitoring;

use App\Contracts\KegiatanRepository;
use App\Support\Monitoring\AgregasiBelanja;
use App\Support\Monitoring\RingkasanAnggaran;
use App\Support\Stub\StubPengaturan;
use Tests\TestCase;

class AgregasiBelanjaTest extends TestCase
{
    private function admin()
    {
        return $this->withSession(['stub_role' => 'admin']);
    }

    public function test_agregasi_per_kelompok_belanja(): void
    {
        $this->withSession(['stub_role' => 'leading'])->getJson(route('monitoring.agregasi'))->assertForbidden();

        $respons = $this->admin()->getJson(route('monitoring.agregasi'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('jumlah_kegiatan', 3)
            ->assertJsonPath('kelompok.sumber', ['DIPA', 'Komite']);

        $rekap = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), ['tahun' => 2026])->perKelompok();
        $this->assertSame($rekap['total'], $respons->json('kelompok.total'));
        $this->assertSame($rekap['jumlahItem'], $respons->json('kelompok.jumlah_item'));
        foreach ($rekap['kelompok'] as $i => $k) {
            $baris = $respons->json("kelompok.kelompok.{$i}");
            $this->assertSame([$k['kunci'], $k['total'], $k['persen'], $k['jumlahItem'], $k['kegiatan'], $k['perSumber']], [$baris['kunci'], $baris['total'], $baris['persen'], $baris['jumlah_item'], $baris['jumlah_kegiatan'], $baris['per_sumber']]);
            $this->assertCount(min($k['jumlahItem'], AgregasiBelanja::MAKS_ITEM), $baris['item_terbesar']);
            $this->assertSame(array_slice(array_column($k['item'], 'jumlah'), 0, AgregasiBelanja::MAKS_ITEM), array_column($baris['item_terbesar'], 'jumlah'), 'Item terbesar dulu.');
        }
        $this->assertGreaterThan(AgregasiBelanja::MAKS_ITEM, $respons->json('kelompok.kelompok.0.jumlah_item'), 'Contoh kelompok bahan lebih dari batas item yang disertakan.');
        $this->assertSame(array_sum(array_column($respons->json('kelompok.kode_akun'), 'total')), $respons->json('kelompok.total'));
    }

    public function test_agregasi_per_operator_dan_saringan(): void
    {
        $operator = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), ['tahun' => 2026])->perOperator(StubPengaturan::pengguna());
        $respons = $this->admin()->getJson(route('monitoring.agregasi'));
        $this->assertSame(array_column($operator, 'user_id'), array_column($respons->json('operator'), 'user_id'), 'Urut total RAB terbesar.');
        $this->assertSame(array_column($operator, 'total'), array_column($respons->json('operator'), 'total'));
        $this->assertSame([2, 1], array_column($respons->json('operator'), 'jumlah'), 'Contoh 1–2 dibuat akun leading, 3 oleh admin.');
        $this->assertSame(['draf' => 2], array_filter($respons->json('operator.0.per_status')));

        $this->admin()->getJson(route('monitoring.agregasi', ['sumber' => 'DIPA']))
            ->assertJsonPath('jumlah_kegiatan', 2)
            ->assertJsonPath('saringan', ['sumber' => 'DIPA']);
        $this->admin()->getJson(route('monitoring.agregasi', ['tahun' => '2025']))
            ->assertJsonPath('jumlah_kegiatan', 0)
            ->assertJsonPath('kelompok.total', 0)
            ->assertJsonPath('operator', []);
    }
}
