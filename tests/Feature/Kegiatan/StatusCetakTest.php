<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\PratinjauDokumen;
use App\Support\Dokumen\BukuDokumen;
use App\Support\Dokumen\PengambilanBuku;
use App\Support\Dokumen\StatusCetak;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanRab;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Tests\TestCase;

class StatusCetakTest extends TestCase
{
    /** Kegiatan contoh 3 berstatus disetujui: DIPA siap dicetak, Komite tetap DRAF karena melebihi pagu. */
    private function disetujui(): array
    {
        $repo = app(KegiatanRepository::class);
        $repo->simpan(['status' => 'disetujui'] + $repo->temukan(3), 3);

        return $repo->temukan(3);
    }

    public function test_keputusan_cetak_draf_beserta_keluaran_cetaknya(): void
    {
        $status = StatusCetak::dari(app(KegiatanRepository::class)->temukan(3), 'Komite');

        $this->assertTrue($status->draf());
        $this->assertSame(['DRAF', 'Tercetak sebagai DRAF'], [$status->tandaAir(), $status->keadaan()]);
        $this->assertSame('KAK-TOR_RAB_Review_RKJM_dan_Penyusunan_RKTM_KOMITE_2026_DRAF.pdf', $status->namaBerkas());
        $this->assertSame('RAB_Review_RKJM_dan_Penyusunan_RKTM_KOMITE_2026_DRAF.docx', $status->namaBerkas('RAB', 'docx'));
        $this->assertSame(
            'DRAF — dicetak Rabu, 28 Oktober 2026 pukul 09.05 · Belum disetujui admin (status Draf).',
            $status->kakiHalaman(CarbonImmutable::parse('2026-10-28 02:05:00', 'UTC')),
        );
        $this->assertSame([
            ['kunci' => 'status', 'teks' => 'Belum disetujui admin (status Draf).', 'saran' => StatusCetak::SARAN['status'], 'tautan' => route('kegiatan.edit', ['kegiatan' => 3])],
            ['kunci' => 'pagu', 'teks' => 'RAB Komite melebihi pagu Rp450.000.', 'saran' => StatusCetak::SARAN['pagu'], 'tautan' => route('kegiatan.rab', ['kegiatan' => 3, 'sumber' => 'Komite'])],
        ], $status->saran());

        // Tanpa sumber: sumber dana pertama kegiatan (sama dengan SusunanKak).
        $this->assertSame('DIPA', StatusCetak::dari(app(KegiatanRepository::class)->temukan(3), null)->sumber);
        $this->assertSame(['DIPA', 'Komite'], array_map(fn (StatusCetak $s) => $s->sumber, StatusCetak::semua(app(KegiatanRepository::class)->temukan(3))));
        $this->assertSame([null], array_map(fn (StatusCetak $s) => $s->sumber, StatusCetak::semua(['id' => 99, 'nama_kegiatan' => '', 'sumber_dana' => []])));
    }

    public function test_siap_dicetak_dan_melebihi_pagu_tetap_draf_sesudah_disetujui(): void
    {
        $kegiatan = $this->disetujui();

        $dipa = StatusCetak::dari($kegiatan, 'DIPA');
        $this->assertSame([false, null, null, [], 'Siap dicetak'], [$dipa->draf(), $dipa->tandaAir(), $dipa->kakiHalaman(), $dipa->saran(), $dipa->keadaan()]);
        $this->assertSame('KAK-TOR_RAB_Review_RKJM_dan_Penyusunan_RKTM_DIPA_2026.pdf', $dipa->namaBerkas());

        $komite = StatusCetak::dari($kegiatan, 'Komite');
        $this->assertSame([true, ['pagu']], [$komite->draf(), array_column($komite->set->alasan, 'kunci')]);
        $this->assertStringEndsWith('RAB Komite melebihi pagu Rp450.000.', $komite->kakiHalaman());
    }

    public function test_cover_mengikuti_set_dokumen(): void
    {
        $kegiatan = $this->disetujui();
        app(RabRepository::class)->simpanTermin(3, 'DIPA', [['tanggal' => '2026-08-01', 'nominal' => 100_000]]);

        // Termin RAB tidak pas → RAB DRAF walau KAK/TOR-nya tidak → satu set (dan cover-nya) DRAF.
        $status = StatusCetak::dari($kegiatan, 'DIPA');
        $this->assertSame([false, true, true], [SusunanKak::dari($kegiatan, 'DIPA')->draf, SusunanRab::dari($kegiatan, 'DIPA')->draf, $status->draf()]);
        $this->assertEquals(SusunanKak::dari($kegiatan, 'DIPA')->statusDraf, $status->kak);
        $this->assertEquals(SusunanRab::dari($kegiatan, 'DIPA')->statusDraf, $status->rab);
        $this->assertSame(['draf', $status->set->alasan[0]['teks']], [PengambilanBuku::ambil($kegiatan, 'DIPA', 3)['hasil'], PengambilanBuku::ambil($kegiatan, 'DIPA', 3)['pesan']]);
        $this->assertTrue(collect(BukuDokumen::isi([$kegiatan], '1.6', 'DIPA')['baris'])->firstWhere('id', 3)['draf']);

        $html = Livewire::test(PratinjauDokumen::class, ['kegiatan' => 3])->set('sumber', 'DIPA')->html();
        $this->assertStringContainsString('data-testid="keadaan-cetak" data-draf="ya"', $html);
        $this->assertSame(2, substr_count($html, 'data-testid="cap-draf"'), 'Cover & RAB bercap, KAK/TOR tidak.');
    }

    public function test_endpoint_status_cetak(): void
    {
        $this->getJson(route('kegiatan.status_cetak', ['kegiatan' => 3]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('kegiatan_id', 3)
            ->assertJsonPath('draf', true)
            ->assertJsonPath('sumber.0.sumber', 'DIPA')
            ->assertJsonPath('sumber.1.nama_berkas', 'KAK-TOR_RAB_Review_RKJM_dan_Penyusunan_RKTM_KOMITE_2026_DRAF.pdf')
            ->assertJsonPath('sumber.1.alasan.1.kunci', 'pagu')
            ->assertJsonPath('sumber.1.dokumen.rab', ['draf' => true, 'alasan' => ['status', 'pagu']]);

        $this->disetujui();
        $this->getJson(route('kegiatan.status_cetak', ['kegiatan' => 3, 'sumber' => 'DIPA']))
            ->assertJsonPath('draf', false)
            ->assertJsonCount(1, 'sumber')
            ->assertJsonPath('sumber.0.tanda_air', null)
            ->assertJsonPath('sumber.0.kaki_halaman', null);
        $this->getJson(route('kegiatan.status_cetak', ['kegiatan' => 3, 'sumber' => 'BOS']))->assertNotFound();
        $this->getJson(route('kegiatan.status_cetak', ['kegiatan' => 999]))->assertNotFound();

        $lain = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Kegiatan Leading Lain', 'leading' => '1.2', 'user_id' => 2]);
        $this->getJson(route('kegiatan.status_cetak', ['kegiatan' => $lain]))->assertForbidden();
        $this->withSession(['stub_role' => 'admin'])->getJson(route('kegiatan.status_cetak', ['kegiatan' => $lain]))
            ->assertOk()
            ->assertJsonPath('sumber.0.sumber', null)
            ->assertJsonPath('sumber.0.nama_berkas', 'KAK-TOR_RAB_Kegiatan_Leading_Lain_2026_DRAF.pdf');
    }
}
