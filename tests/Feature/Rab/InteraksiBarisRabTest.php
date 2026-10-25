<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\Rab;
use Livewire\Livewire;
use Tests\TestCase;

class InteraksiBarisRabTest extends TestCase
{
    private function uraian(string $sumber, int $kegiatan = 2): array
    {
        return array_column(app(RabRepository::class)->rincian($kegiatan, $sumber), 'uraian');
    }

    private function id(string $sumber, int $index, int $kegiatan = 2): string
    {
        return app(RabRepository::class)->rincian($kegiatan, $sumber)[$index]['id'];
    }

    public function test_salin_menyisipkan_tepat_di_bawah_dan_membuka_form_ubah(): void
    {
        $awal = $this->uraian('DIPA');
        $sumberId = $this->id('DIPA', 1);

        $komponen = Livewire::test(Rab::class, ['kegiatan' => 2])
            ->call('salinItem', $sumberId)
            ->assertSet('formItemTerbuka', true)
            ->assertDispatched('notify');

        $rincian = app(RabRepository::class)->rincian(2, 'DIPA');
        $this->assertSame(array_merge(array_slice($awal, 0, 2), [$awal[1]], array_slice($awal, 2)), array_column($rincian, 'uraian'));
        $this->assertNotSame($sumberId, $rincian[2]['id']);
        $komponen->assertSet('itemDiedit', $rincian[2]['id'])->assertSee('Ubah item no. 3');

        $komponen->call('salinItem', 'tidak-ada')->assertDispatched('notify', type: 'error');
    }

    public function test_naik_turun_mengubah_urutan_cetak(): void
    {
        $awal = $this->uraian('DIPA');
        $komponen = Livewire::test(Rab::class, ['kegiatan' => 2]);

        $komponen->call('geserItem', $this->id('DIPA', 0), 1);
        $this->assertSame([$awal[1], $awal[0]], array_slice($this->uraian('DIPA'), 0, 2));

        $komponen->call('geserItem', $this->id('DIPA', 1), -1);
        $this->assertSame($awal, $this->uraian('DIPA'));

        // Ujung daftar: tidak bergerak.
        $komponen->call('geserItem', $this->id('DIPA', 0), -1)->call('geserItem', $this->id('DIPA', count($awal) - 1), 1);
        $this->assertSame($awal, $this->uraian('DIPA'));
    }

    public function test_pindah_ke_rab_sumber_lain_dan_urungkan(): void
    {
        $dipa = $this->uraian('DIPA');
        $komite = $this->uraian('Komite');
        $dipindah = $dipa[2];

        $komponen = Livewire::test(Rab::class, ['kegiatan' => 2])
            ->call('pindahSumberItem', $this->id('DIPA', 2), 'Komite')
            ->assertSeeHtml('data-testid="banner-pindah"')
            ->assertSee("“{$dipindah}” dipindah ke RAB Komite.");

        $this->assertNotContains($dipindah, $this->uraian('DIPA'));
        $this->assertSame([...$komite, $dipindah], $this->uraian('Komite'));
        $this->assertSame('Komite', $komponen->get('itemDipindah')['ke']);

        $komponen->call('urungkanPindahItem')->assertSet('itemDipindah', null)->assertDontSeeHtml('data-testid="banner-pindah"');
        $this->assertSame($dipa, $this->uraian('DIPA'));
        $this->assertSame($komite, $this->uraian('Komite'));

        // Tujuan tidak sah atau sama dengan sumber yang dibuka: tidak terjadi apa-apa.
        $komponen->call('pindahSumberItem', $this->id('DIPA', 0), 'BOS')->call('pindahSumberItem', $this->id('DIPA', 0), 'DIPA');
        $this->assertSame($dipa, $this->uraian('DIPA'));
    }

    public function test_menu_aksi_mengikuti_sumber_dana_dan_mode_tampilan(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 2])
            ->assertSee('Salin &amp; ubah', false)
            ->assertSee('Naikkan urutan')
            ->assertSee('Pindahkan ke')
            ->assertSeeHtml('data-sumber-dana="Komite"')
            ->set('kelompok', 'kategori')
            ->assertDontSee('Naikkan urutan');

        // Kegiatan satu sumber dana: tidak ada tujuan pemindahan.
        Livewire::test(Rab::class, ['kegiatan' => 1])->assertDontSee('Pindahkan ke');
    }

    public function test_interaksi_baru_terkunci_di_luar_draf(): void
    {
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Terkunci', 'status' => 'disetujui',
            'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000], ['sumber' => 'Komite', 'pagu' => 1_000_000]]]);
        $item = app(RabRepository::class)->tambahItem($id, 'DIPA', ['kode' => '', 'uraian' => 'ATK', 'kategori' => 'bahan', 'volume' => 1, 'satuan' => 'paket', 'harga_satuan' => 10_000]);

        Livewire::test(Rab::class, ['kegiatan' => $id])->assertDontSee('Salin &amp; ubah', false)->call('salinItem', $item)->assertForbidden();
        Livewire::test(Rab::class, ['kegiatan' => $id])->call('geserItem', $item, 1)->assertForbidden();
        Livewire::test(Rab::class, ['kegiatan' => $id])->call('pindahSumberItem', $item, 'Komite')->assertForbidden();
        $this->assertCount(1, app(RabRepository::class)->rincian($id, 'DIPA'));
    }
}
