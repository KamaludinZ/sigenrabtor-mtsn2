<?php

namespace Tests\Feature\Basisdata;

use App\Contracts\KegiatanRepository;
use App\Repositories\EloquentKegiatanRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SkemaTemplateDipakaiTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_31_000053_restrict_template_dipakai_dokumen.php';

    protected function setUp(): void
    {
        parent::setUp();
        RujukanDb::siapkan();
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
    }

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

    private function template(int $tahun = 2026): string
    {
        DB::table('document_templates')->insert(['id' => $id = (string) Str::uuid(), 'tahun_anggaran' => $tahun, 'nama' => 'Master '.$id, 'jenis' => 'KAK+TOR', 'file_path' => "template/master/{$id}.docx", 'nama_berkas' => 'master.docx']);

        return $id;
    }

    /** @param  array<string, mixed>  $isi */
    private function catatan(array $isi): string
    {
        DB::table('riwayat_dokumen')->insert(['id' => $id = (string) Str::uuid()] + $isi + [
            'tahun_anggaran' => 2026, 'kode_leading' => '1.6', 'nama_kegiatan' => 'Rapat Kerja Madrasah',
            'kunci_versi' => 'x:'.$id, 'jenis' => 'KAK+TOR', 'sumber_dana' => null, 'format' => 'docx', 'asal' => 'unduh',
            'nama_berkas' => 'KAK-TOR_Rapat_Kerja_Madrasah_2026_DRAF.docx', 'ukuran' => 48_000, 'status' => 'draf', 'bertanda_draf' => true,
            'versi' => 1, 'ringkas' => json_encode(['total_rab' => null, 'bagian_lengkap' => 3, 'jumlah_bagian' => 9]),
            'user_id' => 1, 'dibuat_oleh' => 'Muslimin, M.Pd.',
        ]);

        return $id;
    }

    public function test_template_yang_dipakai_dokumen_tidak_bisa_dihapus(): void
    {
        $kegiatan = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Rapat Kerja Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026]);
        $dipakai = $this->template();
        $tercatat = $this->template();
        $bebas = $this->template();

        // Hasil susunan: RESTRICT, dan hanya dokumen yang memuat KAK/TOR.
        DB::table('documents')->insert(['activity_id' => $kegiatan, 'tahun_anggaran' => 2026, 'jenis' => 'KAK+TOR', 'template_id' => $dipakai]);
        $this->ditolak(fn () => DB::table('document_templates')->where('id', $dipakai)->delete(), 'documents_template_foreign');
        $this->ditolak(fn () => DB::table('documents')->insert(['activity_id' => $kegiatan, 'tahun_anggaran' => 2026, 'jenis' => 'KAK', 'template_id' => $this->template(2025)]), 'documents_template_foreign');

        // Riwayat Dokumen: template tahun yang sama, RESTRICT, bukan RAB, dan tidak bisa diubah sesudah dicatat.
        $id = $this->catatan(['template_id' => $tercatat]);
        $this->ditolak(fn () => DB::table('document_templates')->where('id', $tercatat)->delete(), 'riwayat_dokumen_template_foreign');
        $this->ditolak(fn () => $this->catatan(['template_id' => $this->template(2025)]), 'riwayat_dokumen_template_foreign');
        $this->ditolak(fn () => $this->catatan(['template_id' => $bebas, 'jenis' => 'RAB', 'sumber_dana' => 'DIPA', 'format' => 'pdf', 'nama_berkas' => 'RAB_X_DIPA_2026.pdf']), 'riwayat_dokumen_template_kak_check');
        $this->ditolak(fn () => DB::table('riwayat_dokumen')->where('id', $id)->update(['template_id' => $bebas]), 'riwayat_dokumen hanya bisa ditambah');
        $this->ditolak(fn () => DB::table('riwayat_dokumen')->where('id', $id)->update(['template_id' => null]), 'riwayat_dokumen hanya bisa ditambah');
        $this->catatan(['template_id' => null]);

        DB::table('document_templates')->where('id', $bebas)->delete();
        $this->assertSame(2, DB::table('document_templates')->whereIn('id', [$dipakai, $tercatat])->count());
        // Nomor dokumen tetap boleh diisi sekali (migrasi 000045) walau trigger kini juga menjaga template.
        DB::table('riwayat_dokumen')->where('id', $id)->update(['nomor_dokumen' => 'KAK/001/2026']);
        $this->assertSame('KAK/001/2026', DB::table('riwayat_dokumen')->where('id', $id)->value('nomor_dokumen'));
    }

    public function test_migrasi_bisa_dibatalkan(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertFalse(Schema::hasColumn('riwayat_dokumen', 'template_id'));
        $kegiatan = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Rapat Kerja Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026]);
        $template = $this->template();
        DB::table('documents')->insert(['activity_id' => $kegiatan, 'tahun_anggaran' => 2026, 'jenis' => 'KAK+TOR', 'template_id' => $template]);
        DB::table('document_templates')->where('id', $template)->delete();
        $this->assertNull(DB::table('documents')->where('activity_id', $kegiatan)->value('template_id'), 'Kembali ON DELETE SET NULL.');

        // Tabel dengan peristiwa trigger tertunda tidak bisa di-ALTER: selesaikan dulu.
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::statement('SET CONSTRAINTS ALL DEFERRED');
        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertTrue(Schema::hasColumn('riwayat_dokumen', 'template_id'));
    }
}
