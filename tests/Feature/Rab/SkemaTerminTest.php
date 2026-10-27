<?php

namespace Tests\Feature\Rab;

use App\Repositories\EloquentRabRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SkemaTerminTest extends TestCase
{
    use RefreshDatabase;

    private int $kegiatan;

    private int $anggaran;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kegiatan = DB::table('activities')->insertGetId(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Workshop Kurikulum']);
        $this->anggaran = DB::table('anggaran_kegiatan')->insertGetId(['activity_id' => $this->kegiatan, 'sumber_dana' => 'Komite', 'pagu' => 10_000_000]);
        // Aturan antarbaris diperiksa saat commit; transaksi tes tidak pernah commit, jadi periksa seketika.
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    /** @param  list<array{0: int, 1: string|null}>  $termin  [nomor, tanggal] */
    private function tulis(array $termin): void
    {
        foreach ($termin as [$nomor, $tanggal]) {
            DB::table('termin_pembayaran')->insert(['anggaran_kegiatan_id' => $this->anggaran, 'nomor_termin' => $nomor, 'tanggal' => $tanggal, 'nominal' => 1_000_000]);
        }
    }

    private function ditolak(string $pesan, callable $aksi): void
    {
        try {
            DB::transaction(function () use ($aksi) {
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
                $aksi();
            });
        } catch (QueryException $e) {
            $this->assertStringContainsString($pesan, $e->getMessage());

            return;
        }
        $this->fail("Seharusnya ditolak: {$pesan}");
    }

    public function test_termin_sah_dan_bisa_diganti_dalam_satu_transaksi(): void
    {
        $this->tulis([[1, '2026-06-17'], [2, '2026-06-19'], [3, null]]);

        DB::transaction(function () {
            DB::statement('SET CONSTRAINTS ALL DEFERRED');
            DB::table('termin_pembayaran')->where('anggaran_kegiatan_id', $this->anggaran)->delete();
            $this->tulis([[2, '2026-09-01'], [1, '2026-08-01']]);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });

        $this->assertSame([1, 2], DB::table('termin_pembayaran')->where('anggaran_kegiatan_id', $this->anggaran)->orderBy('nomor_termin')->pluck('nomor_termin')->all());
    }

    public function test_aturan_termin_dijaga_database(): void
    {
        $this->ditolak('Nomor termin harus berurutan 1 sampai 2.', fn () => $this->tulis([[1, '2026-06-17'], [3, '2026-06-19']]));
        $this->ditolak('Tanggal termin 1 harus di Tahun Anggaran 2026.', fn () => $this->tulis([[1, '2027-01-04']]));
        $this->ditolak('Tanggal termin 2 tidak boleh sebelum termin 1.', fn () => $this->tulis([[1, '2026-06-19'], [2, '2026-06-17']]));
        $this->ditolak('termin_pembayaran_nomor_maks_check', fn () => $this->tulis(array_map(fn ($n) => [$n, null], range(1, 13))));
        $this->assertSame(0, DB::table('termin_pembayaran')->count());

        // Menghapus termin di tengah meninggalkan celah nomor.
        $this->tulis([[1, '2026-06-17'], [2, '2026-06-18'], [3, '2026-06-19']]);
        $this->ditolak('Nomor termin harus berurutan', fn () => DB::table('termin_pembayaran')->where('nomor_termin', 2)->delete());
    }

    public function test_repositori_rab_menulis_termin_yang_lolos_aturan(): void
    {
        $repo = new EloquentRabRepository;
        $repo->simpanTermin($this->kegiatan, 'Komite', [['tanggal' => '2026-06-17', 'nominal' => 7_680_000], ['tanggal' => '2026-06-19', 'nominal' => 2_320_000]]);
        $repo->simpanTermin($this->kegiatan, 'Komite', [['tanggal' => '2026-07-01', 'nominal' => 10_000_000]]);
        $this->assertSame([['tanggal' => '2026-07-01', 'nominal' => 10_000_000]], $repo->termin($this->kegiatan, 'Komite'));

        $this->ditolak('tidak boleh sebelum termin 1', fn () => $repo->simpanTermin($this->kegiatan, 'Komite', [['tanggal' => '2026-07-01', 'nominal' => 1], ['tanggal' => '2026-06-01', 'nominal' => 1]]));
    }
}
