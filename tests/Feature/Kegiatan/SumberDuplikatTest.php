<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\Duplikat\DuplikatKegiatan;
use Tests\TestCase;

class SumberDuplikatTest extends TestCase
{
    /** @return array{0: int, 1: int, 2: int} [baru, sudah ada di tahun aktif, leading lain] */
    private function kegiatanTahunLalu(): array
    {
        $repo = app(KegiatanRepository::class);
        $simpan = fn (array $isi) => $repo->simpan($isi + ['leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2025, 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]]]);

        return [
            $simpan(['nama_kegiatan' => 'Lomba Literasi Madrasah', 'kode_rktm' => '1.1.2']),
            $simpan(['nama_kegiatan' => $repo->temukan(2)['nama_kegiatan']]),
            $simpan(['nama_kegiatan' => 'Pentas Seni Kesiswaan', 'leading' => '1.2', 'user_id' => 4]),
        ];
    }

    public function test_daftar_kegiatan_tahun_lalu_dalam_cakupan_akun(): void
    {
        [$baru, $sudahAda, $lain] = $this->kegiatanTahunLalu();

        $data = $this->getJson(route('kegiatan.duplikat.sumber'))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['tahun_aktif' => 2026, 'tahun_sumber' => [2025], 'dari' => 2025, 'jumlah' => 2, 'jumlah_bisa' => 1, 'leading' => [['kode' => '1.6', 'nama_unit' => 'Tim Penjamin Mutu']]])
            ->json();
        $this->assertSame(DuplikatKegiatan::PERUBAHAN, $data['perubahan']);
        $perId = array_column($data['kegiatan'], null, 'id');
        $this->assertSame([$baru, $sudahAda], array_keys($perId), 'Urut nama; leading lain tidak terlihat.');
        $this->assertSame([true, null], [$perId[$baru]['bisa_diduplikat'], $perId[$baru]['tautan_sudah_ada']]);
        $this->assertSame([false, 2, route('kegiatan.edit', ['kegiatan' => 2])], [$perId[$sudahAda]['bisa_diduplikat'], $perId[$sudahAda]['sudah_ada'], $perId[$sudahAda]['tautan_sudah_ada']]);
        $this->assertSame([['sumber' => 'DIPA', 'total' => 0, 'item' => 0]], $perId[$baru]['sumber']);

        $this->getJson(route('kegiatan.duplikat.sumber', ['cari' => '1.1.2']))->assertOk()->assertJson(['jumlah' => 1])->assertJsonPath('kegiatan.0.id', $baru);
        $this->getJson(route('kegiatan.duplikat.sumber', ['bisa' => 'ya']))->assertOk()->assertJson(['jumlah' => 1, 'saringan' => ['bisa' => 'ya']]);
        $this->getJson(route('kegiatan.duplikat.sumber', ['dari' => 2030]))->assertOk()->assertJson(['dari' => 2025, 'jumlah' => 2]);

        $this->withSession(['stub_role' => 'admin']);
        $admin = $this->getJson(route('kegiatan.duplikat.sumber'))->assertOk()->json();
        $this->assertSame(3, $admin['jumlah']);
        $this->assertSame(['1.2', '1.6'], array_column($admin['leading'], 'kode'));
        $this->getJson(route('kegiatan.duplikat.sumber', ['leading' => '1.2']))->assertOk()->assertJson(['jumlah' => 1])->assertJsonPath('kegiatan.0.id', $lain);
    }
}
