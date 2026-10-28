<?php

namespace Tests\Feature\Kegiatan;

use App\Repositories\EloquentKegiatanRepository;
use App\Support\Kegiatan\DataPelaksanaan;
use App\Support\Stub\StubKegiatanRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SkemaPelaksanaanTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_28_000010_add_pelaksanaan_columns_to_activities_table.php';

    private const VIEW_MONITORING = 'database/migrations/2026_10_28_000022_create_monitoring_views.php';

    /** @param  array<string, mixed>  $data */
    private function kegiatan(array $data = []): int
    {
        return DB::table('activities')->insertGetId($data + ['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Workshop Kurikulum']);
    }

    private function ditolak(callable $aksi): void
    {
        try {
            DB::transaction($aksi);
        } catch (QueryException) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail('Seharusnya ditolak database.');
    }

    private function selesai(int $id): ?string
    {
        return DB::table('activities')->where('id', $id)->value('tanggal_selesai');
    }

    public function test_batasan_jadwal_dan_bulan_pelaksanaan(): void
    {
        $this->kegiatan(['tanggal_mulai' => '2026-07-14', 'tanggal_selesai' => '2026-07-16', 'bulan_pelaksanaan' => '[1, 7, 12]']);
        $this->ditolak(fn () => $this->kegiatan(['tanggal_mulai' => '2026-07-14', 'tanggal_selesai' => '2026-07-13']));
        $this->ditolak(fn () => $this->kegiatan(['tanggal_selesai' => '2026-07-13']));
        foreach (['[0]', '[13]', '["7"]', '[7.5]', '[{"bulan": 7}]'] as $bulan) {
            $this->ditolak(fn () => $this->kegiatan(['bulan_pelaksanaan' => $bulan]));
        }

        // Cari kegiatan yang berlangsung di bulan tertentu (indeks GIN).
        $this->assertSame(1, DB::table('activities')->whereJsonContains('bulan_pelaksanaan', 7)->count());
    }

    public function test_repositori_menyimpan_salinan_tanggal_selesai(): void
    {
        $repo = new EloquentKegiatanRepository;
        $id = $repo->simpan(Arr::except(StubKegiatanRepository::contohDuaSumber(), ['id', 'status']));
        $this->assertSame(DataPelaksanaan::dari(StubKegiatanRepository::contohDuaSumber())->selesai()->toDateString(), $this->selesai($id));
        $this->assertArrayNotHasKey('tanggal_selesai', $repo->temukan($id), 'Bentuk data tetap sama dengan tiruan.');

        // Selasa 14 Juli 2026, 3 hari kerja → Kamis 16 Juli; 5 hari kerja melewati akhir pekan → Senin 20 Juli.
        $repo->simpan(['tanggal_mulai' => '2026-07-14', 'lama_hari' => 3, 'hari_kerja' => true], $id);
        $this->assertSame('2026-07-16', $this->selesai($id));
        $repo->simpan(['lama_hari' => 5], $id);
        $this->assertSame('2026-07-20', $this->selesai($id));
        $repo->simpan(['tanggal_mulai' => null], $id);
        $this->assertNull($this->selesai($id));
    }

    public function test_migrasi_mengisi_tanggal_selesai_kegiatan_lama(): void
    {
        // ALTER TABLE activities ditolak selama ada pemeriksaan rujukan tertunda: periksa seketika.
        RujukanDb::segera();
        // View monitoring memakai tanggal_selesai: lepas dulu, pasang lagi sesudahnya.
        $this->artisan('migrate:rollback', ['--path' => self::VIEW_MONITORING])->assertSuccessful();
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        $lama = $this->kegiatan(['tanggal_mulai' => '2026-11-16', 'lama_hari' => 2, 'hari_kerja' => true]);
        $tanpaJadwal = $this->kegiatan(['lama_hari' => 2]);

        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->artisan('migrate', ['--path' => self::VIEW_MONITORING])->assertSuccessful();

        $this->assertSame(['2026-11-17', null], [$this->selesai($lama), $this->selesai($tanpaJadwal)]);
    }
}
