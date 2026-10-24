<?php

namespace Tests\Feature;

use App\Contracts\MasterKodeRepository;
use App\Livewire\Kegiatan\Klasifikasi;
use App\Support\MasterKode;
use App\Support\Stub\StubMasterKodeRepository;
use Livewire\Livewire;
use Tests\TestCase;

class MasterKodePerTahunTest extends TestCase
{
    public function test_repository_tiruan_terikat_dan_tahun_tersedia(): void
    {
        $this->assertInstanceOf(StubMasterKodeRepository::class, app(MasterKodeRepository::class));
        $this->assertSame([2025, 2026], app(MasterKodeRepository::class)->tahunTersedia());
    }

    public function test_isi_master_berbeda_per_tahun(): void
    {
        $this->assertCount(138, MasterKode::kodeRktm(2026));
        $this->assertCount(114, MasterKode::kodeRktm(2025));
        $this->assertNotNull(MasterKode::cari(MasterKode::kodeRktm(2026), '6.17.12'));
        $this->assertNull(MasterKode::cari(MasterKode::kodeRktm(2025), '6.17.12'));
        $this->assertNull(MasterKode::cari(MasterKode::kodeSk(2025), 'SK.19'));
        $this->assertNotSame(
            MasterKode::cari(MasterKode::kodeEdm(2025), 'C1')['uraian'],
            MasterKode::cari(MasterKode::kodeEdm(2026), 'C1')['uraian'],
        );
    }

    public function test_tahun_tanpa_unggahan_kosong(): void
    {
        $this->assertFalse(MasterKode::tersedia(2027));
        $this->assertSame([], MasterKode::kodeSk(2027));
        $this->assertSame([], MasterKode::budayaMutu(2027));
        $this->assertTrue(MasterKode::tersedia());
    }

    public function test_edm_berkomponen_dan_iksk_tersaring_per_sk(): void
    {
        $this->assertSame('C', MasterKode::cari(MasterKode::kodeEdm(), 'C1')['komponen']);
        $this->assertSame('Proses Pembelajaran', MasterKode::komponenEdm()['C']);
        $this->assertCount(5, MasterKode::budayaMutu());
        $this->assertNotEmpty(MasterKode::kodeIksk('SK.10'));
        $this->assertSame(['SK.10'], array_values(array_unique(array_column(MasterKode::kodeIksk('SK.10'), 'kode_sk'))));
    }

    public function test_leading_disaring_untuk_akun_leading(): void
    {
        $this->assertCount(8, MasterKode::leadingsUntuk(['role' => 'admin', 'leadings' => []]));
        $this->assertSame(['1.6'], array_column(MasterKode::leadingsUntuk(['role' => 'leading', 'leadings' => ['1.6']]), 'kode'));
    }

    public function test_validasi_kode_memakai_master_tahun_aktif(): void
    {
        // Kode yang hanya ada di 2025 (tidak ada) ditolak; kode milik 2026 diterima.
        Livewire::test(Klasifikasi::class, ['kegiatan' => 1])
            ->set('form.kode_rktm', '6.17.12')
            ->assertHasNoErrors('form.kode_rktm')
            ->set('form.kode_rktm', '9.99.99')
            ->assertHasErrors('form.kode_rktm');
    }
}
