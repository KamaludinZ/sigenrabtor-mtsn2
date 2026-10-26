<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\TemplateCoverRepository;
use App\Livewire\Pengaturan\TemplateDokumen;
use App\Support\Dokumen\TeksCover;
use App\Support\Pengaturan\KesiapanPengaturan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TemplateCoverTest extends TestCase
{
    public function test_kotak_teks_bawaan_dirapikan_dan_isinya_dari_kegiatan(): void
    {
        $bawaan = TeksCover::bawaan();
        $this->assertSame(array_keys(TeksCover::FIELD), array_column($bawaan, 'field'));
        $this->assertSame($bawaan, TeksCover::rapikan($bawaan));

        $this->assertSame([[
            'field' => 'judul', 'x' => 100.0, 'y' => 0.0, 'width' => 5.0, 'font_size' => 48.0, 'align' => 'center', 'weight' => 'bold', 'color' => '#1c1917',
        ]], TeksCover::rapikan([
            ['field' => 'judul', 'x' => 120, 'y' => -5, 'width' => 50, 'font_size' => 99, 'align' => 'justify', 'weight' => 'berat', 'color' => 'red;background:url(x)'],
            ['field' => 'tidak-dikenal', 'x' => 10],
            'bukan kotak',
        ]));

        $nilai = TeksCover::nilai(app(KegiatanRepository::class)->temukan(1), 'Komite');
        $this->assertSame('MADRASAH TSANAWIYAH NEGERI 2 KOTA MALANG', $nilai['kop']);
        $this->assertSame('KEGIATAN WORKSHOP PENYUSUNAN KURIKULUM MADRASAH DAN PERANGKAT PEMBELAJARAN', $nilai['nama_kegiatan']);
        $this->assertSame(['SUMBER DANA KOMITE', 'KETUA TIM PENJAMIN MUTU', 'MALANG', 'TAHUN ANGGARAN 2026'], [$nilai['sumber_anggaran'], $nilai['leading'], $nilai['kota'], $nilai['tahun']]);
        $this->assertSame(['KEGIATAN …', 'SUMBER DANA …', 'PEJABAT LEADING'], array_values(array_intersect_key(TeksCover::nilai([], null), array_flip(['nama_kegiatan', 'sumber_anggaran', 'leading']))));
    }

    public function test_halaman_khusus_admin_dengan_tab_dan_tata_letak_baku(): void
    {
        $this->get(route('pengaturan.template.index'))->assertForbidden();
        $this->withSession(['stub_role' => 'admin'])->get(route('pengaturan.template.index'))->assertOk()->assertSeeLivewire(TemplateDokumen::class);

        Livewire::test(TemplateDokumen::class)
            ->assertSeeHtmlInOrder(['aria-current="page"', 'Cover', 'KAK/TOR', 'Segera', 'Daftar Isi', 'Nomor Halaman'])
            ->assertSeeHtml('data-testid="cover-kosong"')
            ->assertSeeHtml('data-testid="sumber-pratinjau" data-jenis="baku"')
            ->assertSeeHtml('data-testid="pratinjau-cover" data-gambar="tidak"')
            ->assertSeeHtml('data-field="nama_kegiatan"')
            ->assertSee('KEGIATAN REVIEW RKJM DAN PENYUSUNAN RKTM')
            ->assertSet('contohSumber', 'DIPA')
            ->assertSet('namaCover', 'Cover default 2026');
    }

    public function test_unggah_pratinjau_lalu_simpan_sebagai_cover_default(): void
    {
        $komponen = Livewire::test(TemplateDokumen::class)
            ->set('gambar', UploadedFile::fake()->image('desain-cover.png', 1240, 1754))
            ->assertHasNoErrors()
            ->assertSeeHtml('data-testid="info-gambar"')
            ->assertSee('desain-cover.png')
            ->assertSee('1.240 × 1.754 piksel')
            ->assertSeeHtml('data-testid="rasio-gambar" data-a4="ya"')
            ->assertSeeHtml('data-testid="sumber-pratinjau" data-jenis="baru"')
            ->assertSeeHtml('data-testid="gambar-cover"')
            ->assertSee('Simpan sebagai cover default')
            ->call('simpanCover')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Cover tersimpan.')
            ->assertSet('gambar', null)
            ->assertSet('namaCover', 'Cover 2 2026');

        $covers = app(TemplateCoverRepository::class)->semua(2026);
        $this->assertCount(1, $covers);
        $this->assertSame(['Cover default 2026', true, 1240, 1754], [$covers[0]['nama'], $covers[0]['is_default'], $covers[0]['lebar'], $covers[0]['tinggi']]);
        $this->assertSame(TeksCover::bawaan(), TeksCover::rapikan($covers[0]['teks_items']), 'Tersimpan apa adanya (angka bulat dari JSON tetap sama setelah dirapikan).');
        Storage::disk('local')->assertExists($covers[0]['gambar_path']);
        $this->assertStringStartsWith('stub/cover-template/', $covers[0]['gambar_path']);

        $komponen->assertSeeHtml('data-testid="sumber-pratinjau" data-jenis="tersimpan"')
            ->assertSeeHtml('src="'.route('pengaturan.template.cover.gambar', $covers[0]['id']).'"')
            ->assertSee('Default');

        // Gambar tersimpan dilayani untuk admin saja.
        $this->get(route('pengaturan.template.cover.gambar', $covers[0]['id']))->assertForbidden();
        $this->withSession(['stub_role' => 'admin'])->get(route('pengaturan.template.cover.gambar', $covers[0]['id']))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('pengaturan.template.cover.gambar', '00000000-0000-0000-0000-000000000000'))->assertNotFound();

        $this->assertTrue(collect(KesiapanPengaturan::tahunAktif()->butir)->firstWhere('kunci', 'cover')['siap']);
    }

    public function test_validasi_gambar_dan_peringatan_rasio(): void
    {
        Livewire::test(TemplateDokumen::class)
            ->set('gambar', UploadedFile::fake()->create('cover.pdf', 100, 'application/pdf'))
            ->assertHasErrors(['gambar'])
            ->assertSee('Berkas harus berupa gambar JPG atau PNG.')
            ->assertSeeHtml('data-testid="sumber-pratinjau" data-jenis="baku"')
            ->set('gambar', UploadedFile::fake()->image('kecil.png', 300, 400))
            ->assertHasErrors(['gambar' => 'dimensions'])
            ->set('gambar', UploadedFile::fake()->image('besar.jpg', 1240, 1754)->size(6000))
            ->assertHasErrors(['gambar' => 'max'])
            ->set('gambar', UploadedFile::fake()->image('mendatar.jpg', 1754, 1240))
            ->assertHasNoErrors()
            ->assertSeeHtml('data-testid="rasio-gambar" data-a4="tidak"')
            ->assertSee('Gambar mendatar, bukan potret')
            ->set('namaCover', '')
            ->call('simpanCover')
            ->assertHasErrors(['namaCover' => 'required'])
            ->call('batalUnggah')
            ->assertSet('gambar', null)
            ->assertHasNoErrors();

        $this->assertSame([], app(TemplateCoverRepository::class)->semua(2026));
    }

    public function test_pilih_pratinjau_dan_hapus_cover(): void
    {
        $repo = app(TemplateCoverRepository::class);
        $pertama = $repo->tambah(2026, 'Cover A', UploadedFile::fake()->image('a.png', 1240, 1754), TeksCover::bawaan());
        $this->travel(1)->minutes();
        $kedua = $repo->tambah(2026, 'Cover B', UploadedFile::fake()->image('b.jpg', 1240, 1754), TeksCover::bawaan());
        $this->assertSame(['Cover A', 'Cover B'], array_column($repo->semua(2026), 'nama'), 'Default lebih dulu.');

        Livewire::test(TemplateDokumen::class)
            ->assertSet('namaCover', 'Cover 3 2026')
            ->assertSeeInOrder(['Cover A', 'Default', 'Cover B'])
            ->call('pilihCover', $kedua)
            ->assertSet('coverDipilih', $kedua)
            ->assertSeeHtml('data-testid="sumber-pratinjau" data-jenis="tersimpan">Cover B')
            ->call('pilihCover', 'bukan-id')
            ->assertSet('coverDipilih', $kedua)
            ->call('mintaHapus', $pertama)
            ->assertSee('Hapus cover ini?')
            ->call('batalHapus')
            ->assertDontSee('Hapus cover ini?')
            ->call('mintaHapus', $pertama)
            ->call('hapusCover')
            ->assertDispatched('notify', message: 'Cover “Cover A” dihapus.')
            ->assertDontSee('Cover A');

        $sisa = $repo->semua(2026);
        $this->assertSame([['Cover B', true]], array_map(fn ($c) => [$c['nama'], $c['is_default']], $sisa), 'Default pindah ke cover berikutnya.');
        $this->assertNull($repo->temukan($pertama));
    }

    public function test_komponen_cover_berskala_halaman(): void
    {
        $html = Blade::render('<x-dokumen.cover :teks="$t" :nilai="$n" garis />', ['t' => TeksCover::bawaan(), 'n' => ['judul' => 'KAK']]);

        $this->assertStringContainsString('@container relative mx-auto aspect-[210/297]', $html);
        $this->assertStringContainsString('font-size: calc(18 * 100cqw / 595)', $html);
        $this->assertStringContainsString('outline-dashed', $html);
        $this->assertSame(8, substr_count($html, 'data-field='));
    }
}
