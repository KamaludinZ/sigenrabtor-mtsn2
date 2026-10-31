<?php

namespace Tests\Feature\Basisdata;

use App\Contracts\TemplateDokumenRepository;
use App\Repositories\EloquentTemplateDokumenRepository;
use App\Support\Pengaturan\UnggahMasterTemplate;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Docx;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SkemaTemplateMasterTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_31_000052_create_template_master_variabel.php';

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    private function unggah(): array
    {
        $berkas = UploadedFile::fake()->createWithContent('reguler.docx', Docx::isi(['Kegiatan ${nama_kegiatan}', 'Total ${total} (${terbilang})', 'Sekali lagi ${nama_kegiatan}']));

        return UnggahMasterTemplate::simpan(2026, $berkas, '  Master   reguler ', 'KAK+TOR', self::ADMIN, ['leading' => '1.6']);
    }

    private function ditolak(callable $aksi): bool
    {
        try {
            DB::transaction($aksi);
        } catch (QueryException) {
            return true;
        }

        return false;
    }

    #[DataProvider('penyimpanan')]
    public function test_pengunggah_dan_kemunculan_variabel(bool $database): void
    {
        if ($database) {
            RujukanDb::siapkan();
            $this->app->singleton(TemplateDokumenRepository::class, EloquentTemplateDokumenRepository::class);
        }
        $master = $this->unggah();

        $this->assertSame(['Master reguler', 2, 'Koordinator PUSKOM'], [$master['nama'], $master['dibuat_oleh'], $master['pengunggah']]);
        $this->assertSame(['nama_kegiatan', 'total', 'terbilang'], $master['variabel']);
        $this->assertSame(['nama_kegiatan' => 2, 'total' => 1, 'terbilang' => 1], $master['jumlah']);
        $this->assertSame($master['jumlah'], app(TemplateDokumenRepository::class)->semua(2026)[0]['jumlah']);

        if ($database) {
            $this->assertSame([['nama_kegiatan', 1, 2], ['total', 2, 1], ['terbilang', 3, 1]], DB::table('document_template_variabel')->where('template_id', $master['id'])->orderBy('urutan')->get()
                ->map(fn ($b) => [$b->variabel, (int) $b->urutan, (int) $b->jumlah])->all());
        }
    }

    public function test_aturan_database(): void
    {
        RujukanDb::siapkan();
        $this->app->singleton(TemplateDokumenRepository::class, EloquentTemplateDokumenRepository::class);
        $id = $this->unggah()['id'];

        $this->assertTrue($this->ditolak(fn () => DB::table('document_templates')->where('id', $id)->update(['aktif' => false])), 'Default harus aktif.');
        $this->assertFalse($this->ditolak(fn () => DB::table('document_templates')->where('id', $id)->update(['aktif' => false, 'is_default' => false])));
        $this->assertTrue($this->ditolak(fn () => DB::table('document_templates')->where('id', $id)->update(['nama' => 'Dua  spasi'])));
        $this->assertTrue($this->ditolak(fn () => DB::table('document_templates')->where('id', $id)->update(['nama' => str_repeat('a', UnggahMasterTemplate::MAKS_NAMA + 1)])));
        $this->assertTrue($this->ditolak(fn () => DB::table('document_template_variabel')->insert(['template_id' => $id, 'variabel' => 'bukan_variabel', 'urutan' => 9, 'jumlah' => 1])));
        $this->assertTrue($this->ditolak(fn () => DB::table('document_template_variabel')->insert(['template_id' => $id, 'variabel' => 'tujuan', 'urutan' => 1, 'jumlah' => 1])), 'Urutan unik per template.');
        $this->assertTrue($this->ditolak(fn () => DB::table('document_template_variabel')->insert(['template_id' => $id, 'variabel' => 'tujuan', 'urutan' => 9, 'jumlah' => 0])));

        app(TemplateDokumenRepository::class)->hapus($id);
        $this->assertSame(0, DB::table('document_template_variabel')->where('template_id', $id)->count(), 'Ikut terhapus bersama templatenya.');
    }

    public function test_migrasi_bisa_dibatalkan_dan_dijalankan_ulang(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('document_template_variabel'));
        $this->assertFalse(Schema::hasColumn('document_templates', 'pengunggah'));
        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('document_template_variabel'));
        $this->assertTrue(Schema::hasColumn('document_templates', 'dibuat_oleh'));
    }
}
