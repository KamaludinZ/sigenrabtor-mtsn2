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

class SkemaRiwayatSuntingTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_29_000041_create_riwayat_sunting_dokumen_table.php';

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

    private function kegiatan(): int
    {
        return app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Rapat Kerja Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026]);
    }

    /** @param  array<string, mixed>  $isi */
    private function catatan(int $kegiatan, array $isi = []): int
    {
        return DB::table('riwayat_sunting_dokumen')->insertGetId($isi + [
            'activity_id' => $kegiatan, 'bidang' => 'tempat', 'nomor_bagian' => 5, 'asal' => 'isian',
            'teks_lama' => 'Ruang Rapat', 'teks_baru' => 'Aula Utama', 'user_id' => 1, 'pengguna' => 'Muslimin, M.Pd.',
        ]);
    }

    public function test_isian_jejak_sejalan_dengan_bidang_sunting(): void
    {
        $id = $this->kegiatan();
        $sah = $this->catatan($id);
        $this->catatan($id, ['bidang' => 'persiapan', 'nomor_bagian' => 4, 'asal' => 'teks_khusus', 'teks_lama' => null, 'teks_baru' => "Butir satu\nButir dua"]);
        $this->catatan($id, ['teks_lama' => 'Aula Utama', 'teks_baru' => null]);
        $this->assertNotNull(DB::table('riwayat_sunting_dokumen')->where('id', $sah)->value('dibuat_pada'));

        foreach ([
            'bidang asing' => ['bidang' => 'nomor_dokumen'],
            'nomor bagian tidak sesuai' => ['nomor_bagian' => 4],
            'asal tidak sesuai' => ['asal' => 'teks_khusus'],
            'teks sama' => ['teks_lama' => 'Aula Utama'],
            'keduanya kosong' => ['teks_lama' => null, 'teks_baru' => null],
            'teks kosong' => ['teks_baru' => ''],
            'spasi tepi' => ['teks_baru' => ' Aula Utama'],
            'terlalu panjang' => ['teks_baru' => str_repeat('a', SuntingDokumen::maksTeks('tempat') + 1)],
            'penyunting kosong' => ['pengguna' => ' '],
        ] as $kasus => $isi) {
            $this->ditolak(fn () => $this->catatan($id, $isi), 'riwayat_sunting_isian_check');
        }
        $this->catatan($id, ['teks_baru' => str_repeat('a', SuntingDokumen::maksTeks('tempat'))]);
    }

    public function test_jejak_hanya_ditambah_dan_ikut_terhapus_bersama_kegiatan(): void
    {
        $id = $this->kegiatan();
        $catatan = $this->catatan($id);

        $this->ditolak(fn () => DB::table('riwayat_sunting_dokumen')->where('id', $catatan)->update(['teks_baru' => 'Aula Lain']), 'hanya bisa ditambah');
        $this->ditolak(fn () => DB::table('riwayat_sunting_dokumen')->where('id', $catatan)->delete(), 'hanya bisa ditambah');
        $this->ditolak(fn () => DB::table('riwayat_sunting_dokumen')->where('id', $catatan)->update(['user_id' => 2]), 'hanya bisa ditambah');
        DB::table('riwayat_sunting_dokumen')->where('id', $catatan)->update(['user_id' => null]);
        $this->assertSame([null, 'Muslimin, M.Pd.'], [DB::table('riwayat_sunting_dokumen')->where('id', $catatan)->value('user_id'), DB::table('riwayat_sunting_dokumen')->where('id', $catatan)->value('pengguna')]);

        DB::table('activities')->where('id', $id)->delete();
        $this->assertSame(0, DB::table('riwayat_sunting_dokumen')->where('activity_id', $id)->count());
    }

    public function test_migrasi_bisa_dibatalkan(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('riwayat_sunting_dokumen'));
        $this->assertNull(DB::selectOne("SELECT to_regproc('riwayat_sunting_hanya_tambah') AS f")->f);
        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('riwayat_sunting_dokumen'));
    }
}
