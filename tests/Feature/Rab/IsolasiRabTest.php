<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\Rab;
use App\Support\Dokumen\SusunanKak;
use App\Support\Rab\AnggaranKegiatan;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Satu kegiatan bisa punya RAB DIPA dan RAB Komite (dokumen terpisah): perubahan pada satu RAB tidak boleh menyentuh
 * rincian, termin, total, terbilang, maupun kalimat Pembiayaan KAK RAB lainnya — juga RAB kegiatan lain.
 */
class IsolasiRabTest extends TestCase
{
    /** Jejak lengkap satu RAB: rincian, termin, total, dan kalimat Pembiayaan KAK-nya. */
    private function jejak(int $kegiatan, string $sumber): array
    {
        $data = app(KegiatanRepository::class)->temukan($kegiatan);
        $rab = app(RabRepository::class);
        $anggaran = AnggaranKegiatan::perSumber($data)[$sumber] ?? null;
        $pembiayaan = collect(SusunanKak::dari($data, $sumber)->bagian)->firstWhere('nomor', 8)['isi'][0]['teks'] ?? null;

        return [$rab->rincian($kegiatan, $sumber), $rab->termin($kegiatan, $sumber), $anggaran?->total(), $anggaran?->terbilang(), $pembiayaan];
    }

    private function id(string $sumber, int $index, int $kegiatan = 2): string
    {
        return app(RabRepository::class)->rincian($kegiatan, $sumber)[$index]['id'];
    }

    private function isiItem($komponen, string $uraian, string $volume = '2', string $harga = '50.000')
    {
        return $komponen->set('item.uraian', $uraian)->set('item.volume', $volume)->set('item.satuan', 'paket')->set('item.harga_satuan', $harga);
    }

    public function test_setiap_aksi_pada_rab_dipa_tidak_mengubah_rab_komite(): void
    {
        $komite = $this->jejak(2, 'Komite');
        $kegiatanLain = $this->jejak(1, 'Komite');
        $dipaAwal = $this->jejak(2, 'DIPA');

        $komponen = Livewire::test(Rab::class, ['kegiatan' => 2])->assertSet('sumber', 'DIPA');
        $this->isiItem($komponen->call('bukaFormItem'), 'ATK tambahan')->call('simpanItem');
        $this->isiItem($komponen->call('ubahItem', $this->id('DIPA', 0)), 'Penggandaan instrumen (revisi)', '70')->call('simpanItem');
        $komponen->call('hapusItem', $this->id('DIPA', 1))->call('urungkanHapusItem')
            ->call('hapusItem', $this->id('DIPA', 2))
            ->call('ubahKategoriItem', $this->id('DIPA', 0), 'lain')
            ->call('salinItem', $this->id('DIPA', 0))->call('tutupFormItem')
            ->call('geserItem', $this->id('DIPA', 0), 1);

        $this->assertNotEquals($dipaAwal, $this->jejak(2, 'DIPA'), 'RAB DIPA memang berubah.');
        $this->assertSame($komite, $this->jejak(2, 'Komite'), 'RAB Komite kegiatan yang sama tidak tersentuh.');
        $this->assertSame($kegiatanLain, $this->jejak(1, 'Komite'), 'RAB kegiatan lain tidak tersentuh.');
    }

    public function test_aksi_pada_rab_komite_tidak_mengubah_rab_dipa(): void
    {
        $dipa = $this->jejak(2, 'DIPA');

        $komponen = Livewire::test(Rab::class, ['kegiatan' => 2])->call('pilihSumber', 'Komite');
        $this->isiItem($komponen->call('bukaFormItem'), 'Plakat guru terbaik', '1', '250.000')->call('simpanItem');
        $komponen->call('hapusItem', $this->id('Komite', 0));

        $this->assertSame($dipa, $this->jejak(2, 'DIPA'));
        $this->assertContains('Plakat guru terbaik', array_column(app(RabRepository::class)->rincian(2, 'Komite'), 'uraian'));
    }

    public function test_id_item_rab_lain_tidak_berpengaruh(): void
    {
        $dipa = $this->jejak(2, 'DIPA');
        $komite = $this->jejak(2, 'Komite');
        $idKomite = $this->id('Komite', 0);

        Livewire::test(Rab::class, ['kegiatan' => 2])
            ->assertSet('sumber', 'DIPA')
            ->call('hapusItem', $idKomite)
            ->assertSet('itemTerhapus', null)
            ->call('ubahKategoriItem', $idKomite, 'jasa')
            ->call('geserItem', $idKomite, 1)
            ->call('salinItem', $idKomite)
            ->assertDispatched('notify', type: 'error')
            ->call('ubahItem', $idKomite)
            ->assertSet('formItemTerbuka', false)
            ->call('pindahSumberItem', $idKomite, 'Komite');

        $this->assertSame($dipa, $this->jejak(2, 'DIPA'));
        $this->assertSame($komite, $this->jejak(2, 'Komite'));
    }

    public function test_ganti_sumber_lewat_url_menutup_form_dan_tidak_menyimpan_ke_rab_lain(): void
    {
        $dipa = $this->jejak(2, 'DIPA');
        $komite = $this->jejak(2, 'Komite');

        $komponen = Livewire::test(Rab::class, ['kegiatan' => 2])->call('bukaFormItem');
        $this->isiItem($komponen, 'Item untuk DIPA')
            ->assertSet('sumberForm', 'DIPA')
            ->call('hapusItem', $this->id('DIPA', 0))
            ->set('sumber', 'Komite')
            ->assertSet('formItemTerbuka', false)
            ->assertSet('sumberForm', null)
            ->assertSet('item.uraian', '')
            ->assertSet('itemTerhapus', null)
            ->call('simpanItem')
            ->assertDispatched('notify', type: 'error')
            ->call('urungkanHapusItem');

        $this->assertSame($komite, $this->jejak(2, 'Komite'), 'Isian form DIPA tidak masuk ke RAB Komite.');
        $this->assertCount(count($dipa[0]) - 1, $this->jejak(2, 'DIPA')[0], 'Urungkan dari RAB lain tidak berlaku.');

        // Sumber yang tidak dimiliki kegiatan dinormalkan ke sumber pertama.
        $komponen->set('sumber', 'BOS')->assertSet('sumber', 'DIPA');
    }

    public function test_item_yang_hilang_saat_diubah_tidak_menimbulkan_item_baru(): void
    {
        $id = $this->id('DIPA', 0);
        $komponen = Livewire::test(Rab::class, ['kegiatan' => 2])->call('ubahItem', $id);

        // Tab lain memindahkan item itu ke RAB Komite sebelum perubahan disimpan.
        Livewire::test(Rab::class, ['kegiatan' => 2])->call('pindahSumberItem', $id, 'Komite');
        $dipa = $this->jejak(2, 'DIPA');
        $komite = $this->jejak(2, 'Komite');

        $this->isiItem($komponen, 'Perubahan yang terlambat')->call('simpanItem')
            ->assertDispatched('notify', type: 'error')
            ->assertSet('formItemTerbuka', false);

        $this->assertSame($dipa, $this->jejak(2, 'DIPA'));
        $this->assertSame($komite, $this->jejak(2, 'Komite'));
    }
}
