<?php

namespace Tests\Feature\Basisdata;

use App\Contracts\KegiatanRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Support\Dokumen\SuntingDokumen;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SkemaDrafSuntingTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_29_000042_create_draf_sunting_dokumen_table.php';

    protected function setUp(): void
    {
        parent::setUp();
        RujukanDb::siapkan();
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
    }

    private function ditolak(callable $aksi, string $pesan): void
    {
        try {
            DB::transaction($aksi);
            $this->fail("Seharusnya ditolak: {$pesan}");
        } catch (QueryException $e) {
            $this->assertStringContainsString($pesan, $e->getMessage());
        }
    }

    /** @param  array<string, mixed>  $isi */
    private function draf(int $kegiatan, array $isi = []): int
    {
        return DB::table('draf_sunting_dokumen')->insertGetId($isi + ['activity_id' => $kegiatan, 'user_id' => 1, 'bidang' => 'tempat', 'teks' => 'Aula Utama ', 'teks_awal' => 'Ruang Rapat']);
    }

    public function test_draf_per_kegiatan_akun_bidang(): void
    {
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Rapat Kerja Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026]);
        $this->draf($id);
        $this->draf($id, ['user_id' => 2]);
        $this->draf($id, ['bidang' => 'persiapan', 'teks' => '', 'teks_awal' => null]);

        $this->ditolak(fn () => $this->draf($id), 'draf_sunting_dokumen_activity_id_user_id_bidang_unique');
        $this->ditolak(fn () => $this->draf($id, ['bidang' => 'nomor_dokumen']), 'draf_sunting_isian_check');
        $this->ditolak(fn () => $this->draf($id, ['user_id' => 3, 'teks' => str_repeat('a', SuntingDokumen::maksDraf('tempat') + 1)]), 'draf_sunting_isian_check');
        $this->ditolak(fn () => $this->draf($id, ['bidang' => 'sasaran', 'teks_awal' => ' Ruang']), 'draf_sunting_isian_check');
        $this->ditolak(fn () => $this->draf($id, ['bidang' => 'pengarah', 'dibuat_pada' => '2026-10-29 10:00:00+07', 'diperbarui_pada' => '2026-10-29 09:00:00+07']), 'draf_sunting_isian_check');

        DB::table('activities')->where('id', $id)->delete();
        $this->assertSame(0, DB::table('draf_sunting_dokumen')->count(), 'Ikut terhapus bersama kegiatannya.');
    }

    public function test_migrasi_bisa_dibatalkan(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('draf_sunting_dokumen'));
        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('draf_sunting_dokumen'));
    }
}
