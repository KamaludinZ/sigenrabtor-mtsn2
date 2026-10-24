<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Klasifikasi;
use App\Livewire\Kegiatan\Rab;
use App\Support\Kegiatan\RingkasanKode;
use Livewire\Livewire;
use Tests\TestCase;

class StatusKelengkapanKodeTest extends TestCase
{
    private function kegiatanSebagian(): int
    {
        return app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Kegiatan Sebagian Kode',
            'nama_kegiatan_cover' => 'KEGIATAN SEBAGIAN KODE',
            'leading' => '1.6',
            'kode_rktm' => '6.17.12',
            'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]],
        ]);
    }

    public function test_status_lengkap_wajib_dan_sebagian(): void
    {
        $lengkap = RingkasanKode::status(RingkasanKode::susun([
            'leading' => '1.6', 'kode_rktm' => '6.17.12', 'kode_sk' => 'SK.10', 'kode_iksk' => 'IKSK 10.6', 'kode_edm' => 'C1', 'budaya_mutu' => 2,
        ]));
        $this->assertSame(['lengkap', 6, 6, []], [$lengkap['status'], $lengkap['terisi'], $lengkap['total'], $lengkap['kosong']]);

        $wajib = RingkasanKode::status(RingkasanKode::susun(['leading' => '1.6']));
        $this->assertSame('wajib', $wajib['status']);
        $this->assertSame('Kode SK belum dipilih — wajib sebelum kegiatan diajukan.', $wajib['pesan']);
        $this->assertSame(['kode-rktm', 'kode-perkin', 'kode-perkin', 'kode-edm', 'kode-budaya'], array_column($wajib['kosong'], 'kartu'));

        $sebagian = RingkasanKode::status(RingkasanKode::susun(['leading' => '1.6', 'kode_sk' => 'SK.12', 'kode_iksk' => 'IKSK 12.1']));
        $this->assertSame('sebagian', $sebagian['status']);
        $this->assertStringStartsWith('3 dari 6 kode terpilih.', $sebagian['pesan']);
    }

    public function test_notifikasi_simpan_menyebut_kode_yang_masih_kosong(): void
    {
        $status = RingkasanKode::status(RingkasanKode::susun(['leading' => '1.6', 'kode_sk' => 'SK.12', 'kode_iksk' => 'IKSK 12.1']));

        $this->assertSame([
            'message' => 'Kode & klasifikasi tersimpan. 3 dari 6 kode terpilih — belum: Kode RKTM, Kode EDM, 5 Budaya Mutu.',
            'type' => 'warning',
        ], RingkasanKode::notifikasiSimpan($status));
    }

    public function test_banner_mengikuti_pilihan_dan_menautkan_kartu_kosong(): void
    {
        Livewire::test(Klasifikasi::class, ['kegiatan' => $this->kegiatanSebagian()])
            ->assertSeeHtml('data-status="wajib"')
            ->assertSee('Kode SK belum dipilih — wajib sebelum kegiatan diajukan.')
            ->assertSeeHtml('href="#kode-perkin" data-kode-kosong="sk"')
            ->set('form.kode_sk', 'SK.12')
            ->assertSeeHtml('data-status="sebagian"')
            ->assertSee('4 dari 6 kode terpilih.')
            ->assertDontSeeHtml('data-kode-kosong="sk"')
            ->set('form.kode_edm', 'C1')
            ->set('form.budaya_mutu', '2')
            ->assertSeeHtml('data-status="lengkap"')
            ->assertSee('Semua kode lengkap — header RAB siap dicetak.');
    }

    public function test_simpan_sebagian_memberi_notifikasi_peringatan(): void
    {
        Livewire::test(Klasifikasi::class, ['kegiatan' => $this->kegiatanSebagian()])
            ->set('form.kode_sk', 'SK.12')
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Kode & klasifikasi tersimpan. 4 dari 6 kode terpilih — belum: Kode EDM, 5 Budaya Mutu.', type: 'warning');
    }

    public function test_lencana_tab_kode_dari_data_tersimpan_di_halaman_lain(): void
    {
        $id = $this->kegiatanSebagian();

        Livewire::test(Rab::class, ['kegiatan' => $id])
            ->assertSeeHtml('data-testid="lencana-kode" data-status="wajib">2/6</span>');

        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->assertSeeHtml('title="Semua kode lengkap" data-testid="lencana-kode" data-status="lengkap"');
    }
}
