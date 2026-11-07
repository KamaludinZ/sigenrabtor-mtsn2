<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\PejabatRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Livewire\Pengaturan\ProfilMadrasah;
use App\Repositories\EloquentPejabatRepository;
use App\Support\Dokumen\ChecklistIsian;
use App\Support\Dokumen\NilaiVariabel;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\TanggalDokumen;
use App\Support\Pengaturan\KelolaPejabat;
use App\Support\Pengaturan\TanggalPengesahan;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class TanggalDokumenLeadingTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** Kegiatan contoh 1 (leading 1.6) dengan tanggal dokumen sendiri dikosongkan. */
    private function kegiatanTanpaTanggal(): array
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        app(KegiatanRepository::class)->simpan(['tanggal_dokumen' => null] + $kegiatan, 1);

        return app(KegiatanRepository::class)->temukan(1);
    }

    public function test_tanggal_kegiatan_sendiri_lebih_dulu_lalu_tanggal_leading(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        $this->assertSame('1.6', $kegiatan['leading']);
        $this->assertSame(['tanggal' => $kegiatan['tanggal_dokumen'], 'asal' => 'kegiatan', 'leading' => null], TanggalPengesahan::untuk($kegiatan, 2026));

        $kosong = ['tanggal_dokumen' => null] + $kegiatan;
        $this->assertSame(['tanggal' => null, 'asal' => null, 'leading' => null], TanggalPengesahan::untuk($kosong, 2026));

        KelolaPejabat::simpanTanggalDokumen(2026, '1.6', '2026-01-05', self::ADMIN);
        $this->assertSame(['tanggal' => '2026-01-05', 'asal' => 'leading', 'leading' => '1.6'], TanggalPengesahan::untuk($kosong, 2026));
        $this->assertSame($kegiatan['tanggal_dokumen'], TanggalPengesahan::tanggal($kegiatan, 2026), 'Tanggal kegiatan tetap menang.');
        $this->assertNull(TanggalPengesahan::tanggal($kosong, 2025), 'Tanggal leading per tahun.');
    }

    public function test_lembar_pengesahan_checklist_dan_variabel_memakai_tanggal_leading(): void
    {
        KelolaPejabat::simpanTanggalDokumen(2026, '1.6', '2026-01-05', self::ADMIN);
        $kegiatan = $this->kegiatanTanpaTanggal();
        $kota = StubData::madrasah()['kota'];

        $kurang = [];
        $pengesahan = SusunanKak::pengesahan($kegiatan, null, $kota, function (string $apa) use (&$kurang) {
            $kurang[] = $apa;
        }, 2026);
        $this->assertContains($kota.', 5 Januari 2026', array_column($pengesahan['kolom'], 'tempat_tanggal'));
        $this->assertNotContains('tanggal dokumen', $kurang);

        $this->assertSame('5 Januari 2026', NilaiVariabel::untuk($kegiatan, null)['tanggal_dokumen']);
        $teks = array_column(TanggalDokumen::dari($kegiatan, null, 2026), 'teks', 'kunci');
        $this->assertSame($kota.', 5 Januari 2026', $teks['kak-pengesahan']);

        $butir = collect(ChecklistIsian::untuk($kegiatan))->firstWhere('kunci', 'identitas')['butir'];
        $tanggal = collect($butir)->firstWhere('kunci', 'tanggal_dokumen');
        $this->assertTrue($tanggal['terisi']);
        $this->assertSame('Memakai tanggal dokumen leading 1.6: 5 Januari 2026.', $tanggal['keterangan']);

        // Tanpa tanggal leading: penanda kosong seperti sebelumnya.
        KelolaPejabat::simpanTanggalDokumen(2026, '1.6', null, self::ADMIN);
        $kurang = [];
        SusunanKak::pengesahan($kegiatan, null, $kota, function (string $apa) use (&$kurang) {
            $kurang[] = $apa;
        }, 2026);
        $this->assertContains('tanggal dokumen', $kurang);
    }

    public function test_simpan_tanggal_leading_memeriksa_isian_dan_mencatat_riwayat(): void
    {
        $this->kegiatanTanpaTanggal();
        $hasil = KelolaPejabat::simpanTanggalDokumen(2026, '1.6', '2026-01-05', self::ADMIN);
        $this->assertSame(['tanggal' => '2026-01-05', 'berubah' => true, 'kegiatan' => 1], $hasil);
        $riwayat = app(RiwayatPengaturanRepository::class)->semua()[0];
        $this->assertSame(['ubah_tanggal_dokumen', 'Tanggal dokumen Pejabat 1.6 Tim Penjamin Mutu TA 2026: — → 5 Januari 2026'], [$riwayat['aksi'], $riwayat['keterangan']]);
        $this->assertFalse(KelolaPejabat::simpanTanggalDokumen(2026, '1.6', '2026-01-05', self::ADMIN)['berubah']);

        $galat = function (string $kode, ?string $tanggal): array {
            try {
                KelolaPejabat::simpanTanggalDokumen(2026, $kode, $tanggal, self::ADMIN);
            } catch (ValidationException $e) {
                return array_map(fn (array $p) => $p[0], $e->errors());
            }
            $this->fail('Seharusnya ditolak.');
        };
        $this->assertSame(['tanggal' => 'Tanggal dokumen harus antara 1 Januari 2025 dan 31 Desember 2026.'], $galat('1.1', '2027-01-02'));
        $this->assertSame(['tanggal' => 'Tanggal dokumen tidak valid.'], $galat('1.1', '2026-02-30'));
        $this->assertSame(['leading' => 'Leading tidak dikenal atau nonaktif.'], $galat('1.9', '2026-01-05'));
        $this->assertThrows(fn () => KelolaPejabat::simpanTanggalDokumen(2025, '1.1', '2025-01-05', self::ADMIN), AuthorizationException::class);
        $this->assertThrows(fn () => KelolaPejabat::simpanTanggalDokumen(2026, '1.1', '2026-01-05', ['role' => 'leading']), AuthorizationException::class);

        $this->assertSame(7, KelolaPejabat::simpanTanggalSemua(2026, '2026-01-02', self::ADMIN), 'Hanya leading yang masih kosong.');
        $this->assertSame('2026-01-05', app(PejabatRepository::class)->tanggalDokumen(2026)['1.6']);
        $this->assertSame(8, KelolaPejabat::simpanTanggalSemua(2026, '2026-01-03', self::ADMIN, timpa: true));
        $this->assertSame(['tanggal' => '2026-01-03', 'teks' => '3 Januari 2026'], KelolaPejabat::data(2026)['tanggal_dokumen']['1.1']);
    }

    public function test_tab_pejabat_mengatur_tanggal_per_leading(): void
    {
        session(['stub_role' => 'admin']);
        $this->kegiatanTanpaTanggal();

        Livewire::withQueryParams(['tab' => 'pejabat'])->test(ProfilMadrasah::class)
            ->assertSeeHtml('data-testid="tanggal-dokumen-leading"')
            ->assertSeeHtml('data-testid="tanggal-dokumen-1.6"')
            ->assertSee('Belum diatur — memakai tanggal dokumen tiap kegiatan')
            ->call('simpanTanggalDokumen', '1.6', '2026-01-05')
            ->assertDispatched('notify', message: 'Tanggal dokumen 1.6 Tim Penjamin Mutu: 5 Januari 2026 — dipakai 1 kegiatan yang tanggal dokumennya kosong.')
            ->assertSeeHtml('data-tanggal-leading="1.6" data-tanggal="2026-01-05"')
            ->call('simpanTanggalDokumen', '1.1', '2030-01-01')->assertHasErrors(['tanggal_dokumen_1.1'])
            ->set('tanggalSemua', '2026-01-02')->call('isiTanggalSemua')
            ->assertDispatched('notify', message: 'Tanggal dokumen diisikan untuk 7 leading.');

        $this->get(route('kegiatan.edit', ['kegiatan' => 1]))->assertOk()->assertSee('lembar pengesahan memakai tanggal dokumen leading: 5 Januari 2026');
        $this->assertSame('2 Januari 2026', $this->getJson(route('pengaturan.madrasah.pejabat'))->assertOk()->json('tanggal_dokumen')['1.1']['teks']);

        Livewire::withQueryParams(['tab' => 'pejabat'])->test(ProfilMadrasah::class)
            ->call('simpanTanggalDokumen', '1.6', '')
            ->assertSeeHtml('data-tanggal-leading="1.6" data-tanggal=""');
    }

    public function test_repositori_database_setara_tiruan(): void
    {
        RujukanDb::siapkan();
        $repo = new EloquentPejabatRepository;
        $repo->simpanTanggalDokumen(2026, '1.6', '2026-01-05');
        $repo->simpanTanggalDokumen(2026, '1.2', '2026-01-04');
        $repo->simpanTanggalDokumen(2026, '1.2', '2026-01-06');
        $this->assertSame(['1.2' => '2026-01-06', '1.6' => '2026-01-05'], $repo->tanggalDokumen(2026));
        $repo->simpanTanggalDokumen(2026, '1.2', null);
        $this->assertSame(['1.6' => '2026-01-05'], $repo->tanggalDokumen(2026));
        $this->assertSame([], $repo->tanggalDokumen(2025));

        $this->expectException(QueryException::class);
        DB::transaction(function () use ($repo) {
            $repo->simpanTanggalDokumen(2026, '1.1', '2028-01-01');
        });
    }
}
