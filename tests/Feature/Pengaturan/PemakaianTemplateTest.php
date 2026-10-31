<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\DrafDokumenRepository;
use App\Contracts\HasilDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Contracts\RabRepository;
use App\Contracts\RiwayatDokumenRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Livewire\Pengaturan\TemplateDokumen;
use App\Repositories\EloquentDrafDokumenRepository;
use App\Repositories\EloquentHasilDokumenRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentMatrikJadwalRepository;
use App\Repositories\EloquentRabRepository;
use App\Repositories\EloquentTemplateDokumenRepository;
use App\Support\Dokumen\CatatRiwayatDokumen;
use App\Support\Dokumen\DaftarTemplateMaster;
use App\Support\Dokumen\PemakaianTemplate;
use App\Support\Dokumen\PembuatDokumen;
use App\Support\Pengaturan\TemplateMasihDipakai;
use App\Support\Pengaturan\UnggahMasterTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Docx;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class PemakaianTemplateTest extends TestCase
{
    use RefreshDatabase;

    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    private function pakaiDatabase(): void
    {
        RujukanDb::siapkan();
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(HasilDokumenRepository::class, EloquentHasilDokumenRepository::class);
        $this->app->singleton(DrafDokumenRepository::class, EloquentDrafDokumenRepository::class);
        $this->app->singleton(RabRepository::class, EloquentRabRepository::class);
        $this->app->singleton(MatrikJadwalRepository::class, EloquentMatrikJadwalRepository::class);
        $this->app->singleton(TemplateDokumenRepository::class, EloquentTemplateDokumenRepository::class);
    }

    private function master(string $nama, ?string $leading): string
    {
        $berkas = UploadedFile::fake()->createWithContent("{$nama}.docx", Docx::isi(['Kegiatan ${nama_kegiatan}']));

        return (string) UnggahMasterTemplate::simpan(2026, $berkas, $nama, 'KAK+TOR', self::ADMIN, ['leading' => $leading])['id'];
    }

    #[DataProvider('penyimpanan')]
    public function test_template_yang_dipakai_dokumen_tidak_bisa_dihapus(bool $database): void
    {
        $database && $this->pakaiDatabase();
        $tpm = $this->master('TPM reguler', '1.6');
        $umum = $this->master('Umum reguler', null);
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Rapat Kerja Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026, 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 3_000_000]]]);
        $this->assertSame(['kegiatan' => [], 'dokumen' => [], 'boleh_dihapus' => true], PemakaianTemplate::untuk($tpm), 'Berlaku sebagai default leading saja belum dipakai dokumen.');

        // Hasil susunan mencatat master yang berlaku; RAB tidak memakai master KAK/TOR.
        $this->assertSame($tpm, PembuatDokumen::susun($id, 'KAK+TOR', null, self::LEADING)['dokumen']['template']);
        $this->assertNull(PembuatDokumen::susun($id, 'RAB', 'Komite', self::LEADING)['dokumen']['template']);
        $this->assertSame([$tpm], array_column(app(HasilDokumenRepository::class)->memakaiTemplate([$tpm, $umum]), 'template'));

        // Riwayat Dokumen (cetak/unduh/arsip) juga mencatatnya.
        $kegiatan = app(KegiatanRepository::class)->temukan($id);
        $this->assertSame($tpm, CatatRiwayatDokumen::catat($kegiatan, 'lengkap', 'Komite', 'pdf', 'cetak', self::LEADING)['template']);
        $this->assertNull(CatatRiwayatDokumen::catat($kegiatan, 'RAB', 'Komite', 'xlsx', 'unduh', self::LEADING)['template']);

        $pemakaian = PemakaianTemplate::untuk($tpm);
        $this->assertFalse($pemakaian['boleh_dihapus']);
        $this->assertSame([['hasil', 'KAK+TOR', 'Hasil susunan KAK/TOR (gabungan) Komite'], ['riwayat', 'lengkap', 'Dicetak KAK-TOR_RAB_Rapat_Kerja_Madrasah_KOMITE_2026_DRAF.pdf']],
            array_map(fn (array $d) => [$d['asal'], $d['jenis'], $d['keterangan']], $pemakaian['dokumen']));
        $this->assertSame([$tpm => ['kegiatan' => 0, 'dokumen' => 2], $umum => ['kegiatan' => 0, 'dokumen' => 0]], PemakaianTemplate::jumlah([$tpm, $umum]));

        try {
            UnggahMasterTemplate::hapus($tpm, self::ADMIN);
            $this->fail('Template yang dipakai dokumen tidak boleh dihapus.');
        } catch (TemplateMasihDipakai $e) {
            $this->assertSame('Template tidak bisa dihapus — 2 dokumen tercatat memakainya. Nonaktifkan saja agar tidak muncul di pilihan baru; dokumen lama tetap utuh.', $e->getMessage());
            $this->assertCount(2, $e->dokumen);
        }
        $this->assertNotNull(app(TemplateDokumenRepository::class)->temukan($tpm));

        // Dipilih kegiatan & dipakai dokumen disebut keduanya; template tanpa pemakaian tetap bisa dihapus.
        app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Rapat Mutu', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026, 'template_kak' => $tpm]);
        $this->assertSame('Template tidak bisa dihapus — 1 kegiatan masih memilihnya dan 2 dokumen tercatat memakainya. Nonaktifkan saja agar tidak muncul di pilihan baru; dokumen lama tetap utuh.',
            (new TemplateMasihDipakai(...array_values(array_intersect_key(PemakaianTemplate::untuk($tpm), ['kegiatan' => 1, 'dokumen' => 1]))))->getMessage());
        $this->assertSame('Umum reguler', UnggahMasterTemplate::hapus($umum, self::ADMIN)['nama']);
    }

    public function test_daftar_api_dan_tab_kak_menandai_template_terpakai(): void
    {
        $tpm = $this->master('TPM reguler', '1.6');
        $umum = $this->master('Umum reguler', null);
        $kegiatan = app(KegiatanRepository::class)->temukan(app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Rapat Kerja Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026]));
        CatatRiwayatDokumen::catat($kegiatan, 'KAK+TOR', null, 'docx', 'unduh', self::LEADING);
        $this->assertSame($tpm, collect(app(RiwayatDokumenRepository::class)->semua())->firstWhere('kegiatan_id', $kegiatan['id'])['template']);

        $baris = collect(DaftarTemplateMaster::semua(2026))->keyBy('id');
        $this->assertSame([1, false], [$baris[$tpm]['dokumen'], $baris[$tpm]['boleh_dihapus']]);
        $this->assertSame([0, true], [$baris[$umum]['dokumen'], $baris[$umum]['boleh_dihapus']]);

        $this->withSession(['stub_role' => 'admin']);
        $this->deleteJson(route('pengaturan.template.master.hapus', $tpm))->assertStatus(409)
            ->assertJsonPath('kegiatan', [])
            ->assertJsonPath('dokumen.0.asal', 'riwayat')
            ->assertJsonPath('dokumen.0.kegiatan_id', $kegiatan['id']);
        $this->getJson(route('pengaturan.template.master.data'))->assertOk();

        Livewire::test(TemplateDokumen::class, ['tab' => 'kak'])
            ->assertSeeHtml('data-testid="hapus-master-terkunci"')
            ->assertSee('Tidak bisa dihapus — 1 dokumen memakainya. Nonaktifkan di Template Master.')
            ->assertSeeHtml('aria-label="Hapus Umum reguler"')
            ->call('mintaHapusMaster', $tpm)
            ->call('hapusMaster')
            ->assertDispatched('notify', type: 'error');
        $this->assertNotNull(app(TemplateDokumenRepository::class)->temukan($tpm));
    }
}
