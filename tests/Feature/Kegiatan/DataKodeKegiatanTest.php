<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Forms\KegiatanForm;
use App\Support\Kegiatan\KodeHilang;
use App\Support\MasterKode;
use Tests\TestCase;

class DataKodeKegiatanTest extends TestCase
{
    /** @param  array<string, mixed>  $isi */
    private function kegiatan(array $isi): int
    {
        return app(KegiatanRepository::class)->simpan($isi + ['nama_kegiatan' => 'Supervisi Akademik', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026]);
    }

    public function test_kode_kegiatan_beserta_uraian_master_dan_pilihannya(): void
    {
        $data = $this->getJson(route('kegiatan.kode_master', ['kegiatan' => 2]))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson([
                'kegiatan_id' => 2,
                'tahun' => 2026,
                'master_tersedia' => true,
                'leading' => ['kode' => '1.6', 'nama_unit' => 'Tim Penjamin Mutu', 'aktif' => true],
                'terisi' => 5,
                'galat_pengajuan' => [],
                'dapat_diubah' => true,
            ])
            ->json();

        $this->assertSame(['kode_rktm', 'kode_sk', 'kode_iksk', 'kode_edm', 'budaya_mutu'], array_keys($data['kode']));
        $sk = $data['kode']['kode_sk'];
        $this->assertSame(['terisi', 'sk', $sk['kode']], [$sk['status'], $sk['jenis'], $sk['master']['kode']]);
        $this->assertSame(MasterKode::cari(MasterKode::kodeSk(2026), $sk['kode'])['uraian'], $sk['master']['uraian']);
        $this->assertNotNull($data['kode']['kode_edm']['master']['nama_komponen']);
        $this->assertSame(route('master.kode', ['jenis' => 'iksk', 'ta' => 2026, 'sk' => $sk['kode']]), $data['kode']['kode_iksk']['pilihan']);
        $this->assertContains($data['kode']['kode_iksk']['kode'], array_column($data['pilihan_iksk'], 'kode'));
        $this->assertSame([$sk['kode']], array_values(array_unique(array_column($data['pilihan_iksk'], 'kode_sk'))));
    }

    public function test_kode_asing_tidak_sesuai_kosong_dan_tahun_kegiatan(): void
    {
        $id = $this->kegiatan(['kode_sk' => 'SK.10', 'kode_iksk' => 'IKSK 11.5', 'kode_rktm' => '9.9.9', 'kode_hilang' => ['kode_edm' => KodeHilang::catatan('Z9', 'duplikat', 2025)]]);
        $data = $this->getJson(route('kegiatan.kode_master', ['kegiatan' => $id]))->assertOk()->json();
        $this->assertSame(['asing', '9.9.9 tidak ada di master TA 2026.', null], [$data['kode']['kode_rktm']['status'], $data['kode']['kode_rktm']['pesan'], $data['kode']['kode_rktm']['master']]);
        $this->assertSame(['tidak_sesuai', 'IKSK 11.5 bukan indikator dari SK.10.'], [$data['kode']['kode_iksk']['status'], $data['kode']['kode_iksk']['pesan']]);
        $this->assertSame('kosong', $data['kode']['kode_edm']['status']);
        $this->assertArrayHasKey('kode_iksk', $data['galat_pengajuan']);
        $this->assertSame(['kode_rktm', 'kode_edm'], array_column($data['hilang'], 'kolom'), 'Kode asing di master + catatan duplikat.');

        $kosong = $this->getJson(route('kegiatan.kode_master', ['kegiatan' => $this->kegiatan([])]))->json();
        $this->assertSame(['kosong', KegiatanForm::KODE_WAJIB['kode_sk']], [$kosong['kode']['kode_sk']['status'], $kosong['kode']['kode_sk']['pesan']]);
        $this->assertSame([[], 0], [$kosong['pilihan_iksk'], $kosong['terisi']]);

        // Kegiatan tahun lalu dicocokkan dengan master tahunnya sendiri.
        $sk2025 = MasterKode::kodeSk(2025)[0];
        $lalu = $this->getJson(route('kegiatan.kode_master', ['kegiatan' => $this->kegiatan(['tahun_anggaran' => 2025, 'kode_sk' => $sk2025['kode']])]))->json();
        $this->assertSame([2025, 'terisi', $sk2025['uraian'], false], [$lalu['tahun'], $lalu['kode']['kode_sk']['status'], $lalu['kode']['kode_sk']['master']['uraian'], $lalu['dapat_diubah']]);
        $this->assertStringContainsString('ta=2025', $lalu['kode']['kode_sk']['pilihan']);
    }

    public function test_leading_nonaktif_akses_dan_tidak_ada(): void
    {
        session(['stub_role' => 'admin']);
        $nonaktif = $this->kegiatan(['leading' => '1.9']);
        $this->getJson(route('kegiatan.kode_master', ['kegiatan' => $nonaktif]))->assertOk()
            ->assertJsonPath('leading.kode', '1.9')
            ->assertJsonPath('leading.aktif', false);

        session(['stub_role' => 'leading']);
        $this->getJson(route('kegiatan.kode_master', ['kegiatan' => $this->kegiatan(['leading' => '1.2', 'user_id' => 4])]))->assertForbidden();
        $this->getJson(route('kegiatan.kode_master', ['kegiatan' => 999]))->assertNotFound();
    }
}
