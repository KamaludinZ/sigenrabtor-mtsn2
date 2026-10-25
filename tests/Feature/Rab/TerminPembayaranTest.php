<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\Rab;
use App\Livewire\Kegiatan\TerminPembayaran;
use App\Support\Rab\MekanismePembayaran;
use Livewire\Livewire;
use Tests\TestCase;

class TerminPembayaranTest extends TestCase
{
    private function termin(int $kegiatan = 1, string $sumber = 'Komite'): array
    {
        return app(RabRepository::class)->termin($kegiatan, $sumber);
    }

    public function test_blok_mekanisme_pembayaran_seperti_form_rab(): void
    {
        $termin = new MekanismePembayaran(10_000_000, [['tanggal' => '2026-06-17', 'nominal' => 7_680_000], ['tanggal' => '2026-06-19', 'nominal' => 2_320_000]]);

        $this->assertSame([
            ['label' => 'Mekanisme Pembayaran', 'nilai' => 'Termin'],
            ['label' => 'Total Keseluruhan', 'nilai' => 'Rp10.000.000,00'],
            ['label' => 'Termin 1 (17/06/2026)', 'nilai' => 'Rp7.680.000,00'],
            ['label' => 'Termin 2 (19/06/2026)', 'nilai' => 'Rp2.320.000,00'],
        ], $termin->barisDokumen());
        $this->assertSame(['pas', 0, 76.8], [$termin->status(), $termin->selisih(), $termin->persen(7_680_000)]);

        $this->assertSame(['sekaligus', []], [(new MekanismePembayaran(500))->status(), (new MekanismePembayaran(500))->barisDokumen()]);
        $kurang = new MekanismePembayaran(1_000, [['tanggal' => null, 'nominal' => 600]]);
        $this->assertSame(['kurang', 'Termin kurang Rp400 dari total RAB', 'Termin 1'], [$kurang->status(), $kurang->teksStatus(), $kurang->barisDokumen()[2]['label']]);
        $lebih = new MekanismePembayaran(1_000, [['tanggal' => null, 'nominal' => 1_500]]);
        $this->assertSame(['lebih', 'Termin lebih Rp500 dari total RAB'], [$lebih->status(), $lebih->teksStatus()]);
        $this->assertSame(0.0, (new MekanismePembayaran(0, [['tanggal' => null, 'nominal' => 5]]))->persen(5));
    }

