<?php

namespace Tests\Feature\Rab;

use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\Rab;
use App\Support\Rab\RincianRab;
use Livewire\Livewire;
use Tests\TestCase;

/** Contoh kegiatan 1: RAB Komite Rp10.000.000 dengan dua termin yang pas (Rp7.680.000 + Rp2.320.000). */
class DampakTerminTest extends TestCase
{
    private const SPANDUK = ['uraian' => 'Spanduk kegiatan', 'volume' => '1', 'satuan' => 'buah', 'harga_satuan' => '150.000'];

    public function test_perubahan_rincian_melaporkan_dampak_pada_termin(): void
    {
        $tambah = RincianRab::tambah(1, 'Komite', self::SPANDUK);
        $this->assertSame(['status' => 'kurang', 'pesan' => 'Total RAB Komite kini Rp10.150.000; termin kurang Rp150.000 dari total RAB — sesuaikan di Termin pembayaran.', 'tipe' => 'warning'], $tambah['termin']);
        $this->assertCount(2, app(RabRepository::class)->termin(1, 'Komite'), 'Termin tidak diubah otomatis.');

        // Uraian berubah tanpa mengubah total → tanpa dampak; nilai kembali → termin pas lagi.
        $this->assertNull(RincianRab::ubah(1, 'Komite', $tambah['id'], ['uraian' => 'Spanduk pembukaan'] + self::SPANDUK)['termin']);
        $hapus = RincianRab::hapus(1, 'Komite', $tambah['id']);
        $this->assertSame(['status' => 'pas', 'pesan' => 'Termin RAB Komite kini sama dengan total RAB.', 'tipe' => 'success'], $hapus['termin']);
        $this->assertSame('kurang', RincianRab::kembalikan(1, 'Komite', $hapus['index'], $hapus['item'])['status']);
    }

    public function test_tanpa_termin_tidak_ada_dampak(): void
    {
        // Kegiatan 2 dibayar sekaligus.
        $this->assertNull(RincianRab::tambah(2, 'DIPA', self::SPANDUK)['termin']);
    }

    public function test_halaman_rab_memberi_tahu_saat_termin_tidak_lagi_pas(): void
    {
        $id = fn () => app(RabRepository::class)->rincian(1, 'Komite')[0]['id'];

        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->call('salinItem', $id())
            ->assertDispatched('notify', type: 'warning')
            ->call('hapusItem', app(RabRepository::class)->rincian(1, 'Komite')[1]['id'])
            ->assertDispatched('notify', message: 'Termin RAB Komite kini sama dengan total RAB.', type: 'success');
    }
}
