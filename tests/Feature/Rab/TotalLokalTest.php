<?php

namespace Tests\Feature\Rab;

use App\Livewire\Kegiatan\Rab;
use App\Support\Rab\HasilRab;
use App\Support\Rab\StatusPagu;
use Livewire\Livewire;
use Tests\TestCase;

class TotalLokalTest extends TestCase
{
    public function test_status_pagu_server_sama_dengan_kembaran_klien(): void
    {
        // Berkas yang sama dipakai tests/e2e/format.spec.js untuk statusPagu() di resources/js/format.js.
        $kasus = json_decode(file_get_contents(base_path('tests/fixtures/status-pagu.json')), true);
        $this->assertNotEmpty($kasus);

        foreach ($kasus as $k) {
            $status = StatusPagu::hitung($k['total'], $k['pagu']);
            $this->assertSame([$k['status'], $k['teks'], $k['nada']], [$status->status, $status->teks(), $status->nada()], json_encode($k));
        }
    }

    public function test_total_tanpa_item_yang_diubah(): void
    {
        $hasil = new HasilRab([['id' => 'a', 'jumlah' => 300], ['id' => 'b', 'jumlah' => 700]], 1_000, [], []);

        $this->assertSame(1_000, $hasil->totalTanpa(null));
        $this->assertSame(300, $hasil->totalTanpa('b'));
        $this->assertSame(1_000, $hasil->totalTanpa('tidak-ada'));
    }

    public function test_form_item_membawa_dasar_perkiraan_total(): void
    {
        $komponen = Livewire::test(Rab::class, ['kegiatan' => 2])
            ->assertSet('sumber', 'DIPA')
            ->call('bukaFormItem')
            ->assertSeeHtml('data-testid="perkiraan-total"')
            ->assertSeeHtml('wire:key="perkiraan-DIPA-baru-5400000-6000000"')
            ->assertSee('Total RAB DIPA bila item ini disimpan')
            ->assertSeeInOrder(['Lima Juta Empat Ratus Ribu Rupiah', 'Rp5.400.000', 'Sisa pagu Rp600.000']);

        // Mode ubah: dasar hitung = total tanpa item itu, sehingga jumlah baru menggantikan jumlah lama.
        $item = collect($komponen->instance()->rincian)->firstWhere('uraian', 'Honorarium tim supervisor (6 orang x 4 kali)');
        $this->assertSame(3_600_000, $item['jumlah']);

        $komponen->call('ubahItem', $item['id'])
            ->assertSeeHtml('wire:key="perkiraan-DIPA-'.$item['id'].'-1800000-6000000"');
    }
}
