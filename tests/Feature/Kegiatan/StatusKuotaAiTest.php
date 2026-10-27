<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\PengaturanAiRepository;
use App\Livewire\Kegiatan\KalimatAi;
use App\Livewire\Kegiatan\MatrikJadwal;
use App\Livewire\SaranAi\Riwayat;
use App\Support\Kegiatan\BatasAi;
use App\Support\Kegiatan\StatusKuotaAi;
use Livewire\Livewire;
use Tests\TestCase;

class StatusKuotaAiTest extends TestCase
{
    private function pakai(int $kali, string $fitur = 'tujuan'): void
    {
        foreach (range(1, $kali) as $_) {
            BatasAi::catat(['id' => 1], $fitur);
        }
    }

    public function test_tingkat_dan_rincian_kuota(): void
    {
        $this->assertSame(['aman', 'menipis', 'aman', 'menipis', 'habis'], [
            StatusKuotaAi::tingkat(20, 20), StatusKuotaAi::tingkat(4, 20), StatusKuotaAi::tingkat(5, 20), StatusKuotaAi::tingkat(2, 5), StatusKuotaAi::tingkat(0, 20),
        ]);

        $this->pakai(2);
        $this->pakai(1, 'usul_jadwal');
        $status = StatusKuotaAi::untuk(['id' => 1]);
        $this->assertSame([20, 3, 17, 15, 'aman', false, '00.00'], [$status['batas'], $status['terpakai'], $status['sisa'], $status['persen'], $status['tingkat'], $status['khusus'], $status['pulih']]);
        $this->assertSame([['fitur' => 'tujuan', 'label' => 'Tujuan', 'jumlah' => 2], ['fitur' => 'usul_jadwal', 'label' => 'Usulan jadwal', 'jumlah' => 1]], $status['rincian']);

        $data = array_diff_key(app(PengaturanAiRepository::class)->ambil(), ['diperbarui_pada' => 1]);
        app(PengaturanAiRepository::class)->simpan(['batas_khusus' => ['1' => 5]] + $data);
        $status = StatusKuotaAi::untuk(['id' => 1]);
        $this->assertSame([5, 2, 'menipis', true], [$status['batas'], $status['sisa'], $status['tingkat'], $status['khusus']]);
    }

    public function test_status_kuota_tampil_di_kalimat_kak_modal_jadwal_dan_riwayat(): void
    {
        $this->pakai(2);
        Livewire::test(KalimatAi::class, ['kegiatan' => 1])
            ->assertSeeHtml('data-testid="kuota-ai" data-tingkat="aman"')
            ->assertSee('Sisa 18 dari 20 permintaan AI hari ini.')
            ->assertSee('Kuota pulih setiap hari pukul 00.00.')
            ->assertSeeHtml('data-testid="rincian-kuota"')
            ->call('mintaSaran', 'sasaran')
            ->assertSeeHtml('data-testid="kuota-ringkas" data-tingkat="aman"')
            ->assertSee('Sisa 17/20');

        $this->pakai(15);
        Livewire::test(KalimatAi::class, ['kegiatan' => 1])->assertSeeHtml('data-tingkat="menipis"')->assertSee('Menipis');
        Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])->call('bukaUsulJadwal')->assertSeeHtml('data-testid="kuota-ringkas" data-tingkat="menipis"');

        $this->pakai(3);
        Livewire::test(KalimatAi::class, ['kegiatan' => 1])
            ->assertSeeHtml('data-testid="kuota-ai" data-tingkat="habis"')
            ->assertSee('Kuota hari ini habis — tombol AI aktif lagi besok (pulih pukul 00.00).');
        Livewire::test(Riwayat::class)->assertSee('Kuota Anda hari ini')->assertSeeHtml('data-tingkat="habis"')->assertSee('Sisa 0/20');
    }
}
