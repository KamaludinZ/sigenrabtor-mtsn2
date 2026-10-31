<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\DrafDokumenRepository;
use App\Contracts\HasilDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Contracts\RabRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Enums\StatusKegiatan;
use App\Livewire\Kegiatan\SusunDokumen;
use App\Repositories\EloquentDrafDokumenRepository;
use App\Repositories\EloquentHasilDokumenRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentMatrikJadwalRepository;
use App\Repositories\EloquentRabRepository;
use App\Repositories\EloquentTemplateDokumenRepository;
use App\Support\Dokumen\FormatKakTor;
use App\Support\Dokumen\KunciTemplate;
use App\Support\Dokumen\PilihanTemplate;
use App\Support\Dokumen\PilihanTemplateKegiatan;
use App\Support\Kegiatan\AlurStatus;
use App\Support\Pemeriksaan\KeputusanPemeriksaan;
use App\Support\Pengaturan\KelolaTemplateMaster;
use App\Support\Pengaturan\UnggahMasterTemplate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Docx;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class KunciTemplateTest extends TestCase
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

    private function master(string $nama): string
    {
        $berkas = UploadedFile::fake()->createWithContent("{$nama}.docx", Docx::isi(['Kegiatan ${nama_kegiatan}']));

        return (string) UnggahMasterTemplate::simpan(2026, $berkas, $nama, 'KAK+TOR', self::ADMIN, ['leading' => '1.6'])['id'];
    }

    /** Bawa kegiatan melewati langkah alur status [aksi, status tujuan, pelaku] berurutan. */
    private function lewati(int $id, array $langkah): array
    {
        $repo = app(KegiatanRepository::class);
        foreach ($langkah as [$aksi, $ke, $pengguna]) {
            AlurStatus::terapkan($repo->temukan($id), $aksi, $ke, $pengguna, $aksi === 'buka_kunci' ? 'Ganti template.' : null);
        }

        return $repo->temukan($id);
    }

    #[DataProvider('penyimpanan')]
    public function test_template_dibekukan_saat_final_dan_hanya_berubah_sesudah_dibuka_admin(bool $database): void
    {
        $database && $this->pakaiDatabase();
        $repo = app(KegiatanRepository::class);
        $reguler = $this->master('TPM reguler');
        $narasumber = $this->master('TPM narasumber');
        $id = $repo->simpan(['nama_kegiatan' => 'Workshop Penguatan Mutu', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026]);

        // Otomatis (default leading) sampai Final; saat Final template & format yang berlaku dibekukan.
        $kegiatan = $this->lewati($id, [['ajukan', StatusKegiatan::Diperiksa, self::LEADING], ['setujui', StatusKegiatan::Disetujui, self::ADMIN]]);
        $this->assertArrayNotHasKey('template_kak', array_filter($kegiatan, fn ($v) => $v !== null));
        $kegiatan = $this->lewati($id, [['finalkan', StatusKegiatan::Final, self::LEADING]]);
        $this->assertSame([$reguler, 'sembilan_bagian'], [$kegiatan['template_kak'], $kegiatan['format_dokumen']]);
        $riwayat = KunciTemplate::riwayat($kegiatan);
        $this->assertSame(['kunci', 'Dikunci bersama dokumen final', 'Template Word “TPM reguler”, format 9 bagian.', 'Muslimin, M.Pd.', 1],
            [$riwayat[0]['aksi'], $riwayat[0]['label_aksi'], $riwayat[0]['teks'], $riwayat[0]['oleh'], $riwayat[0]['user_id']]);

        // Default leading, status template, dan format tahun berubah → dokumen final tetap memakai yang dibekukan.
        KelolaTemplateMaster::jadikanDefault($narasumber, self::ADMIN);
        KelolaTemplateMaster::ubahStatus($reguler, false, self::ADMIN);
        FormatKakTor::simpanTahun(2026, 'lima_bab', self::ADMIN);
        $this->assertSame(['asal' => 'kegiatan', 'kak' => $reguler], array_intersect_key(PilihanTemplate::untukKegiatan($kegiatan, 2026), ['asal' => 1, 'kak' => 1]));
        $this->assertSame('sembilan_bagian', FormatKakTor::untuk($kegiatan, 2026));

        // Selama Final pilihan tidak bisa diubah.
        $data = PilihanTemplateKegiatan::data($kegiatan);
        $this->assertSame([false, true, 1], [$data['dapat_diubah'], $data['dikunci_final'], count($data['riwayat'])]);
        $this->assertStringStartsWith('Template & format dikunci bersama dokumen final (dikunci ', (string) $data['alasan_terkunci']);
        $this->assertStringEndsWith('— minta admin membuka kunci untuk menggantinya.', (string) $data['alasan_terkunci']);
        try {
            PilihanTemplateKegiatan::simpan($id, ['template' => $narasumber], self::LEADING);
            $this->fail('Pilihan template dokumen final terkunci.');
        } catch (AuthorizationException $e) {
            $this->assertSame($data['alasan_terkunci'], $e->getMessage());
        }

        // Admin membuka kunci → pilihan bisa diubah lagi dan tercatat siapa & kapan.
        KeputusanPemeriksaan::putuskan($id, 'buka_kunci', 'Ganti template narasumber.', self::ADMIN);
        $this->assertSame('dikembalikan', $repo->temukan($id)['status']);
        $hasil = PilihanTemplateKegiatan::simpan($id, ['template' => $narasumber, 'format' => 'lima_bab'], self::LEADING);
        $this->assertSame(['format_dokumen', 'template_kak'], $hasil['diubah']);
        $riwayat = $hasil['data']['riwayat'];
        $this->assertSame(['ubah', 'kunci'], array_column($riwayat, 'aksi'), 'Terbaru dulu.');
        $this->assertSame(['Template Word: “TPM reguler” → “TPM narasumber”; Format: Format 9 bagian → Format 5 bab.', 'Muslimin, M.Pd.'], [$riwayat[0]['teks'], $riwayat[0]['oleh']]);
        $this->assertNotEmpty($riwayat[0]['pada']);
        $this->assertSame([], PilihanTemplateKegiatan::simpan($id, ['template' => $narasumber], self::LEADING)['diubah']);
        $this->assertCount(2, $repo->temukan($id)['riwayat_template'], 'Tanpa perubahan tidak dicatat.');
        PilihanTemplateKegiatan::simpan($id, ['template' => ''], self::LEADING);
        $this->assertSame('Template Word: “TPM narasumber” → otomatis.', KunciTemplate::riwayat($repo->temukan($id))[0]['teks']);
    }

    public function test_tab_susun_menampilkan_kunci_dan_riwayat(): void
    {
        $reguler = $this->master('TPM reguler');
        $repo = app(KegiatanRepository::class);
        $id = $repo->simpan(['nama_kegiatan' => 'Workshop Penguatan Mutu', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026, 'status' => 'disetujui']);
        $this->lewati($id, [['finalkan', StatusKegiatan::Final, self::LEADING]]);

        $this->get(route('kegiatan.template.pilihan', ['kegiatan' => $id]))->assertOk()
            ->assertJson(['dapat_diubah' => false, 'dikunci_final' => true])
            ->assertJsonPath('riwayat.0.aksi', 'kunci')
            ->assertJsonPath('template.dipilih', $reguler);
        $this->putJson(route('kegiatan.template.pilihan', ['kegiatan' => $id]), ['format' => 'lima_bab'])->assertForbidden()
            ->assertJsonPath('pesan', KunciTemplate::alasan($repo->temukan($id)));

        Livewire::test(SusunDokumen::class, ['kegiatan' => $id])
            ->assertSeeHtml('data-testid="template-terkunci"')
            ->assertSee('Template & format dikunci bersama dokumen final')
            ->assertSeeHtml('data-testid="riwayat-template" data-jumlah="1"')
            ->assertSee('Template Word “TPM reguler”, format 9 bagian.')
            ->call('ubahTemplateKak', '')
            ->assertForbidden();
    }

    public function test_riwayat_template_di_database_dijaga_check(): void
    {
        $this->pakaiDatabase();
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Workshop Penguatan Mutu', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026]);
        $entri = ['aksi' => 'ubah', 'pada' => now()->toIso8601String(), 'oleh' => 'A', 'user_id' => 1, 'template_lama' => null, 'template_baru' => null, 'nama_lama' => null, 'nama_baru' => null, 'format_lama' => null, 'format_baru' => 'lima_bab'];
        DB::table('activities')->where('id', $id)->update(['riwayat_template' => json_encode([$entri])]);
        $this->assertSame([$entri], app(KegiatanRepository::class)->temukan($id)['riwayat_template'], 'Urutan kunci tetap (json).');

        foreach ([[], [['aksi' => 'hapus'] + $entri], [['format_baru' => 'tujuh_bab'] + $entri], [array_diff_key($entri, ['oleh' => 1])], array_fill(0, KunciTemplate::BATAS_RIWAYAT + 1, $entri), ['x']] as $salah) {
            try {
                DB::transaction(fn () => DB::table('activities')->where('id', $id)->update(['riwayat_template' => json_encode($salah)]));
                $this->fail('Riwayat template tidak sah ditolak: '.json_encode($salah));
            } catch (QueryException $e) {
                $this->assertStringContainsString('activities_riwayat_template_check', $e->getMessage());
            }
        }
    }
}
