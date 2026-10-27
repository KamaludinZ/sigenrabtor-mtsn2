<?php

namespace Tests\Feature\Buku;

use App\Contracts\KegiatanRepository;
use App\Contracts\TemplateCoverRepository;
use App\Livewire\Buku\Daftar;
use App\Support\Dokumen\BukuDokumen;
use App\Support\Dokumen\TeksCover;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class BukuDokumenTest extends TestCase
{
    public function test_halaman_buku_untuk_semua_pengguna_dan_menu_aktif(): void
    {
        $this->get(route('buku.index'))
            ->assertOk()
            ->assertSeeLivewire(Daftar::class)
            ->assertSee('<title>Buku Dokumen · ', false)
            ->assertSeeHtml('href="'.route('buku.index').'"')
            ->assertSeeHtml('aria-current="page"');
    }

    public function test_akun_leading_melihat_buku_leading_nya(): void
    {
        $kegiatan = BukuDokumen::tahunAktif(app(KegiatanRepository::class)->semua());
        $leadings = BukuDokumen::leadingUntuk(['role' => 'leading', 'leadings' => ['1.6']], $kegiatan);
        $this->assertSame(['1.6'], array_column($leadings, 'kode'));

        $peta = BukuDokumen::peta($leadings, $kegiatan);
        $this->assertSame([['1.6', 'DIPA', 2], ['1.6', 'Komite', 3]], array_map(fn ($b) => [$b['leading'], $b['sumber'], $b['jumlah']], $peta));

        // Isi buku urut kode RKTM bertingkat, dengan halaman awal dari simulasi nomor halaman.
        $isi = BukuDokumen::isi($kegiatan, '1.6', 'Komite');
        $this->assertSame([1, 3, 2], array_column($isi['baris'], 'id'), '6.17.12, 6.17.13, 6.19.1');
        $this->assertSame($isi['simulasi']['mulai'], array_column($isi['baris'], 'halaman'));
        $this->assertSame(20_450_000, $isi['total']);
        $this->assertTrue($isi['baris'][0]['draf']);

        Livewire::test(Daftar::class)
            ->assertSeeHtml('data-testid="parameter-buku"')
            ->assertSee('1.6 · Tim Penjamin Mutu — DIPA')
            ->assertSeeInOrder(['Cover buku', 'Daftar Kegiatan', 'Review RKJM dan Penyusunan RKTM', 'Supervisi Akademik'])
            ->assertSee('TOR_RAB_Tim_Penjamin_Mutu_DIPA_2026.pdf')
            ->assertSeeHtml('data-cover-buku="1.6|DIPA"')
            ->assertSee('SUMBER DANA DIPA')
            ->assertSee('KETUA TIM PENJAMIN MUTU')
            ->assertSee('urut kode RKTM')
            ->assertSee('2 kegiatan masih DRAF')
            ->assertSeeHtml('data-buku="1.6|Komite" data-jumlah="3"')
            ->call('pilih', '1.6', 'Komite')
            ->assertSet('sumber', 'Komite')
            ->assertSee('1.6 · Tim Penjamin Mutu — Komite')
            ->assertSeeHtml('href="'.route('kegiatan.pratinjau', ['kegiatan' => 1, 'sumber' => 'Komite']).'"')
            ->assertDontSee('Pengaturan buku');

        Livewire::withQueryParams(['leading' => '1.1', 'sumber' => 'BOS'])->test(Daftar::class)
            ->assertSet('leading', '')
            ->assertSet('sumber', '');
    }

    public function test_cover_buku_memakai_desain_cover_dan_nilai_buku(): void
    {
        $this->assertSame([
            'kop' => 'MADRASAH TSANAWIYAH NEGERI 2 KOTA MALANG',
            'judul' => 'KEGIATAN',
            'nama_kegiatan' => 'TIM PENJAMIN MUTU',
            'sumber_anggaran' => 'SUMBER DANA KOMITE',
            'disusun_oleh' => 'Disusun oleh:',
            'leading' => 'KETUA TIM PENJAMIN MUTU',
            'kota' => 'MALANG',
            'tahun' => 'TAHUN ANGGARAN 2026',
        ], BukuDokumen::nilaiCover('1.6', 'Komite'));
        $this->assertSame(array_keys(TeksCover::FIELD), array_keys(BukuDokumen::nilaiCover('1.6', 'Komite')), 'Mengisi kotak teks desain cover yang sama.');

        $id = app(TemplateCoverRepository::class)->tambah(2026, 'Cover Biru', UploadedFile::fake()->image('cover.png', 595, 842), TeksCover::bawaan());
        $html = Livewire::test(Daftar::class)->html();
        $this->assertSame(2, substr_count($html, 'data-cover-buku="1.6|DIPA"'), 'Gambar kecil + tampilan besar.');
        $this->assertStringContainsString(route('dokumen.cover.gambar', ['cover' => $id]), $html);
        $this->assertStringContainsString('data-testid="cap-draf"', $html);
        $this->assertStringNotContainsString('id="cetak-cover-tahun"', $html, 'Tanpa jangkar ganda.');
    }

    public function test_admin_melihat_semua_buku(): void
    {
        session(['stub_role' => 'admin']);
        $kegiatan = BukuDokumen::tahunAktif(app(KegiatanRepository::class)->semua());
        $this->assertCount(16, BukuDokumen::peta(BukuDokumen::leadingUntuk(['role' => 'admin', 'leadings' => []], $kegiatan), $kegiatan), '8 leading aktif × 2 sumber dana; leading nonaktif tanpa kegiatan tidak ikut.');

        Livewire::withQueryParams(['leading' => '1.1', 'sumber' => 'Komite'])->test(Daftar::class)
            ->assertSee('1.1 · Waka Kurikulum — Komite')
            ->assertSeeHtml('data-testid="buku-tanpa-kegiatan"')
            ->assertSee('Pengaturan buku')
            ->assertSeeHtml('href="'.route('pengaturan.template.index', ['tab' => 'daftar_isi']).'"');
    }
}
