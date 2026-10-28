<?php

namespace Tests\Feature\Rab;

use App\Repositories\EloquentRabRepository;
use App\Support\Terbilang;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SkemaTerbilangTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_28_000007_add_terbilang_constraints.php';

    private const MIGRASI_FUNGSI = 'database/migrations/2026_10_28_000033_add_terbilang_functions.php';

    private int $kegiatan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kegiatan = DB::table('activities')->insertGetId(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Workshop Kurikulum']);
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

    /** @param  array<string, mixed>  $data */
    private function anggaran(array $data): int
    {
        return DB::table('anggaran_kegiatan')->insertGetId($data + ['activity_id' => $this->kegiatan, 'sumber_dana' => 'DIPA']);
    }

    public function test_terbilang_selalu_sesuai_total(): void
    {
        // Terbilang terpanjang yang mungkin muat di kolom.
        $id = $this->anggaran(['total_rab' => Terbilang::MAKSIMUM, 'terbilang' => Terbilang::rupiah(Terbilang::MAKSIMUM)]);
        $this->assertSame(226, mb_strlen(DB::table('anggaran_kegiatan')->find($id)->terbilang));

        $this->ditolak(fn () => $this->anggaran(['sumber_dana' => 'Komite', 'total_rab' => 0, 'terbilang' => 'Nol Rupiah']));
        $this->ditolak(fn () => $this->anggaran(['sumber_dana' => 'Komite', 'total_rab' => 10_000, 'terbilang' => null]));
        $this->ditolak(fn () => $this->anggaran(['sumber_dana' => 'Komite', 'total_rab' => Terbilang::MAKSIMUM + 1, 'terbilang' => 'x']));
        $this->ditolak(fn () => DB::table('termin_pembayaran')->insert(['anggaran_kegiatan_id' => $id, 'nomor_termin' => 1, 'nominal' => Terbilang::MAKSIMUM + 1]));
    }

    public function test_repositori_menjaga_terbilang_saat_rincian_berubah(): void
    {
        $repo = new EloquentRabRepository;
        $id = $repo->tambahItem($this->kegiatan, 'Komite', ['kode' => '', 'uraian' => 'Konsumsi peserta', 'kategori' => 'bahan', 'volume' => 56, 'satuan' => 'kotak', 'harga_satuan' => 25_000]);
        $baris = fn () => DB::table('anggaran_kegiatan')->where('activity_id', $this->kegiatan)->first(['total_rab', 'terbilang']);
        $this->assertSame([1_400_000, 'Satu Juta Empat Ratus Ribu Rupiah'], [$baris()->total_rab, $baris()->terbilang]);

        $repo->hapusItem($this->kegiatan, 'Komite', $id);
        $this->assertSame([0, null], [$baris()->total_rab, $baris()->terbilang]);
    }

    public function test_migrasi_menghitung_ulang_salinan_lama(): void
    {
        // Fungsi & CHECK terbilang database (000033) dibangun di atas CHECK 000007: dibatalkan lebih dulu.
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI_FUNGSI])->assertSuccessful();
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();

        // Salinan lama yang tidak sesuai rinciannya (mis. diubah langsung di database).
        $basi = $this->anggaran(['total_rab' => 5, 'terbilang' => 'Lima Rupiah']);
        DB::table('budget_items')->insert(['id' => (string) Str::uuid(), 'anggaran_kegiatan_id' => $basi, 'urutan' => 1, 'uraian' => 'Spanduk', 'kategori' => 'lain', 'volume' => 2, 'satuan' => 'buah', 'harga_satuan' => 150_000]);
        $kosong = $this->anggaran(['sumber_dana' => 'Komite', 'total_rab' => 0, 'terbilang' => 'Nol Rupiah']);

        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->artisan('migrate', ['--path' => self::MIGRASI_FUNGSI])->assertSuccessful();

        $this->assertSame([300_000, 'Tiga Ratus Ribu Rupiah'], [DB::table('anggaran_kegiatan')->find($basi)->total_rab, DB::table('anggaran_kegiatan')->find($basi)->terbilang]);
        $this->assertSame([0, null], [DB::table('anggaran_kegiatan')->find($kosong)->total_rab, DB::table('anggaran_kegiatan')->find($kosong)->terbilang]);
    }
}
