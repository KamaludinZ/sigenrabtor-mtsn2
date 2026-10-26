<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\TemplateCoverRepository;
use App\Livewire\Pengaturan\TemplateDokumen;
use App\Support\Dokumen\TeksCover;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class EditorCoverTest extends TestCase
{
    private function coverContoh(): string
    {
        return app(TemplateCoverRepository::class)->tambah(2026, 'Cover default 2026', UploadedFile::fake()->image('cover.png', 1240, 1754), TeksCover::bawaan());
    }

    public function test_buka_editor_dari_pratinjau_cover_tersimpan(): void
    {
        $id = $this->coverContoh();

        Livewire::test(TemplateDokumen::class)
            ->assertSee('Atur kotak teks')
            ->assertDontSeeHtml('data-testid="editor-cover"')
            ->call('aturKotak', $id)
            ->assertSet('coverDiatur', $id)
            ->assertSet('kotak', TeksCover::bawaan())
            ->assertSeeHtml('data-testid="editor-cover"')
            ->assertSee('Atur kotak teks — Cover default 2026')
            ->assertSeeHtml('x-data="editorCover({ bawaan: ')
            ->assertSeeHtml('wire:key="editor-'.$id.'-3-DIPA"')
            ->assertSeeHtml('src="'.route('pengaturan.template.cover.gambar', $id).'"')
            ->assertSeeHtmlInOrder(['Kolom di cover', 'Kop madrasah', 'Judul dokumen', 'Nama kegiatan (versi cover)', 'Tahun anggaran'])
            ->assertSeeHtmlInOrder(['Kiri (%)', 'Atas (%)', 'Lebar (%)', 'Ukuran huruf (pt)'])
            ->assertDontSeeHtml('data-testid="unggah-cover"')
            ->call('aturKotak', 'bukan-id')
            ->assertSet('coverDiatur', $id)
            ->call('batalAtur')
            ->assertSet('coverDiatur', null)
            ->assertSet('kotak', [])
            ->assertSeeHtml('data-testid="unggah-cover"');
    }

    public function test_simpan_tata_letak_dirapikan(): void
    {
        $id = $this->coverContoh();
        $kotak = TeksCover::bawaan();
        $kotak[1] = ['y' => 30.5, 'font_size' => 22, 'align' => 'left', 'color' => '#0F5E42'] + $kotak[1];
        $kotak[2]['x'] = 150; // di luar halaman → dibatasi
        unset($kotak[4]); // "Disusun oleh" disembunyikan

        Livewire::test(TemplateDokumen::class)
            ->call('aturKotak', $id)
            ->set('kotak', array_values($kotak))
            ->call('simpanTataLetak')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Tata letak “Cover default 2026” tersimpan.')
            ->assertSet('coverDiatur', null)
            ->assertSeeHtml('data-testid="sumber-pratinjau" data-jenis="tersimpan"')
            ->assertSeeHtml('top: 30.5%');

        $tersimpan = TeksCover::rapikan(app(TemplateCoverRepository::class)->temukan($id)['teks_items']);
        $this->assertNotContains('disusun_oleh', array_column($tersimpan, 'field'));
        $this->assertSame(['y' => 30.5, 'font_size' => 22.0, 'align' => 'left', 'color' => '#0f5e42'], array_intersect_key($tersimpan[1], array_flip(['y', 'font_size', 'align', 'color'])));
        $this->assertSame([100.0, 5.0], [$tersimpan[2]['x'], $tersimpan[2]['width']]);
    }

    public function test_tata_letak_kosong_ditolak_dan_hapus_menutup_editor(): void
    {
        $id = $this->coverContoh();

        $komponen = Livewire::test(TemplateDokumen::class)
            ->call('aturKotak', $id)
            ->set('kotak', [['field' => 'tidak-dikenal']])
            ->call('simpanTataLetak')
            ->assertHasErrors(['kotak'])
            ->assertSee('Tampilkan minimal satu kotak teks di cover.')
            ->assertSet('coverDiatur', $id);
        $this->assertSame(TeksCover::bawaan(), TeksCover::rapikan(app(TemplateCoverRepository::class)->temukan($id)['teks_items']));

        // Memilih gambar baru menutup editor.
        $komponen->set('gambar', UploadedFile::fake()->image('baru.png', 1240, 1754))
            ->assertSet('coverDiatur', null)
            ->assertSeeHtml('data-testid="sumber-pratinjau" data-jenis="baru"');

        $komponen->call('batalUnggah')->call('aturKotak', $id)->call('mintaHapus', $id)->call('hapusCover')->assertSet('coverDiatur', null);
    }
}
