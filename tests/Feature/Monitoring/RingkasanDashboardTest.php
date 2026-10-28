<?php

namespace Tests\Feature\Monitoring;

use App\Contracts\KegiatanRepository;
use App\Support\MasterKode;
use App\Support\Monitoring\RingkasanAnggaran;
use App\Support\Monitoring\RingkasanDashboard;
use Tests\TestCase;

class RingkasanDashboardTest extends TestCase
{
    public function test_ringkasan_akun_leading_hanya_leading_nya(): void
    {
        app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Lomba Kreativitas Siswa', 'leading' => '1.2', 'user_id' => 2, 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 12_000_000]]]);
        app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Kegiatan Tahun Lalu', 'leading' => '1.6', 'tahun_anggaran' => 2025]);

        $respons = $this->getJson(route('dashboard.data'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('tahun', 2026)
            ->assertJsonPath('cakupan', ['semua' => false, 'leading' => ['1.6'], 'nama' => '1.6 · Tim Penjamin Mutu'])
            ->assertJsonPath('jumlah_kegiatan', 3)
            ->assertJsonPath('per_status.0', ['status' => 'draf', 'label' => 'Draf', 'jumlah' => 3])
            ->assertJsonCount(1, 'per_leading')
            ->assertJsonPath('per_leading.0.kode', '1.6');

        $ringkasan = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), ['tahun' => 2026, 'leadings' => ['1.6']]);
        $this->assertSame($ringkasan->perSumber()['Komite']['total'], $respons->json('per_sumber.Komite.total'));
        $this->assertSame($ringkasan->perbandinganSumber()['DIPA']['kondisi'], $respons->json('per_sumber.DIPA.kondisi'));
        $this->assertSame($ringkasan->total()['total'], $respons->json('total.total'));
        $this->assertNull($respons->json('total.selisih'), 'Ada RAB tanpa pagu (DIPA contoh 3): selisih gabungan tidak bisa dihitung.');
        $this->assertSame($ringkasan->kombinasiSumber(), $respons->json('kombinasi_sumber'));
    }

    public function test_admin_melihat_semua_leading(): void
    {
        app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Lomba Kreativitas Siswa', 'leading' => '1.2', 'user_id' => 2, 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 12_000_000]]]);

        $respons = $this->withSession(['stub_role' => 'admin'])->getJson(route('dashboard.data'))
            ->assertJsonPath('cakupan.semua', true)
            ->assertJsonPath('cakupan.nama', 'Semua leading')
            ->assertJsonPath('jumlah_kegiatan', 4);

        $this->assertSame(array_column(MasterKode::leadings(), 'kode'), array_column($respons->json('per_leading'), 'kode'), 'Semua leading aktif, termasuk yang belum punya kegiatan.');
        $baris = collect($respons->json('per_leading'))->firstWhere('kode', '1.2');
        $this->assertSame([1, 12_000_000, 0, 1], [$baris['jumlah'], $baris['pagu'], $baris['total'], $baris['rab']]);
        $this->assertSame(['Semua leading', '1.6 · Tim Penjamin Mutu'], [RingkasanDashboard::namaCakupan(null), RingkasanDashboard::namaCakupan(['1.6'])]);
    }
}
