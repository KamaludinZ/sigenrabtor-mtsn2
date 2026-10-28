<?php

namespace Tests\Feature\Basisdata;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SkemaImporDuplikatTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_28_000035_create_impor_dan_duplikat_tables.php';

    protected function setUp(): void
    {
        parent::setUp();
        RujukanDb::siapkan();
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
    private function impor(array $isi = []): string
    {
        DB::table('impor_kegiatan')->insert($isi + ['id' => $id = (string) Str::uuid(), 'tahun_anggaran' => 2026, 'jenis' => 'perkin', 'nama_berkas' => 'cascade.xlsx', 'lembar' => 'Cascade Perkin', 'pengguna' => 'Muslimin, M.Pd.', 'user_id' => 1]);

        return $id;
    }

    /** @param  array<string, mixed>  $isi */
    private function kegiatan(array $isi = []): int
    {
        return DB::table('activities')->insertGetId($isi + ['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Rapat Kerja', 'kode_leading' => '1.6']);
    }

    public function test_impor_dan_baris_drafnya(): void
    {
        $impor = $this->impor(['jumlah_baris' => 3, 'jumlah_siap' => 1, 'jumlah_dilewati' => 1, 'jumlah_gagal' => 1]);
        $hasil = $this->kegiatan(['asal' => 'impor_perkin', 'impor_id' => $impor]);
        DB::table('draf_impor_kegiatan')->insert([
            ['impor_id' => $impor, 'nomor_baris' => 5, 'status' => 'siap', 'data' => json_encode(['nama_kegiatan' => 'Rapat Kerja']), 'pesan' => '[]', 'peringatan' => json_encode(['IKSK 8.9 tidak ada di master TA 2026 — dikosongkan.']), 'kegiatan_id' => $hasil, 'diproses_pada' => now()],
            ['impor_id' => $impor, 'nomor_baris' => 6, 'status' => 'dilewati', 'data' => '{}', 'pesan' => json_encode(['Nama sudah ada.']), 'peringatan' => '[]', 'kegiatan_id' => null, 'diproses_pada' => null],
            ['impor_id' => $impor, 'nomor_baris' => 7, 'status' => 'gagal', 'data' => '{}', 'pesan' => json_encode(['Leading tidak dikenal.']), 'peringatan' => '[]', 'kegiatan_id' => null, 'diproses_pada' => null],
        ]);
        DB::table('impor_kegiatan')->where('id', $impor)->update(['status' => 'selesai', 'selesai_pada' => now(), 'jumlah_dibuat' => 1]);

        $this->ditolak(fn () => $this->impor(['jumlah_baris' => 2, 'jumlah_siap' => 1]), 'impor_kegiatan_isian_check');
        $this->ditolak(fn () => $this->impor(['jumlah_baris' => 1, 'jumlah_siap' => 1, 'jumlah_dibuat' => 2]), 'impor_kegiatan_isian_check');
        $this->ditolak(fn () => $this->impor(['status' => 'selesai']), 'impor_kegiatan_isian_check');
        $this->ditolak(fn () => $this->impor(['lembar' => ' ']), 'impor_kegiatan_isian_check');
        $this->ditolak(fn () => $this->impor(['jenis' => 'csv']), 'impor_kegiatan_jenis_check');

        $baris = ['impor_id' => $impor, 'status' => 'siap', 'data' => '{}', 'pesan' => '[]', 'peringatan' => '[]'];
        $this->ditolak(fn () => DB::table('draf_impor_kegiatan')->insert(['nomor_baris' => 5] + $baris), 'draf_impor_kegiatan_impor_id_nomor_baris_unique');
        $this->ditolak(fn () => DB::table('draf_impor_kegiatan')->insert(['nomor_baris' => 8, 'status' => 'gagal'] + $baris), 'draf_impor_kegiatan_isian_check');
        $this->ditolak(fn () => DB::table('draf_impor_kegiatan')->insert(['nomor_baris' => 9, 'status' => 'dilewati', 'kegiatan_id' => $hasil, 'diproses_pada' => now()] + $baris), 'draf_impor_kegiatan_isian_check');
        $this->ditolak(fn () => DB::table('draf_impor_kegiatan')->insert(['nomor_baris' => 10, 'kegiatan_id' => $hasil] + $baris), 'draf_impor_kegiatan_isian_check');
        $this->ditolak(fn () => DB::table('draf_impor_kegiatan')->insert(['nomor_baris' => 11, 'data' => '[]'] + $baris), 'draf_impor_kegiatan_isian_check');
        $this->ditolak(fn () => DB::table('draf_impor_kegiatan')->insert(['nomor_baris' => 0] + $baris), 'draf_impor_kegiatan_isian_check');

        // Kegiatan impor hanya berasal impor; impor dihapus → kegiatan tetap, barisnya ikut terhapus.
        $this->ditolak(fn () => $this->kegiatan(['asal' => 'manual', 'impor_id' => $impor]), 'activities_impor_check');
        DB::table('impor_kegiatan')->where('id', $impor)->delete();
        $this->assertSame([0, null], [DB::table('draf_impor_kegiatan')->count(), DB::table('activities')->where('id', $hasil)->value('impor_id')]);
    }

    public function test_jejak_duplikat_tahun_lalu(): void
    {
        $sumber = $this->kegiatan(['tahun_anggaran' => 2025, 'nama_kegiatan' => 'Rapat Kerja 2025']);
        $hasil = $this->kegiatan(['duplikat_dari_id' => $sumber]);
        $catatan = [['jenis' => 'kode', 'teks' => 'Kode SK SK.99 tidak ada di master TA 2026 — pilih ulang.']];
        $isi = ['sumber_id' => $sumber, 'sumber_tahun' => 2025, 'hasil_id' => $hasil, 'tahun_anggaran' => 2026, 'catatan' => json_encode($catatan), 'pengguna' => 'Muslimin, M.Pd.', 'user_id' => 1];
        DB::table('duplikat_kegiatan')->insert($isi);

        $this->ditolak(fn () => DB::table('duplikat_kegiatan')->insert($isi), 'duplikat_kegiatan_hasil_id_unique');
        $lain = $this->kegiatan(['nama_kegiatan' => 'Lain']);
        $this->ditolak(fn () => DB::table('duplikat_kegiatan')->insert(['hasil_id' => $lain, 'sumber_tahun' => 2026] + $isi), 'duplikat_kegiatan_isian_check');
        $this->ditolak(fn () => DB::table('duplikat_kegiatan')->insert(['hasil_id' => $lain, 'sumber_id' => $lain] + $isi), 'duplikat_kegiatan_isian_check');
        $this->ditolak(fn () => DB::table('duplikat_kegiatan')->insert(['hasil_id' => $lain, 'catatan' => '{}'] + $isi), 'duplikat_kegiatan_isian_check');

        DB::table('activities')->where('id', $sumber)->delete();
        $this->assertSame([null, 2025], [DB::table('duplikat_kegiatan')->where('hasil_id', $hasil)->value('sumber_id'), (int) DB::table('duplikat_kegiatan')->where('hasil_id', $hasil)->value('sumber_tahun')], 'Sumber dihapus: tahunnya tetap tercatat.');
        DB::table('activities')->where('id', $hasil)->delete();
        $this->assertSame(0, DB::table('duplikat_kegiatan')->count());
    }

    public function test_migrasi_bisa_dibatalkan(): void
    {
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::statement('SET CONSTRAINTS ALL DEFERRED');
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('impor_kegiatan'));
        $this->assertFalse(Schema::hasColumn('activities', 'impor_id'));
        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('duplikat_kegiatan'));
    }
}
