<?php

namespace Tests\Feature\Basisdata;

use App\Repositories\EloquentRabRepository;
use App\Support\Format;
use App\Support\Terbilang;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SkemaFormatRupiahTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_28_000033_add_terbilang_functions.php';

    private function nilai(string $fungsi, int $n): string
    {
        return DB::selectOne("SELECT {$fungsi}(?::bigint) AS v", [$n])->v;
    }

    public function test_terbilang_database_sama_dengan_kasus_bersama(): void
    {
        $kasus = json_decode((string) file_get_contents(base_path('tests/fixtures/terbilang.json')), true);
        foreach ($kasus['kasus'] as $k) {
            $this->assertSame($k['rupiah'], $this->nilai('terbilang_rupiah', $k['nilai']), (string) $k['nilai']);
            $this->assertSame($k['kata'], $this->nilai('terbilang_kata', $k['nilai']), (string) $k['nilai']);
        }
        foreach ($kasus['di_luar_batas'] as $n) {
            try {
                DB::transaction(fn () => $this->nilai('terbilang_rupiah', $n));
                $this->fail("{$n} seharusnya ditolak.");
            } catch (QueryException $e) {
                $this->assertStringContainsString('Nilai terlalu besar untuk diterbilangkan (maksimal 999 triliun).', $e->getMessage());
            }
        }
    }

    public function test_format_dan_terbilang_database_sama_dengan_php(): void
    {
        mt_srand(2026);
        $angka = [0, 1, -1, 999, 1_000, -600_000, 1_001_001, Terbilang::MAKSIMUM, -Terbilang::MAKSIMUM];
        foreach ([10, 1_000, 100_000, 10_000_000, 1_000_000_000, 1_000_000_000_000, Terbilang::MAKSIMUM] as $batas) {
            for ($i = 0; $i < 25; $i++) {
                $angka[] = mt_rand(0, $batas) * (mt_rand(0, 4) === 0 ? -1 : 1);
            }
        }

        foreach ($angka as $n) {
            $this->assertSame(Terbilang::rupiah($n), $this->nilai('terbilang_rupiah', $n), (string) $n);
            $this->assertSame(Format::angka($n), $this->nilai('format_angka', $n), (string) $n);
            $this->assertSame(Format::rupiah($n), $this->nilai('format_rupiah', $n), (string) $n);
            $this->assertSame(Format::rupiahDokumen($n), $this->nilai('format_rupiah_dokumen', $n), (string) $n);
        }
    }

    public function test_salinan_terbilang_anggaran_wajib_sesuai_total(): void
    {
        $kegiatan = DB::table('activities')->insertGetId(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Workshop Kurikulum']);
        $tolak = function (array $baris) use ($kegiatan) {
            try {
                DB::transaction(fn () => DB::table('anggaran_kegiatan')->insert($baris + ['activity_id' => $kegiatan, 'sumber_dana' => 'Komite']));
                $this->fail('Seharusnya ditolak.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('anggaran_kegiatan_terbilang_sesuai', $e->getMessage());
            }
        };
        $tolak(['total_rab' => 11, 'terbilang' => 'Sepuluh Rupiah']);
        $tolak(['total_rab' => 1_400_000, 'terbilang' => 'satu juta empat ratus ribu rupiah']);
        DB::table('anggaran_kegiatan')->insert(['activity_id' => $kegiatan, 'sumber_dana' => 'DIPA', 'total_rab' => 1_400_000, 'terbilang' => 'Satu Juta Empat Ratus Ribu Rupiah']);

        // Repositori RAB menulis salinan yang sama dengan fungsi database.
        $repo = new EloquentRabRepository;
        $repo->tambahItem($kegiatan, 'Komite', ['kode' => '', 'uraian' => 'Konsumsi peserta', 'kategori' => 'bahan', 'volume' => 3, 'satuan' => 'kotak', 'harga_satuan' => 333_333]);
        $this->assertSame('Sembilan Ratus Sembilan Puluh Sembilan Ribu Sembilan Ratus Sembilan Puluh Sembilan Rupiah', DB::table('anggaran_kegiatan')->where(['activity_id' => $kegiatan, 'sumber_dana' => 'Komite'])->value('terbilang'));
    }

    public function test_migrasi_menghitung_ulang_dan_bisa_dibatalkan(): void
    {
        RujukanDb::siapkan();
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertNull(DB::selectOne("SELECT to_regproc('terbilang_rupiah') AS f")->f);
        $kegiatan = DB::table('activities')->insertGetId(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Workshop Kurikulum']);
        // Salinan lama yang salah tulis (lolos CHECK 000007 yang hanya memeriksa kosong/tidaknya).
        $id = DB::table('anggaran_kegiatan')->insertGetId(['activity_id' => $kegiatan, 'sumber_dana' => 'DIPA', 'total_rab' => 25_000, 'terbilang' => 'Dua Puluh Ribu Rupiah']);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::statement('SET CONSTRAINTS ALL DEFERRED');

        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertSame('Dua Puluh Lima Ribu Rupiah', DB::table('anggaran_kegiatan')->where('id', $id)->value('terbilang'));
        $this->assertTrue(Schema::hasTable('anggaran_kegiatan'));
    }
}
