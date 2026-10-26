<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\TahunAnggaranRepository;
use App\Livewire\Pengaturan\TahunAnggaran;
use App\Support\Pengaturan\BagianPengaturan;
use App\Support\Stub\StubData;
use App\Support\Stub\StubTahunAnggaranRepository;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class TahunAnggaranTest extends TestCase
{
    public function test_repository_tepat_satu_tahun_aktif_dan_jejak_aktivasi(): void
    {
        $repo = app(TahunAnggaranRepository::class);
        $this->assertInstanceOf(StubTahunAnggaranRepository::class, $repo);
        $this->assertSame([2026, 2025], array_column($repo->semua(), 'tahun'));
        $this->assertSame(['tahun' => 2026, 'is_locked' => false], array_intersect_key($repo->aktif(), ['tahun' => 1, 'is_locked' => 1]));
        $this->assertSame(['id' => 1, 'tahun' => 2026, 'is_locked' => false], StubData::tahunAktif());

        $repo->aktifkan(2025, 'Koordinator PUSKOM');
        $this->assertSame([false, true], array_column($repo->semua(), 'is_active'));
        $this->assertSame(['id' => 2, 'tahun' => 2025, 'is_locked' => true], StubData::tahunAktif(), 'Tahun aktif berlaku di seluruh aplikasi.');
        $this->assertSame('Koordinator PUSKOM', $repo->aktif()['diaktifkan_oleh']);
        $this->assertSame(now()->toDateString(), substr($repo->aktif()['diaktifkan_pada'], 0, 10));

        $this->expectException(InvalidArgumentException::class);
        $repo->aktifkan(2030, 'Koordinator PUSKOM');
    }

    public function test_halaman_khusus_admin_dan_aktif_di_menu(): void
    {
        $this->get(route('pengaturan.tahun.index'))->assertForbidden();
        // Leading: chip tahun di menu atas bukan tautan.
        $this->get(route('kegiatan.index'))->assertSeeHtml('data-testid="chip-tahun-aktif"')->assertDontSee('Kelola tahun anggaran');

        $this->withSession(['stub_role' => 'admin'])
            ->get(route('pengaturan.tahun.index'))
            ->assertOk()
            ->assertSeeLivewire(TahunAnggaran::class)
            ->assertSeeHtml('href="'.route('pengaturan.tahun.index').'" wire:navigate title="Kelola tahun anggaran"')
            ->assertSeeInOrder(['Pengaturan', 'Tahun Anggaran', 'Berlaku TA 2026']);

        $this->assertTrue(BagianPengaturan::cari('tahun')['tersedia']);
    }

    public function test_status_tahun_aktif_dan_daftar(): void
    {
        Livewire::test(TahunAnggaran::class)
            ->assertSeeHtml('data-testid="tahun-aktif"')
            ->assertSeeInOrder(['Tahun aktif', '2026', 'Diaktifkan oleh Koordinator PUSKOM, 2 Januari 2026.', 'SK RKTM/RKAM', 'Belum diisi', 'Master kode', 'Tersedia', 'Kegiatan', '3 kegiatan tercatat'])
            ->assertSeeHtml('data-testid="tahun-2026" data-status="aktif"')
            ->assertSeeHtml('data-testid="tahun-2025" data-status="terkunci"')
            ->assertSee('Contoh 001/SK/RKTM/2025')
            ->assertSee('Sedang aktif')
            ->assertSeeHtml('aria-label="Jadikan 2025 tahun aktif"')
            ->assertDontSeeHtml('aria-label="Jadikan 2026 tahun aktif"');
    }

    public function test_pilih_konfirmasi_lalu_aktifkan(): void
    {
        Livewire::test(TahunAnggaran::class)
            ->assertDontSeeHtml('data-testid="konfirmasi-tahun"')
            ->call('pilih', 2026)
            ->assertSet('akanDiaktifkan', null)
            ->call('pilih', 1999)
            ->assertSet('akanDiaktifkan', null)
            ->call('pilih', 2025)
            ->assertSet('akanDiaktifkan', 2025)
            ->assertSeeHtml('data-testid="konfirmasi-tahun"')
            ->assertSee('Aktifkan Tahun Anggaran 2025?')
            ->assertSee('Kegiatan & dokumen tahun 2026 tetap tersimpan, tidak dihapus')
            ->assertSeeHtml('data-testid="peringatan-terkunci"')
            ->assertDontSeeHtml('data-testid="peringatan-master"')
            ->call('batalPilih')
            ->assertDontSeeHtml('data-testid="konfirmasi-tahun"')
            ->call('pilih', 2025)
            ->call('aktifkan')
            ->assertRedirect(route('pengaturan.tahun.index'));

        $this->assertSame(2025, StubData::tahunAktif()['tahun']);
        $this->assertSame(['message' => 'Tahun Anggaran 2025 aktif. Seluruh dokumen kini memakai tahun 2025.', 'type' => 'success'], session('notify'));

        $this->withSession(['stub_role' => 'admin'])->get(route('pengaturan.tahun.index'))
            ->assertSeeHtml('data-testid="tahun-2025" data-status="aktif"')
            ->assertSee('Aktif · terkunci')
            ->assertSee('Berlaku TA 2025');

        // Tanpa pilihan, aktifkan tidak melakukan apa pun.
        Livewire::test(TahunAnggaran::class)->call('aktifkan')->assertNoRedirect();
        $this->assertSame(2025, StubData::tahunAktif()['tahun']);
    }
}
