<?php

namespace Tests\Feature\Rab;

use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\Rab;
use App\Livewire\Kegiatan\TerminPembayaran;
use Livewire\Livewire;
use Tests\TestCase;

class TerminCrudTest extends TestCase
{
    private function termin()
    {
        return Livewire::test(TerminPembayaran::class, ['kegiatanId' => 1, 'sumber' => 'Komite']);
    }

    public function test_hapus_bisa_diurungkan_sampai_ada_perubahan_lain(): void
    {
        $komponen = $this->termin();
        [$pertama, $kedua] = $komponen->get('baris');

        $komponen->call('hapusTermin', $pertama['key'])
            ->assertCount('baris', 1)
            ->assertSee('Termin 1 (Rp7.680.000) dihapus.')
            ->call('urungkanHapus')
            ->assertSet('baris', [$pertama, $kedua])
            ->assertSet('terminTerhapus', null)
            ->call('hapusTermin', $kedua['key'])
            ->call('tambahTermin')
            ->assertSet('terminTerhapus', null)
            ->call('urungkanHapus')
            ->assertCount('baris', 2);

        $komponen->call('hapusTermin', 'tidak-ada')->assertCount('baris', 2);
        $this->assertCount(2, app(RabRepository::class)->termin(1, 'Komite'), 'Belum disimpan: data tersimpan utuh.');
    }

    public function test_urutkan_menurut_tanggal_membawa_nominalnya(): void
    {
        $this->termin()
            ->assertDontSeeHtml('data-testid="urutkan-tanggal"')
            ->set('baris.0.tanggal', '2026-06-25')
            ->assertSeeHtml('data-testid="urutkan-tanggal"')
            ->call('urutkanTanggal')
            ->assertSet('baris.0.tanggal', '2026-06-19')
            ->assertSet('baris.0.nominal', '2.320.000')
            ->assertSet('baris.1.nominal', '7.680.000')
            ->call('tambahTermin')
            ->set('baris.0.tanggal', '')
            ->call('urutkanTanggal')
            ->assertSet('baris.2.tanggal', '')
            ->assertSet('baris.0.tanggal', '2026-06-25');
    }

    public function test_perubahan_dikabarkan_ke_halaman_rab(): void
    {
        $this->termin()
            ->assertSeeHtml('x-on:beforeunload.window')
            ->set('baris.1.nominal', '2.000.000')
            ->assertDispatched('termin-berubah', sumber: 'Komite')
            ->call('batalkan')
            ->assertDispatched('termin-berubah', sumber: null)
            ->assertSet('adaPerubahan', false);

        $this->termin()->call('bagiRata', 2)->call('simpan')->assertDispatched('termin-berubah', sumber: null);
    }

    public function test_pindah_sumber_atau_halaman_meminta_konfirmasi_saat_termin_belum_disimpan(): void
    {
        $rab = Livewire::test(Rab::class, ['kegiatan' => 2])
            ->set('halaman', 'termin')
            ->dispatch('termin-berubah', sumber: 'DIPA')
            ->assertSet('terminBelumDisimpan', 'DIPA')
            ->call('pilihSumber', 'Komite')
            ->assertSet('sumber', 'DIPA')
            ->assertSet('tujuanTertunda', ['sumber' => 'Komite', 'halaman' => 'termin'])
            ->assertSee('Perubahan termin RAB DIPA belum disimpan. Tinggalkan tanpa menyimpan?')
            ->call('tetapDiTermin')
            ->assertSet('tujuanTertunda', null)
            ->set('halaman', 'rincian')
            ->assertSet('halaman', 'termin')
            ->assertSet('tujuanTertunda', ['sumber' => 'DIPA', 'halaman' => 'rincian'])
            ->call('tinggalkanTermin')
            ->assertSet('halaman', 'rincian')
            ->assertSet('terminBelumDisimpan', null);

        // Tinggalkan ke sumber lain: halaman termin sumber tujuan langsung terbuka.
        $rab->set('halaman', 'termin')
            ->dispatch('termin-berubah', sumber: 'DIPA')
            ->call('pilihSumber', 'Komite')
            ->call('tinggalkanTermin')
            ->assertSet('sumber', 'Komite')
            ->assertSet('halaman', 'termin');

        // Termin disimpan/dibatalkan: tidak ada lagi yang ditahan.
        $rab->dispatch('termin-berubah', sumber: 'Komite')
            ->call('pilihSumber', 'DIPA')
            ->dispatch('termin-berubah', sumber: null)
            ->assertSet('tujuanTertunda', null)
            ->call('pilihSumber', 'DIPA')
            ->assertSet('sumber', 'DIPA');
    }
}
