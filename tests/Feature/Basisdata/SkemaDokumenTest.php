<?php

namespace Tests\Feature\Basisdata;

use App\Contracts\KegiatanRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Support\Dokumen\DokumenKegiatan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SkemaDokumenTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_28_000032_create_documents_tables.php';

    protected function setUp(): void
    {
        parent::setUp();
        RujukanDb::siapkan();
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
    }

    /** Aksi ditolak database — constraint tertunda diperiksa seketika lewat SET CONSTRAINTS di savepoint. */
    private function ditolak(callable $aksi, string $pesan): void
    {
        try {
            DB::transaction(function () use ($aksi) {
                $aksi();
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            });
            $this->fail("Seharusnya ditolak: {$pesan}");
        } catch (QueryException $e) {
            $this->assertStringContainsString($pesan, $e->getMessage());
        } finally {
            DB::statement('SET CONSTRAINTS ALL DEFERRED');
        }
    }

    private function sah(callable $aksi): void
    {
        DB::transaction(function () use ($aksi) {
            $aksi();
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });
        DB::statement('SET CONSTRAINTS ALL DEFERRED');
    }

    private function kegiatan(int $tahun = 2026): int
    {
        return app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Supervisi Akademik', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => $tahun, 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]]]);
    }

    /** @param  array<string, mixed>  $isi */
    private function dokumen(int $kegiatan, array $isi = []): int
    {
        return DB::table('documents')->insertGetId($isi + ['activity_id' => $kegiatan, 'tahun_anggaran' => 2026, 'jenis' => 'KAK+TOR']);
    }

    private function template(int $tahun): string
    {
        DB::table('document_templates')->insert(['id' => $id = (string) Str::uuid(), 'tahun_anggaran' => $tahun, 'nama' => 'Master KAK', 'jenis' => 'KAK+TOR', 'file_path' => "template/master/{$id}.docx", 'nama_berkas' => 'master.docx']);

        return $id;
    }

    public function test_tepat_satu_tahun_aktif(): void
    {
        $this->assertSame(2026, (int) DB::selectOne('SELECT tahun_aktif() AS t')->t);
        $this->ditolak(fn () => DB::table('tahun_anggaran')->update(['is_active' => false]), 'Harus ada satu tahun anggaran aktif.');
        $this->ditolak(fn () => DB::table('tahun_anggaran')->insert(['tahun' => 2027, 'is_active' => true]), 'tahun_anggaran_satu_aktif');

        // Ganti tahun aktif dua langkah dalam satu transaksi tetap sah (diperiksa saat commit).
        $this->sah(function () {
            DB::table('tahun_anggaran')->insert(['tahun' => 2027]);
            DB::table('tahun_anggaran')->where('tahun', 2026)->update(['is_active' => false]);
            DB::table('tahun_anggaran')->where('tahun', 2027)->update(['is_active' => true]);
        });
        $this->assertSame(2027, (int) DB::selectOne('SELECT tahun_aktif() AS t')->t);
    }

    public function test_dokumen_bertahun_kegiatannya_dan_bersumber_dana_sepasang(): void
    {
        $id = $this->kegiatan();
        $lain = $this->kegiatan();
        $anggaran = DB::table('anggaran_kegiatan')->where('activity_id', $id)->where('sumber_dana', 'DIPA')->value('id');
        $anggaranLain = DB::table('anggaran_kegiatan')->where('activity_id', $lain)->value('id');

        $kak = $this->dokumen($id);
        $this->assertSame(['draf', true, '{}'], [DB::table('documents')->where('id', $kak)->value('status'), (bool) DB::table('documents')->where('id', $kak)->value('bertanda_draf'), DB::table('documents')->where('id', $kak)->value('konten')]);
        $rab = $this->dokumen($id, ['jenis' => 'RAB', 'anggaran_kegiatan_id' => $anggaran, 'sumber_dana' => 'DIPA', 'nomor_dokumen' => 'RAB/001/2026']);

        $this->ditolak(fn () => $this->dokumen($id), 'documents_satu_per_jenis');
        $this->ditolak(fn () => $this->dokumen($id, ['jenis' => 'RAB']), 'documents_isian_check');
        $this->ditolak(fn () => $this->dokumen($id, ['jenis' => 'lengkap', 'anggaran_kegiatan_id' => $anggaran]), 'documents_isian_check');
        $this->ditolak(fn () => $this->dokumen($id, ['jenis' => 'Surat']), 'documents_isian_check');
        $this->ditolak(fn () => $this->dokumen($id, ['jenis' => 'KAK', 'status' => 'arsip']), 'documents_isian_check');
        $this->ditolak(fn () => $this->dokumen($id, ['jenis' => 'KAK', 'konten' => '[]']), 'documents_isian_check');
        $this->ditolak(fn () => $this->dokumen($id, ['jenis' => 'KAK', 'file_pdf_path' => 'dokumen/1.pdf']), 'documents_isian_check');
        $this->ditolak(fn () => $this->dokumen($id, ['jenis' => 'TOR', 'tahun_anggaran' => 2025]), 'documents_kegiatan_foreign');
        $this->ditolak(fn () => $this->dokumen($id, ['jenis' => 'lengkap', 'anggaran_kegiatan_id' => $anggaranLain, 'sumber_dana' => 'DIPA']), 'documents_anggaran_foreign');
        $this->ditolak(fn () => $this->dokumen($lain, ['jenis' => 'RAB', 'anggaran_kegiatan_id' => $anggaranLain, 'sumber_dana' => 'DIPA', 'nomor_dokumen' => 'RAB/001/2026']), 'documents_tahun_anggaran_nomor_dokumen_unique');
        $this->dokumen($id, ['jenis' => 'KAK', 'file_pdf_path' => 'dokumen/1.pdf', 'dicetak_pada' => now()]);

        // Template & cover harus dari tahun yang sama; dihapus → hanya rujukannya yang kosong.
        $this->ditolak(fn () => $this->dokumen($lain, ['template_id' => $this->template(2025)]), 'documents_template_foreign');
        $template = $this->template(2026);
        $tor = $this->dokumen($lain, ['template_id' => $template]);
        DB::table('document_templates')->where('id', $template)->delete();
        $this->assertSame([null, 2026], [DB::table('documents')->where('id', $tor)->value('template_id'), (int) DB::table('documents')->where('id', $tor)->value('tahun_anggaran')]);

        DB::table('activities')->where('id', $id)->delete();
        $this->assertSame(0, DB::table('documents')->whereIn('id', [$kak, $rab])->count(), 'Ikut terhapus bersama kegiatan.');
    }

    public function test_nomor_dokumen_berurutan_per_jenis_dan_tahun(): void
    {
        $nomor = fn (string $jenis, int $tahun) => (int) DB::selectOne('SELECT nomor_dokumen_berikutnya(?, ?) AS n', [$jenis, $tahun])->n;

        $this->assertSame([1, 2, 1, 1, 3], [$nomor('KAK+TOR', 2026), $nomor('KAK+TOR', 2026), $nomor('RAB', 2026), $nomor('KAK+TOR', 2025), $nomor('KAK+TOR', 2026)]);
        $this->assertSame(3, (int) DB::table('number_sequences')->where(['jenis' => 'KAK+TOR', 'tahun' => 2026])->value('nomor_terakhir'));
        $this->assertSame(array_keys(DokumenKegiatan::JENIS), ['KAK+TOR', 'KAK', 'TOR', 'RAB', 'lengkap']);
        $this->ditolak(fn () => $nomor('Surat', 2026), 'number_sequences_jenis_check');
        $this->ditolak(fn () => $nomor('KAK', 2030), 'number_sequences_tahun_foreign');
    }

    public function test_migrasi_bisa_dibatalkan(): void
    {
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::statement('SET CONSTRAINTS ALL DEFERRED');
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('documents'));
        $this->assertFalse(Schema::hasTable('number_sequences'));
        $this->assertNull(DB::selectOne("SELECT to_regproc('tahun_aktif') AS f")->f);
        $this->assertNull(DB::selectOne("SELECT to_regproc('nomor_dokumen_berikutnya') AS f")->f);

        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('documents'));
        $this->assertSame(2026, (int) DB::selectOne('SELECT tahun_aktif() AS t')->t);
    }
}
