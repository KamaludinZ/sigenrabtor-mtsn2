<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Contracts\TemplateCoverRepository;
use App\Livewire\Kegiatan\PratinjauDokumen;
use App\Support\Dokumen\KaidahDokumen;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanRab;
use App\Support\Dokumen\TeksCover;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class PratinjauDokumenTest extends TestCase
{
    /** @return array<string, string> Kunci aturan → status untuk kegiatan $data × $sumber. */
    private function statusKaidah(array $data, ?string $sumber = null): array
    {
        return array_column(KaidahDokumen::periksa($data, SusunanKak::dari($data, $sumber), SusunanRab::dari($data, $sumber)), 'status', 'kunci');
    }

    public function test_tab_pratinjau_untuk_semua_pengguna(): void
    {
        $this->get(route('kegiatan.pratinjau', ['kegiatan' => 1]))
            ->assertOk()
            ->assertSeeLivewire(PratinjauDokumen::class)
            ->assertSee('<title>Pratinjau Dokumen · Workshop Penyusunan Kurikulum', false)
            ->assertSeeHtml('aria-current="page"');

        $this->get(route('kegiatan.edit', ['kegiatan' => 1]))->assertSeeHtml('href="'.route('kegiatan.pratinjau', ['kegiatan' => 1]).'"');
        $this->get(route('kegiatan.pratinjau', ['kegiatan' => 999]))->assertNotFound();
    }

    public function test_cover_kak_dan_rab_per_sumber_dana(): void
    {
        Livewire::test(PratinjauDokumen::class, ['kegiatan' => 2])
            ->assertSeeHtml('data-testid="pratinjau-cover" data-gambar="tidak"')
            ->assertSee('KEGIATAN SUPERVISI AKADEMIK DAN REFLEKSI PEMBELAJARAN')
            ->assertSee('SUMBER DANA DIPA')
            ->assertSeeHtml('data-testid="pratinjau-kak"')
            ->assertDontSeeHtml('data-testid="pratinjau-kak-cover"')
            ->assertSeeHtml('data-testid="pratinjau-rab"')
            ->assertSeeHtml('data-testid="tabel-rab-dipa"')
            ->assertSeeHtml('data-testid="keadaan-cetak"')
            ->assertSee('Tercetak sebagai DRAF')
            ->set('sumber', 'Komite')
            ->assertSee('SUMBER DANA KOMITE')
            ->assertSeeHtml('data-testid="tabel-rab-komite"');

        Livewire::withQueryParams(['sumber' => 'BOS'])->test(PratinjauDokumen::class, ['kegiatan' => 2])->assertSet('sumber', '');
    }

    public function test_cover_memakai_template_default_tahun_aktif(): void
    {
        $id = app(TemplateCoverRepository::class)->tambah(2026, 'Cover Biru', UploadedFile::fake()->image('cover.png', 595, 842), TeksCover::bawaan());

        Livewire::test(PratinjauDokumen::class, ['kegiatan' => 1])
            ->assertSeeHtml('data-testid="pratinjau-cover" data-gambar="ya"')
            ->assertSeeHtml(route('dokumen.cover.gambar', ['cover' => $id]));

        // Gambar cover bisa dibuka akun leading (pengelolaannya tetap khusus admin).
        $this->get(route('dokumen.cover.gambar', ['cover' => $id]))->assertOk();
        $this->get(route('pengaturan.template.cover.gambar', ['cover' => $id]))->assertForbidden();
    }

    public function test_panel_kaidah_dari_data_kegiatan(): void
    {
        Livewire::test(PratinjauDokumen::class, ['kegiatan' => 3])
            ->set('sumber', 'Komite')
            ->assertSeeHtml('data-kaidah="pagu" data-status="gagal"')
            ->assertSee('Melebihi pagu Rp450.000.')
            ->assertSeeHtml('data-kaidah="total" data-status="lulus"')
            ->assertSee('Total KAK = total RAB = jumlah yang diajukan: Rp7.950.000,-.')
            ->set('sumber', 'DIPA')
            ->assertSeeHtml('data-kaidah="pagu" data-status="peringatan"')
            ->assertSee('Pagu belum diisi.');

        $this->assertSame(
            ['total' => 'lulus', 'terbilang' => 'lulus', 'tahun' => 'lulus', 'pagu' => 'lulus', 'termin' => 'lulus', 'kode' => 'lulus', 'format' => 'info'],
            array_diff_key($this->statusKaidah(app(KegiatanRepository::class)->temukan(1)), array_flip(['penanda_tangan', 'kelengkapan'])),
        );
    }

    public function test_rupiah_dan_terbilang_tercetak_dengan_jangkar_dokumen(): void
    {
        Livewire::test(PratinjauDokumen::class, ['kegiatan' => 1])
            ->assertSeeHtml('id="cetak-kak-pembiayaan"')
            ->assertSeeHtml('id="cetak-rab-diajukan"')
            ->assertSeeHtml('id="cetak-rab-total"')
            ->assertSeeHtml('data-testid="sinkron-dokumen"')
            ->assertSeeHtml('href="#cetak-kak-pembiayaan"')
            ->assertSeeHtml('href="#cetak-rab-total"')
            ->assertSee('“Rp10.000.000,- (Sepuluh Juta Rupiah)”', false)
            ->assertSee('“10.000.000”', false);

        // Halaman RAB menautkan tiap tempat cetak ke letaknya di pratinjau.
        $this->get(route('kegiatan.rab', ['kegiatan' => 1]))
            ->assertSeeHtml('href="'.route('kegiatan.pratinjau', ['kegiatan' => 1, 'sumber' => 'Komite']).'#cetak-rab-diajukan"');
    }

    public function test_aturan_kaidah(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(2);

        $asing = $this->statusKaidah(['kode_sk' => 'SK.99', 'kode_edm' => null] + $kegiatan, 'DIPA');
        $this->assertSame('gagal', $asing['kode']);

        $kosong = KaidahDokumen::periksa(['kode_edm' => null] + $kegiatan, SusunanKak::dari(['kode_edm' => null] + $kegiatan, 'DIPA'), SusunanRab::dari(['kode_edm' => null] + $kegiatan, 'DIPA'));
        $this->assertSame(['peringatan', 'Belum dipilih: Kode EDM.'], [collect($kosong)->firstWhere('kunci', 'kode')['status'], collect($kosong)->firstWhere('kunci', 'kode')['pesan']]);

        $this->assertSame('peringatan', $this->statusKaidah(['tahun_anggaran' => 2025] + $kegiatan, 'DIPA')['tahun']);

        // Tanpa sumber dana: total menunggu, pagu & termin tidak diperiksa.
        $tanpa = $this->statusKaidah(['nama_kegiatan' => 'Rapat']);
        $this->assertSame('peringatan', $tanpa['total']);
        $this->assertArrayNotHasKey('pagu', $tanpa);
        $this->assertArrayNotHasKey('termin', $tanpa);

        // Termin tidak pas → gagal.
        app(RabRepository::class)->simpanTermin(2, 'DIPA', [['tanggal' => '2026-06-01', 'nominal' => 1_000_000]]);
        $this->assertSame('gagal', $this->statusKaidah(app(KegiatanRepository::class)->temukan(2), 'DIPA')['termin']);

        $this->assertSame(['lulus' => 1, 'peringatan' => 1, 'gagal' => 0, 'info' => 1], KaidahDokumen::ringkasan([['status' => 'lulus'], ['status' => 'peringatan'], ['status' => 'info']]));
    }
}
