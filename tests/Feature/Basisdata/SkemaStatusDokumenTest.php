<?php

namespace Tests\Feature\Basisdata;

use App\Contracts\DrafDokumenRepository;
use App\Contracts\HasilDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Contracts\RabRepository;
use App\Repositories\EloquentDrafDokumenRepository;
use App\Repositories\EloquentHasilDokumenRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentMatrikJadwalRepository;
use App\Repositories\EloquentRabRepository;
use App\Support\Dokumen\DrafDokumen;
use App\Support\Dokumen\PembuatDokumen;
use App\Support\Dokumen\StatusDokumen;
use App\Support\Kegiatan\AlurStatus;
use App\Support\Stub\StubKegiatanRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SkemaStatusDokumenTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_29_000040_sinkron_status_dokumen.php';

    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    protected function setUp(): void
    {
        parent::setUp();
        RujukanDb::siapkan();
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(HasilDokumenRepository::class, EloquentHasilDokumenRepository::class);
        $this->app->singleton(DrafDokumenRepository::class, EloquentDrafDokumenRepository::class);
        $this->app->singleton(RabRepository::class, EloquentRabRepository::class);
        $this->app->singleton(MatrikJadwalRepository::class, EloquentMatrikJadwalRepository::class);
    }

    /** Ditolak database — constraint tertunda status dokumen diperiksa seketika di savepoint. */
    private function ditolak(callable $aksi, string $pesan): void
    {
        try {
            DB::transaction(function () use ($aksi) {
                $aksi();
                DB::statement('SET CONSTRAINTS documents_status_sesuai_kegiatan IMMEDIATE');
            });
            $this->fail("Seharusnya ditolak: {$pesan}");
        } catch (QueryException $e) {
            $this->assertStringContainsString($pesan, $e->getMessage());
        } finally {
            DB::statement('SET CONSTRAINTS documents_status_sesuai_kegiatan DEFERRED');
        }
    }

    /** Kegiatan lengkap (isian contoh kegiatan 1) yang bisa diajukan. */
    private function kegiatanLengkap(): int
    {
        $data = StubKegiatanRepository::contohSemua()[1];
        unset($data['id'], $data['riwayat_status'], $data['data_pagu_id'], $data['duplikat_dari'], $data['diperbarui_pada']);

        return app(KegiatanRepository::class)->simpan(['user_id' => 1, 'status' => 'draf'] + $data);
    }

    /** @return list<array{0: string, 1: bool}> status & cap DRAF tiap baris documents kegiatan. */
    private function dokumen(int $id): array
    {
        return DB::table('documents')->where('activity_id', $id)->orderBy('id')->get()->map(fn (object $d) => [$d->status, (bool) $d->bertanda_draf])->all();
    }

    public function test_trigger_dokumen_ikut_status_kegiatan(): void
    {
        $id = $this->kegiatanLengkap();
        PembuatDokumen::susun($id, 'RAB', 'Komite', self::LEADING);
        DrafDokumen::simpan($id, 'lengkap', null, self::LEADING);
        $this->assertSame([['draf', true], ['draf', true]], $this->dokumen($id));

        // Status berubah langsung di database (tanpa aplikasi): dokumen ikut, cap DRAF dipertahankan.
        DB::table('activities')->where('id', $id)->update(['status' => 'diperiksa']);
        $this->assertSame([['diperiksa', true], ['diperiksa', true]], $this->dokumen($id));
        DB::table('activities')->where('id', $id)->update(['status' => 'disetujui']);
        $this->assertSame([['disetujui', true], ['disetujui', true]], $this->dokumen($id), 'Cap sesudah Disetujui dihitung aplikasi.');
        DB::table('documents')->where('activity_id', $id)->update(['bertanda_draf' => false]);
        DB::table('activities')->where('id', $id)->update(['status' => 'dikembalikan']);
        $this->assertSame([['dikembalikan', true], ['dikembalikan', true]], $this->dokumen($id), 'Kembali ke status ber-DRAF: cap dipasang lagi.');

        DB::statement('SET CONSTRAINTS documents_status_sesuai_kegiatan IMMEDIATE');
        DB::statement('SET CONSTRAINTS documents_status_sesuai_kegiatan DEFERRED');
    }

    public function test_status_dokumen_wajib_sama_dengan_kegiatan_dan_cap_draf(): void
    {
        $id = $this->kegiatanLengkap();
        $dokumen = fn (array $isi) => DB::table('documents')->insertGetId($isi + ['activity_id' => $id, 'tahun_anggaran' => 2026, 'jenis' => 'KAK+TOR']);

        $this->ditolak(fn () => $dokumen(['status' => 'final']), 'documents_status_sesuai_kegiatan');
        $this->ditolak(fn () => $dokumen(['status' => 'draf', 'bertanda_draf' => false]), 'documents_cap_draf_check');
        $sah = $dokumen([]);
        $this->ditolak(fn () => DB::table('documents')->where('id', $sah)->update(['status' => 'diperiksa']), 'documents_status_sesuai_kegiatan');
        $this->assertSame([['draf', true]], $this->dokumen($id));
    }

    public function test_alur_status_menyamakan_dokumen_di_database(): void
    {
        $id = $this->kegiatanLengkap();
        PembuatDokumen::susun($id, 'lengkap', null, self::LEADING);
        DrafDokumen::simpan($id, 'RAB', 'Komite', self::LEADING);

        $this->travel(1)->seconds();
        $ajukan = AlurStatus::ubah($id, 'ajukan', null, self::LEADING);
        $this->assertSame(['hasil' => 1, 'draf' => 1, 'gagal' => null], $ajukan['dokumen']);
        $this->assertSame([['diperiksa', true], ['diperiksa', true]], $this->dokumen($id));
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::statement('SET CONSTRAINTS ALL DEFERRED');

        $this->travel(1)->seconds();
        AlurStatus::ubah($id, 'setujui', null, self::ADMIN);
        $hasil = app(HasilDokumenRepository::class)->temukan($id, 'lengkap', 'Komite');
        $this->assertSame(['disetujui', 'Koordinator PUSKOM'], [$hasil['status'], $hasil['oleh']]);
        $this->assertSame($hasil['alasan_draf'] !== [], $hasil['bertanda_draf']);
        $this->assertSame('disetujui', app(DrafDokumenRepository::class)->temukan($id, 'RAB', 'Komite')['status']);
        $this->assertTrue(StatusDokumen::untuk(app(KegiatanRepository::class)->temukan($id))['sinkron']);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::statement('SET CONSTRAINTS ALL DEFERRED');
    }

    public function test_migrasi_menyamakan_data_lama_dan_bisa_dibatalkan(): void
    {
        $id = $this->kegiatanLengkap();
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertNull(DB::selectOne("SELECT to_regproc('dokumen_ikut_status_kegiatan') AS f")->f);
        $this->assertSame(0, DB::table('pg_constraint')->where('conname', 'documents_cap_draf_check')->count());
        // Dokumen lama yang tidak sejalan dengan kegiatannya.
        DB::table('documents')->insert(['activity_id' => $id, 'tahun_anggaran' => 2026, 'jenis' => 'KAK+TOR', 'status' => 'disetujui', 'bertanda_draf' => false]);

        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertSame([['draf', true]], $this->dokumen($id));
        $this->assertSame(1, DB::table('pg_constraint')->where('conname', 'documents_cap_draf_check')->count());
        DB::table('activities')->where('id', $id)->update(['status' => 'diperiksa']);
        $this->assertSame([['diperiksa', true]], $this->dokumen($id));
    }
}
