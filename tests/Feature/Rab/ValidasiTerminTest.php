<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\Form;
use App\Livewire\Kegiatan\Rab;
use App\Livewire\Kegiatan\TerminPembayaran;
use App\Support\Rab\MekanismePembayaran;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * PRD aturan 6 "Termin harus pas": jumlah semua termin = total RAB sumber dana itu.
 */
class ValidasiTerminTest extends TestCase
{
    private function termin(int $total, array $nominal): MekanismePembayaran
    {
        return new MekanismePembayaran($total, array_map(fn ($n) => ['tanggal' => '2026-06-01', 'nominal' => $n], $nominal));
    }

    public function test_pesan_tidak_pas_untuk_tiap_kondisi(): void
    {
        $this->assertNull(TerminPembayaran::pesanTidakPas($this->termin(1_000, []), 'DIPA'), 'Sekaligus selalu sah.');
        $this->assertNull(TerminPembayaran::pesanTidakPas($this->termin(1_000, [400, 600]), 'DIPA'));
        $this->assertSame('Jumlah termin harus sama dengan total RAB DIPA (Rp1.000); termin kurang Rp100 dari total RAB.',
            TerminPembayaran::pesanTidakPas($this->termin(1_000, [400, 500]), 'DIPA'));
        $this->assertSame('Jumlah termin harus sama dengan total RAB Komite (Rp1.000); termin lebih Rp50 dari total RAB.',
            TerminPembayaran::pesanTidakPas($this->termin(1_000, [1_050]), 'Komite'));
        $this->assertStringStartsWith('RAB DIPA belum berisi rincian', TerminPembayaran::pesanTidakPas($this->termin(0, [500]), 'DIPA'));
    }

    public function test_termin_untuk_rab_kosong_ditolak(): void
    {
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'RAB Kosong', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]]]);

        Livewire::test(TerminPembayaran::class, ['kegiatanId' => $id, 'sumber' => 'DIPA'])
            ->call('tambahTermin')
            ->set('baris.0.tanggal', '2026-05-04')
            ->set('baris.0.nominal', '500.000')
            ->call('simpan')
            ->assertHasErrors('termin')
            ->assertSeeHtml('data-testid="galat-termin"')
            ->assertSee('RAB DIPA belum berisi rincian, jadi termin belum bisa disusun.');

        $this->assertSame([], app(RabRepository::class)->termin($id, 'DIPA'));
    }

    public function test_pengajuan_diblokir_sampai_termin_pas(): void
    {
        // Rincian berubah setelah termin disimpan: termin kegiatan 1 tidak lagi sama dengan total RAB Komite.
        app(RabRepository::class)->simpanTermin(1, 'Komite', [['tanggal' => '2026-06-17', 'nominal' => 6_000_000]]);

        Livewire::test(Form::class, ['kegiatan' => 1])
            ->assertSeeHtml('data-testid="termin-form-komite"')
            ->assertSee('1 termin · Termin kurang Rp4.000.000 dari total RAB')
            ->call('ubahStatus', 'ajukan')
            ->assertHasErrors('form.termin_komite')
            ->assertSet('status', 'draf')
            ->assertSee('Sesuaikan di halaman RAB › Termin pembayaran.')
            ->assertSeeHtml('halaman=termin');

        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->assertSeeHtml('data-testid="termin-tidak-pas"')
            ->assertSee('Termin RAB Komite tidak lagi sama dengan total RAB — termin kurang Rp4.000.000 dari total RAB.')
            ->set('halaman', 'termin')
            ->assertSet('halaman', 'termin');

        app(RabRepository::class)->simpanTermin(1, 'Komite', [['tanggal' => '2026-06-17', 'nominal' => 10_000_000]]);
        Livewire::test(Form::class, ['kegiatan' => 1])
            ->call('ubahStatus', 'ajukan')
            ->assertHasNoErrors()
            ->assertSet('status', 'diperiksa');
    }
}
