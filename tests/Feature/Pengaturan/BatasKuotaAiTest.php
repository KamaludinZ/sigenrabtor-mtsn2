<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\PengaturanAiRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Livewire\Kegiatan\KalimatAi;
use App\Livewire\Pengaturan\PengaturanAi;
use App\Support\Kegiatan\BatasAi;
use Livewire\Livewire;
use Tests\TestCase;

class BatasKuotaAiTest extends TestCase
{
    private function catatanTerbaru(): string
    {
        return collect(app(RiwayatPengaturanRepository::class)->semua())->firstWhere('aksi', 'ubah_batas_ai')['keterangan'];
    }

    public function test_aturan_batas_umum_dan_khusus(): void
    {
        $this->assertNull(BatasAi::galat('20'));
        $this->assertSame('Batas 1–200 permintaan per hari.', BatasAi::galat(0));
        $this->assertNotNull(BatasAi::galat('201'));
        $this->assertNotNull(BatasAi::galat('lima'));
        $this->assertSame([20, null, 20], [BatasAi::batas(), BatasAi::khusus(['id' => 1]), BatasAi::batas(['id' => 1])]);

        $data = array_diff_key(app(PengaturanAiRepository::class)->ambil(), ['diperbarui_pada' => 1]);
        app(PengaturanAiRepository::class)->simpan(['batas_khusus' => ['1' => 5]] + $data);
        BatasAi::catat(['id' => 1], 'tujuan');
        $this->assertSame([5, 5, 4, 20], [BatasAi::batas(['id' => 1]), BatasAi::khusus(['id' => 1]), BatasAi::sisa(['id' => 1]), BatasAi::batas(['id' => 3])]);
        $this->assertSame('Batas 5 permintaan AI hari ini sudah tercapai — coba lagi besok.', BatasAi::pesanHabis(['id' => 1]));
    }

    public function test_ubah_batas_umum_dan_khusus_per_akun(): void
    {
        session(['stub_role' => 'admin']);
        $halaman = Livewire::withQueryParams(['tab' => 'pemakaian'])->test(PengaturanAi::class)
            ->assertSet('isianBatas', 20)
            ->set('isianBatas', '0')
            ->call('simpanBatas')
            ->assertHasErrors('isianBatas')
            ->call('pilihPresetBatas', 50)
            ->call('simpanBatas')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Batas umum tersimpan: 50 permintaan AI per akun per hari.', type: 'success')
            ->assertSee('batas umum 50 per akun per hari');
        $this->assertSame([50, 'Batas umum: 20 per akun per hari → 50 per akun per hari'], [BatasAi::batas(), $this->catatanTerbaru()]);

        $halaman->call('ubahKhusus', 1)
            ->assertSet('isianKhusus', 50)
            ->set('isianKhusus', '100')
            ->call('simpanKhusus')
            ->assertSet('khususTerbuka', null)
            ->assertDispatched('notify', message: 'Batas khusus Muslimin, M.Pd.: 100 permintaan per hari.', type: 'success')
            ->assertSeeHtml('data-testid="khusus-1">Khusus 100')
            ->assertSeeHtml('data-testid="terpakai-1">0 / 100');
        $this->assertSame([100, 'Muslimin, M.Pd. — Batas: batas umum → 100 per hari'], [BatasAi::batas(['id' => 1]), $this->catatanTerbaru()]);

        // Kalimat KAK akun leading memakai batas khususnya.
        session(['stub_role' => 'leading']);
        Livewire::test(KalimatAi::class, ['kegiatan' => 1])->assertSee('Sisa 100 dari 100 permintaan AI hari ini.');
        session(['stub_role' => 'admin']);

        // Sama dengan batas umum = khusus dibuang; Pakai batas umum mengembalikan.
        $halaman->call('ubahKhusus', 3)->set('isianKhusus', '50')->call('simpanKhusus');
        $this->assertArrayNotHasKey('3', app(PengaturanAiRepository::class)->ambil()['batas_khusus']);
        $halaman->call('hapusKhusus', 1)->assertDispatched('notify', message: 'Muslimin, M.Pd. kembali memakai batas umum.', type: 'success');
        $this->assertSame([50, null], [BatasAi::batas(['id' => 1]), BatasAi::khusus(['id' => 1])]);

        Livewire::test(PengaturanAi::class)->call('ubahKhusus', 6)->assertNotFound();
    }

    public function test_batas_khusus_habis_menonaktifkan_tombol_ai_akun_itu(): void
    {
        $data = array_diff_key(app(PengaturanAiRepository::class)->ambil(), ['diperbarui_pada' => 1]);
        app(PengaturanAiRepository::class)->simpan(['batas_khusus' => ['1' => 1]] + $data);
        BatasAi::catat(['id' => 1], 'tujuan');

        Livewire::test(KalimatAi::class, ['kegiatan' => 1])
            ->assertSeeHtml('data-tombol-ai="tujuan" data-keadaan="nonaktif"')
            ->assertSee('Batas 1 permintaan AI hari ini sudah tercapai')
            ->assertSee('Sisa 0 dari 1 permintaan AI hari ini.');
    }
}