    public function test_halaman_termin_di_rab_dan_tetap_saat_ganti_sumber(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 2])
            ->assertSee('Termin pembayaran')
            ->set('halaman', 'termin')
            ->assertSet('halaman', 'termin')
            ->assertSeeLivewire(TerminPembayaran::class)
            ->call('pilihSumber', 'Komite')
            ->assertSet('sumber', 'Komite')
            ->assertSet('halaman', 'termin')
            ->set('halaman', 'semua')
            ->call('pilihSumber', 'DIPA')
            ->assertSet('halaman', 'rincian');
    }

    public function test_termin_contoh_dimuat_dan_pratinjau_dokumen(): void
    {
        Livewire::test(TerminPembayaran::class, ['kegiatanId' => 1, 'sumber' => 'Komite'])
            ->assertSet('baris.0.nominal', '7.680.000')
            ->assertSet('baris.1.tanggal', '2026-06-19')
            ->assertSeeHtml('data-status="pas"')
            ->assertSee('Jumlah termin sama dengan total RAB')
            ->assertSeeInOrder(['Mekanisme Pembayaran', 'Termin', 'Total Keseluruhan', 'Rp10.000.000,00', 'Termin 1 (17/06/2026)', 'Rp7.680.000,00', 'Termin 2 (19/06/2026)', 'Rp2.320.000,00'])
            ->assertSee('76,8%')
            ->assertSee('Tujuh Juta Enam Ratus Delapan Puluh Ribu Rupiah');
    }

    public function test_sunting_hitung_selisih_lalu_simpan(): void
    {
        $komponen = Livewire::test(TerminPembayaran::class, ['kegiatanId' => 1, 'sumber' => 'Komite'])
            ->set('baris.1.nominal', '2000000')
            ->assertSet('baris.1.nominal', '2.000.000')
            ->assertSet('adaPerubahan', true)
            ->assertSeeHtml('data-status="kurang"')
            ->assertSee('Termin kurang Rp320.000 dari total RAB')
            ->call('isiSisa')
            ->assertSet('baris.1.nominal', '2.320.000')
            ->assertSeeHtml('data-status="pas"')
            ->call('tambahTermin')
            ->assertSet('baris.2.nominal', '')
            // Terisi sebagian (nominal tanpa tanggal) tetap divalidasi; yang kosong sama sekali diabaikan (lihat ValidasiTerminTest).
            ->set('baris.2.nominal', '500.000')
            ->call('simpan')
            ->assertHasErrors(['baris.2.tanggal' => 'required']);

        // Jumlah termin tidak sama dengan total RAB: ditolak (PRD aturan 6), data tersimpan tidak berubah.
        $key = $komponen->get('baris')[2]['key'];
        $komponen->call('hapusTermin', $key)
            ->set('baris.1.nominal', '1.000.000')
            ->call('simpan')
            ->assertHasErrors('termin')
            ->assertSee('Jumlah termin harus sama dengan total RAB Komite (Rp10.000.000); termin kurang Rp1.320.000 dari total RAB.')
            ->assertNotDispatched('termin-disimpan')
            ->assertSet('adaPerubahan', true);
        $this->assertSame([['tanggal' => '2026-06-17', 'nominal' => 7_680_000], ['tanggal' => '2026-06-19', 'nominal' => 2_320_000]], $this->termin());

        // Disesuaikan: galat hilang begitu ada perubahan, lalu tersimpan.
        $komponen->set('baris.1.nominal', '2.000.000')
            ->assertHasNoErrors('termin')
            ->call('isiSisa')
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertDispatched('termin-disimpan', sumber: 'Komite')
            ->assertSet('adaPerubahan', false);
        $this->assertSame([['tanggal' => '2026-06-17', 'nominal' => 7_680_000], ['tanggal' => '2026-06-19', 'nominal' => 2_320_000]], $this->termin());
    }

    public function test_bagi_rata_validasi_tanggal_dan_sekaligus(): void
    {
        $komponen = Livewire::test(TerminPembayaran::class, ['kegiatanId' => 1, 'sumber' => 'Komite'])
            ->call('bagiRata', 3)
            ->assertSet('baris.0.nominal', '3.333.333')
            ->assertSet('baris.2.nominal', '3.333.334')
            ->assertSet('baris.0.tanggal', '2026-06-17')
            ->set('baris.2.tanggal', '2027-01-05')
            ->set('baris.1.tanggal', '2026-06-01')
            ->call('simpan')
            ->assertHasErrors(['baris.2.tanggal', 'baris.1.tanggal'])
            ->assertSee('Tanggal termin harus di Tahun Anggaran 2026.')
            ->assertSee('Tanggal termin 2 tidak boleh sebelum termin 1.')
            ->call('batalkan')
            ->assertSet('baris.0.nominal', '7.680.000')
            ->assertCount('baris', 2)
            ->assertSet('adaPerubahan', false);

        $komponen->call('jadikanSekaligus')
            ->assertSeeHtml('data-status="sekaligus"')
            ->assertSee('Tidak ada blok termin')
            ->call('simpan')
            ->assertDispatched('notify', message: 'RAB Komite dibayar sekaligus (tanpa termin).');
        $this->assertSame([], $this->termin());

        // Dari sekaligus ke bertahap: langsung terisi dua termin sama rata.
        $komponen->call('bagiRata', 2)->assertCount('baris', 2)->assertSet('baris.0.nominal', '5.000.000');
    }

    public function test_termin_terkunci_di_luar_draf(): void
    {
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Terkunci', 'status' => 'final', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]]]);

        Livewire::test(TerminPembayaran::class, ['kegiatanId' => $id, 'sumber' => 'DIPA'])
            ->assertSee('Termin terkunci karena kegiatan berstatus Final.')
            ->assertDontSee('Simpan termin')
            ->call('tambahTermin')
            ->assertForbidden();
    }
}
