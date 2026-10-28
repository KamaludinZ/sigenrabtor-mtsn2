<?php

namespace Tests\Feature\Basisdata;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SkemaSaranAiTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_28_000036_create_ai_suggestions_tables.php';

    private int $kegiatan;

    protected function setUp(): void
    {
        parent::setUp();
        RujukanDb::siapkan();
        $this->kegiatan = DB::table('activities')->insertGetId(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Workshop Kurikulum', 'kode_leading' => '1.6']);
    }

    private function ditolak(callable $aksi, string $batasan): void
    {
        try {
            DB::transaction($aksi);
            $this->fail("{$batasan} seharusnya menolak.");
        } catch (QueryException $e) {
            $this->assertStringContainsString($batasan, $e->getMessage());
        }
    }

    /** @param  array<string, mixed>  $isi */
    private function saran(array $isi = []): string
    {
        DB::table('ai_suggestions')->insert($isi + ['id' => $id = (string) Str::uuid(), 'activity_id' => $this->kegiatan, 'bagian' => 'tujuan', 'prompt_terkirim' => 'Kegiatan: Workshop Kurikulum', 'user_id' => 1]);

        return $id;
    }

    /** @param  array<string, mixed>  $isi */
    private function versi(string $saran, int $urutan, array $isi = []): void
    {
        DB::table('ai_suggestion_versions')->insert($isi + ['suggestion_id' => $saran, 'urutan' => $urutan, 'judul' => "Versi {$urutan}", 'teks' => "Teks saran {$urutan}."]);
    }

    public function test_permintaan_dan_versi_saran(): void
    {
        $id = $this->saran(['provider' => 'tiruan', 'instruksi' => 'lebih singkat']);
        $this->versi($id, 1, ['status' => 'diedit', 'teks_dipakai' => 'Teks saran 1 yang disunting.']);
        $this->versi($id, 2, ['status' => 'ditolak']);
        $this->versi($id, 3, ['status' => 'ditolak']);

        $this->ditolak(fn () => $this->saran(['bagian' => 'anggaran']), 'ai_suggestions_isian_check');
        $this->ditolak(fn () => $this->saran(['provider' => 'chatgpt']), 'ai_suggestions_isian_check');
        $this->ditolak(fn () => $this->saran(['prompt_terkirim' => ' ']), 'ai_suggestions_isian_check');
        $this->ditolak(fn () => $this->saran(['instruksi' => str_repeat('a', 301)]), 'ai_suggestions_isian_check');
        $this->ditolak(fn () => $this->saran(['dibuat_pada' => '2026-03-02 10:00:00+07', 'diputuskan_pada' => '2026-03-01 10:00:00+07']), 'ai_suggestions_isian_check');
        $this->ditolak(fn () => $this->saran(['activity_id' => 999999]), 'ai_suggestions_activity_id_foreign');

        $lain = $this->saran(['bagian' => 'jadwal']);
        $this->ditolak(fn () => $this->versi($id, 1), 'ai_suggestion_versions_suggestion_id_urutan_unique');
        $this->ditolak(fn () => $this->versi($lain, 4), 'ai_suggestion_versions_isian_check');
        $this->ditolak(fn () => $this->versi($lain, 1, ['status' => 'diterima']), 'ai_suggestion_versions_isian_check');
        $this->ditolak(fn () => $this->versi($lain, 1, ['status' => 'diterima', 'teks_dipakai' => 'Beda.']), 'ai_suggestion_versions_isian_check');
        $this->ditolak(fn () => $this->versi($lain, 1, ['status' => 'diedit', 'teks_dipakai' => 'Teks saran 1.']), 'ai_suggestion_versions_isian_check');
        $this->ditolak(fn () => $this->versi($lain, 1, ['status' => 'menunggu', 'teks_dipakai' => 'Teks saran 1.']), 'ai_suggestion_versions_isian_check');
        $this->versi($lain, 1, ['status' => 'diterima', 'teks_dipakai' => 'Teks saran 1.']);
        $this->ditolak(fn () => $this->versi($lain, 2, ['status' => 'diterima', 'teks_dipakai' => 'Teks saran 2.']), 'ai_suggestion_versions_satu_dipakai');

        // Akun dihapus → peminta kosong; kegiatan dihapus → permintaan & versinya ikut terhapus.
        DB::table('users')->insert(['id' => 98, 'name' => 'Sementara', 'email' => 'sementara@example.com', 'password' => '-', 'role' => 'admin', 'is_active' => true]);
        $olehSementara = $this->saran(['user_id' => 98]);
        DB::table('users')->where('id', 98)->delete();
        $this->assertNull(DB::table('ai_suggestions')->where('id', $olehSementara)->value('user_id'));
        DB::table('activities')->where('id', $this->kegiatan)->delete();
        $this->assertSame([0, 0], [DB::table('ai_suggestions')->count(), DB::table('ai_suggestion_versions')->count()]);
    }

    public function test_pemakaian_ai_per_akun_tanggal_fitur(): void
    {
        DB::table('ai_usage')->insert(['user_id' => 1, 'tanggal' => '2026-10-28', 'fitur' => 'tujuan', 'jumlah' => 2]);
        DB::table('ai_usage')->insert(['user_id' => 1, 'tanggal' => '2026-10-28', 'fitur' => 'usul_jadwal']);
        $this->ditolak(fn () => DB::table('ai_usage')->insert(['user_id' => 1, 'tanggal' => '2026-10-28', 'fitur' => 'tujuan']), 'ai_usage_user_id_tanggal_fitur_unique');
        $this->ditolak(fn () => DB::table('ai_usage')->insert(['user_id' => 1, 'tanggal' => '2026-10-28', 'fitur' => 'anggaran']), 'ai_usage_isian_check');
        $this->ditolak(fn () => DB::table('ai_usage')->insert(['user_id' => 1, 'tanggal' => '2026-10-29', 'fitur' => 'tujuan', 'jumlah' => 0]), 'ai_usage_isian_check');
        $this->assertSame(3, (int) DB::table('ai_usage')->where(['user_id' => 1, 'tanggal' => '2026-10-28'])->sum('jumlah'));
    }

    public function test_migrasi_bisa_dibatalkan(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('ai_suggestions'));
        $this->assertFalse(Schema::hasTable('ai_usage'));
        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('ai_suggestion_versions'));
    }
}
