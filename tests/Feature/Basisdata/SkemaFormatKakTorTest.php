<?php

namespace Tests\Feature\Basisdata;

use App\Contracts\KegiatanRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Support\Dokumen\FormatKakTor;
use App\Support\Dokumen\SusunanKak;
use App\Support\Kegiatan\TeksKhusus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SkemaFormatKakTorTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_31_000049_add_format_kak_tor.php';

    private function ditolak(callable $aksi): bool
    {
        try {
            DB::transaction($aksi);
        } catch (QueryException) {
            return true;
        }

        return false;
    }

    public function test_format_kegiatan_dan_tahun_dijaga_database(): void
    {
        RujukanDb::siapkan();
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $repo = app(KegiatanRepository::class);
        $id = $repo->simpan(['nama_kegiatan' => 'Finalisasi Ijazah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026]);
        $this->assertArrayNotHasKey('format_dokumen', $repo->temukan($id), 'Tanpa pilihan = ikut tahun, seperti tiruan.');

        $repo->simpan(['format_dokumen' => FormatKakTor::LIMA_BAB], $id);
        $this->assertSame(FormatKakTor::LIMA_BAB, $repo->temukan($id)['format_dokumen']);
        $this->assertSame(FormatKakTor::LIMA_BAB, SusunanKak::dari($repo->temukan($id))->format);

        $this->assertTrue($this->ditolak(fn () => DB::table('activities')->where('id', $id)->update(['format_dokumen' => 'empat_bab'])));
        $this->assertFalse($this->ditolak(fn () => DB::table('pengaturan_format')->insert(['tahun_anggaran' => 2026, 'format_kak' => FormatKakTor::SEMBILAN_BAGIAN])));
        $this->assertTrue($this->ditolak(fn () => DB::table('pengaturan_format')->where('tahun_anggaran', 2026)->update(['format_kak' => 'empat_bab'])));
    }

    public function test_teks_khusus_dan_sunting_bidang_baru(): void
    {
        $teks = ['narasumber' => 'Narasumber kegiatan {nama_kegiatan} adalah pengawas madrasah.', 'output' => 'Tersusunnya dokumen kurikulum.', 'outcome' => 'Meningkatnya mutu layanan pembelajaran.', 'penanggung_jawab' => 'Penanggung jawab kegiatan adalah Kepala Madrasah.'];
        $this->assertSame([], TeksKhusus::galat($teks));
        $this->assertFalse($this->ditolak(fn () => DB::table('activities')->insert(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Rapat Kerja', 'teks_khusus' => json_encode($teks)])));
        $this->assertTrue($this->ditolak(fn () => DB::table('activities')->insert(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Rapat Kerja', 'teks_khusus' => json_encode(['output' => 'Pendek'])])));

        $id = DB::table('activities')->insertGetId(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Rapat Kerja']);
        $this->assertFalse($this->ditolak(fn () => DB::table('riwayat_sunting_dokumen')->insert(['activity_id' => $id, 'bidang' => 'narasumber', 'nomor_bagian' => 2, 'asal' => 'teks_khusus', 'teks_lama' => null, 'teks_baru' => 'Narasumber kegiatan adalah pengawas.', 'pengguna' => 'Admin', 'dibuat_pada' => now()])));
        $this->assertTrue($this->ditolak(fn () => DB::table('riwayat_sunting_dokumen')->insert(['activity_id' => $id, 'bidang' => 'narasumber', 'nomor_bagian' => 5, 'asal' => 'teks_khusus', 'teks_lama' => null, 'teks_baru' => 'Narasumber kegiatan.', 'pengguna' => 'Admin', 'dibuat_pada' => now()])));
    }

    public function test_migrasi_bisa_dibatalkan_dan_dijalankan_ulang(): void
    {
        RujukanDb::segera();
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertFalse(Schema::hasColumn('activities', 'format_dokumen'));
        $this->assertFalse(Schema::hasColumn('pengaturan_format', 'format_kak'));
        $this->assertTrue($this->ditolak(fn () => DB::table('activities')->insert(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Rapat Kerja', 'teks_khusus' => json_encode(['output' => 'Tersusunnya dokumen kurikulum.'])])), 'Tanpa migrasi ini bidang baru belum dikenal.');

        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertTrue(Schema::hasColumn('activities', 'format_dokumen'));
        $this->assertTrue(Schema::hasColumn('pengaturan_format', 'format_kak'));
    }
}
