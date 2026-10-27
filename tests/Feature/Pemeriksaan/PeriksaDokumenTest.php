<?php

namespace Tests\Feature\Pemeriksaan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Pemeriksaan\Periksa;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanRab;
use Livewire\Livewire;
use Tests\TestCase;

class PeriksaDokumenTest extends TestCase
{
    /** Salinan contoh kegiatan 2 (DIPA + Komite) yang sudah diajukan pada $pada. */
    private function diajukan(string $nama, string $pada, array $tambahan = []): int
    {
        $data = app(KegiatanRepository::class)->temukan(2);
        unset($data['id']);

        return app(KegiatanRepository::class)->simpan($tambahan + ['nama_kegiatan' => $nama, 'status' => 'diperiksa', 'riwayat_status' => [
            ['aksi' => 'dibuat', 'status' => 'draf', 'oleh' => 'Muslimin, M.Pd.', 'peran' => 'leading', 'pada' => '2026-02-01T08:00:00+07:00'],
            ['aksi' => 'ajukan', 'status' => 'diperiksa', 'oleh' => 'Muslimin, M.Pd.', 'peran' => 'leading', 'pada' => $pada],
        ]] + $data);
    }

    public function test_hanya_admin_dan_kegiatan_harus_ada(): void
    {
        $this->get(route('pemeriksaan.periksa', ['kegiatan' => 1]))->assertForbidden();

        $this->withSession(['stub_role' => 'admin'])->get(route('pemeriksaan.periksa', ['kegiatan' => 1]))
            ->assertOk()
            ->assertSeeLivewire(Periksa::class)
            ->assertSee('<title>Periksa · Workshop Penyusunan Kurikulum', false)
            ->assertSeeHtml('href="'.route('pemeriksaan.index').'"');

        $this->withSession(['stub_role' => 'admin'])->get(route('pemeriksaan.periksa', ['kegiatan' => 999]))->assertNotFound();
    }

    public function test_pratinjau_kak_dan_rab_per_sumber_dana_di_url(): void
    {
        Livewire::test(Periksa::class, ['kegiatan' => 2])
            ->assertSet('dokumen', 'kak')
            ->assertSeeHtml('data-testid="pratinjau-kak"')
            ->assertSee('Sumber dana: DIPA')
            ->set('dokumen', 'rab')
            ->assertSeeHtml('data-testid="pratinjau-rab"')
            ->assertSee('Rencana Anggaran Biaya Kegiatan Supervisi Akademik dan Refleksi Pembelajaran MTsN 2 Kota Malang Tahun 2026')
            ->assertSeeInOrder(['Kementerian', 'Kementerian Agama', 'Satker', 'Madrasah Tsanawiyah Negeri 2 Kota Malang', 'Kode RKTM', '6.19.1', 'Jumlah yang diajukan', 'Rp5.400.000,- (Lima Juta Empat Ratus Ribu Rupiah)'])
            ->assertSeeHtml('data-testid="tabel-rab-dipa"')
            ->assertSeeHtml('data-testid="pengesahan-rab"')
            ->set('sumber', 'Komite')
            ->assertSeeHtml('data-testid="tabel-rab-komite"')
            ->assertSee('Rp2.500.000,- (Dua Juta Lima Ratus Ribu Rupiah)')
            ->call('tampilkan', 'kak', 'DIPA')
            ->assertSet('dokumen', 'kak')
            ->assertSet('sumber', 'DIPA')
            ->assertSeeHtml('data-testid="pratinjau-kak"');

        Livewire::withQueryParams(['dokumen' => 'acak', 'sumber' => 'BOS'])->test(Periksa::class, ['kegiatan' => 2])
            ->assertSet('dokumen', 'kak')
            ->assertSet('sumber', '');
        Livewire::withQueryParams(['sumber' => 'DIPA'])->test(Periksa::class, ['kegiatan' => 1])->assertSet('sumber', '');
    }

