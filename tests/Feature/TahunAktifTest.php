<?php

namespace Tests\Feature;

use App\Contracts\RiwayatPengaturanRepository;
use App\Livewire\TahunAktif;
use App\Support\Pengaturan\KontrolTahunAktif;
use App\Support\Stub\StubData;
use Livewire\Livewire;
use Tests\TestCase;

class TahunAktifTest extends TestCase
{
    public function test_semua_yang_mengikuti_tahun_aktif_beserta_kesiapannya(): void
    {
        $dipakai = collect(KontrolTahunAktif::dipakai())->keyBy('kunci');

        $this->assertSame(array_keys(KontrolTahunAktif::DIPAKAI), $dipakai->keys()->all());
        $this->assertTrue($dipakai['master']['siap']);
        $this->assertFalse($dipakai['pejabat']['siap'], 'Contoh TA 2026: pejabat sengaja kosong.');
        $this->assertStringContainsString('Kepala Madrasah', $dipakai['pejabat']['catatan']);
        $this->assertFalse($dipakai['dasar_hukum']['siap'], 'SK RKTM TA 2026 belum diisi.');
        $this->assertNull($dipakai['jadwal']['siap'], 'Jadwal selalu mengikuti tahun aktif tanpa isian.');
        $this->assertStringContainsString('TA 2026', $dipakai['master']['keterangan']);

        $this->assertSame([[2026, true, false, 3], [2025, false, true, 0]], array_map(fn ($t) => [$t['tahun'], $t['is_active'], $t['is_locked'], $t['jumlah_kegiatan']], KontrolTahunAktif::pilihan()));
    }

    public function test_leading_hanya_melihat(): void
    {
        Livewire::test(TahunAktif::class)
            ->assertSeeHtml('data-testid="chip-tahun-aktif"')
            ->assertSee('Tahun Anggaran 2026')
            ->assertSeeHtml('data-dipakai="pejabat" data-siap="tidak"')
            ->assertSee('Tahun anggaran aktif diatur admin.')
            ->assertDontSeeHtml('data-testid="ganti-tahun"')
            ->call('pilih', 2025)
            ->assertSet('akanDiaktifkan', null)
            ->call('aktifkan')
            ->assertNoRedirect();

        $this->assertSame(2026, StubData::tahunAktif()['tahun']);
    }

    public function test_admin_mengganti_tahun_lewat_konfirmasi(): void
    {
        session(['stub_role' => 'admin']);

        Livewire::test(TahunAktif::class)
            ->assertSeeHtml('data-testid="ganti-tahun"')
            ->assertSee('Terkunci · 0 kegiatan · master kode tersedia')
            ->assertSee('Kelola tahun anggaran')
            ->call('pilih', 2026)
            ->assertSet('akanDiaktifkan', null)
            ->call('pilih', 2025)
            ->assertSee('Aktifkan Tahun Anggaran 2025?')
            ->assertSee('TA 2025 terkunci — datanya hanya bisa dilihat.')
            ->call('batal')
            ->assertSet('akanDiaktifkan', null)
            ->call('pilih', 2025)
            ->call('aktifkan')
            ->assertRedirect();

        $this->assertSame(2025, StubData::tahunAktif()['tahun']);
        $this->assertSame('Tahun Anggaran 2025 aktif. Seluruh dokumen kini memakai tahun 2025.', session('notify')['message']);
        $this->assertSame('Tahun Anggaran 2025 diaktifkan (sebelumnya 2026).', app(RiwayatPengaturanRepository::class)->semua()[0]['keterangan']);

        $this->assertFalse(KontrolTahunAktif::aktifkan(2025, StubData::currentUser()), 'Tahun yang sudah aktif tidak diaktifkan ulang.');
        $this->assertFalse(KontrolTahunAktif::aktifkan(2030, StubData::currentUser()));
    }

    public function test_kontrol_tampil_di_bilah_atas(): void
    {
        $this->get(route('dashboard'))->assertSeeLivewire(TahunAktif::class);
    }
}
