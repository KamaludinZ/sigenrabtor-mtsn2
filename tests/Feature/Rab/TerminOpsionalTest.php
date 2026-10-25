<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\Form;
use App\Livewire\Kegiatan\Rab;
use App\Livewire\Kegiatan\TerminPembayaran;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\MekanismePembayaran;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Termin pembayaran opsional (PRD F11): RAB yang dibayar sekaligus tidak punya termin, dan itu sah di semua tempat.
 */
class TerminOpsionalTest extends TestCase
{
    private function terminKomite(): array
    {
        return app(RabRepository::class)->termin(1, 'Komite');
    }

    public function test_sekaligus_bisa_diurungkan_lalu_disimpan_tanpa_termin(): void
    {
        $komponen = Livewire::test(TerminPembayaran::class, ['kegiatanId' => 1, 'sumber' => 'Komite']);
        $semula = $komponen->get('baris');

        $komponen->call('jadikanSekaligus')
            ->assertSet('baris', [])
            ->assertSee('2 termin dilepas — RAB Komite dibayar sekaligus setelah disimpan.')
            ->call('urungkanSekaligus')
            ->assertSet('baris', $semula)
            ->assertSet('terminSebelumSekaligus', null)
            ->call('jadikanSekaligus')
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'RAB Komite dibayar sekaligus (tanpa termin).')
            ->assertSet('terminSebelumSekaligus', null)
            ->assertDontSeeHtml('data-testid="termin-dilepas"');

        $this->assertSame([], $this->terminKomite());
    }

    public function test_baris_kosong_diabaikan_saat_simpan(): void
    {
        Livewire::test(TerminPembayaran::class, ['kegiatanId' => 2, 'sumber' => 'DIPA'])
            ->call('tambahTermin')
            ->set('baris.0.nominal', '')
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: '1 baris termin kosong diabaikan.')
            ->assertSet('baris', []);
        $this->assertSame([], app(RabRepository::class)->termin(2, 'DIPA'));

        // Termin terisi tetap tersimpan; baris kosong di sela-selanya dibuang.
        Livewire::test(TerminPembayaran::class, ['kegiatanId' => 2, 'sumber' => 'DIPA'])
            ->call('tambahTermin')
            ->set('baris.0.tanggal', '2026-08-03')
            ->call('tambahTermin')
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertCount('baris', 1);
        $this->assertSame([['tanggal' => '2026-08-03', 'nominal' => 5_400_000]], app(RabRepository::class)->termin(2, 'DIPA'));
    }

    public function test_tanpa_termin_sah_di_dokumen_rab_dan_pengajuan(): void
    {
        app(RabRepository::class)->simpanTermin(1, 'Komite', []);
        $anggaran = AnggaranKegiatan::perSumber(app(KegiatanRepository::class)->temukan(1))['Komite'];

        $this->assertFalse($anggaran->adaTermin());
        $this->assertTrue($anggaran->terminSesuai());
        $this->assertSame([], MekanismePembayaran::dari($anggaran)->barisDokumen(), 'Blok Mekanisme Pembayaran tidak dicetak.');

        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->assertDontSee('termin belum pas')
            ->assertDontSeeHtml('data-testid="termin-tidak-pas"')
            ->set('halaman', 'termin')
            ->assertSee('Tidak ada blok termin — pembayaran sekaligus sebesar Rp10.000.000.');

        Livewire::test(Form::class, ['kegiatan' => 1])
            ->assertDontSeeHtml('data-testid="termin-form-komite"')
            ->call('ubahStatus', 'ajukan')
            ->assertHasNoErrors()
            ->assertSet('status', 'diperiksa');
    }
}