    public function test_kelengkapan_anggaran_dan_peringatan_pagu(): void
    {
        $komponen = Livewire::test(Periksa::class, ['kegiatan' => 3]);
        $kegiatan = app(KegiatanRepository::class)->temukan(3);

        $this->assertSame([
            ['sumber' => 'DIPA', 'kak' => count(SusunanKak::dari($kegiatan, 'DIPA')->kurang), 'rab' => count(SusunanRab::dari($kegiatan, 'DIPA')->kurang)],
            ['sumber' => 'Komite', 'kak' => count(SusunanKak::dari($kegiatan, 'Komite')->kurang), 'rab' => count(SusunanRab::dari($kegiatan, 'Komite')->kurang)],
        ], $komponen->instance()->kelengkapan);

        $komponen
            ->assertSeeHtml('data-dokumen="rab-komite"')
            ->assertSeeHtml('data-status-pagu="tanpa_pagu"')
            ->assertSee('Melebihi pagu Rp450.000')
            ->assertSee('Dibayar sekaligus')
            ->call('tampilkan', 'rab', 'Komite')
            ->assertSeeHtml('data-testid="peringatan-pagu-komite"');

        Livewire::test(Periksa::class, ['kegiatan' => 1])
            ->assertSee('Jumlah termin sama dengan total RAB')
            ->set('dokumen', 'rab')
            ->assertSeeHtml('data-testid="mekanisme-rab"')
            ->assertSeeInOrder(['Mekanisme Pembayaran', 'Termin', 'Termin 1 (17', 'Rp7.680.000,00']);
    }

    public function test_posisi_antrean_panel_keputusan_dan_riwayat(): void
    {
        $lama = $this->diajukan('Pengajuan Lama', '2026-03-01T09:00:00+07:00');
        $baru = $this->diajukan('Pengajuan Baru', '2026-03-04T09:00:00+07:00');

        Livewire::test(Periksa::class, ['kegiatan' => $baru])
            ->assertSeeHtml('data-testid="posisi-antrean"')
            ->assertSee('Antrean 2 dari 2')
            ->assertSeeHtml('href="'.route('pemeriksaan.periksa', ['kegiatan' => $lama]).'"')
            ->assertSee('Diajukan 4 Mar 2026, 09.00 oleh Muslimin, M.Pd.')
            ->assertSee('Keputusan pemeriksaan')
            ->assertSee('Catatan untuk leading')
            ->assertSeeInOrder(['Riwayat status', 'Diajukan untuk diperiksa', 'Draf dibuat']);

        Livewire::test(Periksa::class, ['kegiatan' => 1])
            ->assertDontSeeHtml('data-testid="posisi-antrean"')
            ->assertSee('Belum diajukan')
            ->assertDontSee('Catatan untuk leading')
            ->assertSee('Belum ada perubahan status.');

        $final = $this->diajukan('Sudah Final', '2026-02-20T09:00:00+07:00', ['status' => 'final', 'riwayat_status' => [
            ['aksi' => 'kembalikan', 'status' => 'dikembalikan', 'oleh' => 'Koordinator PUSKOM', 'peran' => 'admin', 'pada' => '2026-02-21T10:00:00+07:00', 'catatan' => 'Sasaran belum terukur.'],
            ['aksi' => 'finalkan', 'status' => 'final', 'oleh' => 'Koordinator PUSKOM', 'peran' => 'admin', 'pada' => '2026-03-02T10:00:00+07:00'],
        ]]);
        Livewire::test(Periksa::class, ['kegiatan' => $final])
            ->assertSee('Terkunci')
            ->assertSeeInOrder(['Ditandai final', 'Dikembalikan untuk perbaikan', 'Sasaran belum terukur.']);
    }

    public function test_susunan_rab_menandai_isian_yang_kurang(): void
    {
        $rab = SusunanRab::dari(['nama_kegiatan' => 'Kegiatan Rapat Kerja', 'status' => 'disetujui', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]]]);

        $this->assertSame('Rencana Anggaran Biaya Kegiatan Rapat Kerja MTsN 2 Kota Malang Tahun 2026', $rab->judul);
        $this->assertSame(['kode rktm', 'kode sk', 'iksk', 'kode edm', '5 budaya mutu', 'rincian belanja RAB DIPA', 'tanggal dokumen'], $rab->kurang);
        $this->assertTrue($rab->draf);
        $this->assertSame([], $rab->mekanisme);
        $this->assertNull(collect($rab->kepala)->firstWhere('label', 'Jumlah yang diajukan')['nilai']);
        $this->assertSame('Kode & Klasifikasi', collect($rab->kepala)->firstWhere('label', 'Kode SK')['asal']);

        $tanpaSumber = SusunanRab::dari(['nama_kegiatan' => 'Rapat']);
        $this->assertNull($tanpaSumber->anggaran);
        $this->assertContains('sumber dana', $tanpaSumber->kurang);

        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        $this->assertSame([], SusunanRab::dari($kegiatan)->kurang);
        $this->assertTrue(SusunanRab::dari($kegiatan)->draf, 'Belum disetujui tetap DRAF.');
        $this->assertFalse(SusunanRab::dari(['status' => 'disetujui'] + $kegiatan)->draf);
    }
}
