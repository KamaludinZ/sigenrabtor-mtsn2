<?php

namespace Tests\Feature\Rab;

use App\Livewire\Forms\ItemBelanjaForm;
use App\Repositories\EloquentKegiatanRepository;
use App\Support\Rab\KalkulatorRab;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\RujukanDb;
use Tests\TestCase;
use Tests\Unit\KalkulatorRabTest;

class SkemaRabTest extends TestCase
{
    use RefreshDatabase;

    private int $anggaran;

    protected function setUp(): void
    {
        parent::setUp();
        RujukanDb::siapkan();
        $kegiatan = DB::table('activities')->insertGetId(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Workshop Kurikulum']);
        $this->anggaran = DB::table('anggaran_kegiatan')->insertGetId(['activity_id' => $kegiatan, 'sumber_dana' => 'Komite', 'pagu' => 10_000_000]);
    }

    /** @param  array<string, mixed>  $data */
    private function item(array $data = []): string
    {
        $id = (string) Str::uuid();
        DB::table('budget_items')->insert($data + [
            'id' => $id, 'anggaran_kegiatan_id' => $this->anggaran, 'urutan' => DB::table('budget_items')->count() + 1,
            'uraian' => 'Konsumsi peserta', 'kategori' => 'bahan', 'volume' => 1, 'satuan' => 'kotak', 'harga_satuan' => 25_000,
        ]);

        return $id;
    }

    private function ditolak(callable $aksi): void
    {
        try {
            DB::transaction(function () use ($aksi) {
                // Batasan DEFERRABLE diperiksa saat commit; transaksi tes tidak pernah commit, jadi periksa seketika.
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
                $aksi();
            });
        } catch (QueryException) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail('Seharusnya ditolak database.');
    }

    public function test_kolom_mengikuti_rab_repository(): void
    {
        $this->assertTrue(Schema::hasColumns('budget_items', ['id', 'anggaran_kegiatan_id', 'urutan', 'kode', 'uraian', 'kategori', 'volume', 'satuan', 'harga_satuan', 'jumlah']));
        $this->assertTrue(Schema::hasColumns('termin_pembayaran', ['anggaran_kegiatan_id', 'nomor_termin', 'tanggal', 'nominal']));
        $this->assertTrue(Schema::hasColumn('anggaran_kegiatan', 'dipilih'));
        $this->assertSame(true, DB::table('anggaran_kegiatan')->find($this->anggaran)->dipilih);
    }

    public function test_jumlah_dihitung_database_sama_dengan_kalkulator_rab(): void
    {
        foreach (KalkulatorRabTest::jumlahProvider() as $kasus => [$volume, $harga, $harapan]) {
            $id = $this->item(['volume' => $volume, 'harga_satuan' => $harga]);
            $this->assertSame([$harapan, KalkulatorRab::jumlah($volume, $harga)], [DB::table('budget_items')->find($id)->jumlah, $harapan], $kasus);
        }

        // Diubah → jumlah ikut; tidak bisa ditulis langsung.
        $id = $this->item(['volume' => 2, 'harga_satuan' => 1_000]);
        DB::table('budget_items')->where('id', $id)->update(['volume' => 3]);
        $this->assertSame(3_000, DB::table('budget_items')->find($id)->jumlah);
        $this->ditolak(fn () => DB::table('budget_items')->where('id', $id)->update(['jumlah' => 1]));
    }

    public function test_batasan_item_dan_termin(): void
    {
        foreach (array_keys(ItemBelanjaForm::LABEL_KATEGORI) as $kategori) {
            $this->item(['kategori' => $kategori]);
        }
        $this->ditolak(fn () => $this->item(['kategori' => 'modal']));
        $this->ditolak(fn () => $this->item(['volume' => 0]));
        $this->ditolak(fn () => $this->item(['harga_satuan' => 0]));
        $this->ditolak(fn () => $this->item(['kode' => str_repeat('5', 21)]));
        $this->ditolak(fn () => $this->item(['urutan' => 1]));

        DB::table('termin_pembayaran')->insert(['anggaran_kegiatan_id' => $this->anggaran, 'nomor_termin' => 1, 'tanggal' => '2026-07-01', 'nominal' => 5_000_000]);
        $this->ditolak(fn () => DB::table('termin_pembayaran')->insert(['anggaran_kegiatan_id' => $this->anggaran, 'nomor_termin' => 1, 'nominal' => 1]));
        $this->ditolak(fn () => DB::table('termin_pembayaran')->insert(['anggaran_kegiatan_id' => $this->anggaran, 'nomor_termin' => 0, 'nominal' => 1]));
        $this->ditolak(fn () => DB::table('termin_pembayaran')->insert(['anggaran_kegiatan_id' => $this->anggaran, 'nomor_termin' => 2, 'nominal' => 0]));
    }

    public function test_urutan_bisa_digeser_dalam_satu_transaksi(): void
    {
        [$a, $b] = [$this->item(), $this->item()];

        DB::transaction(function () {
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            DB::statement('SET CONSTRAINTS budget_items_urutan_unique DEFERRED');
            // Sisipkan di awal: semua urutan digeser satu (sementara bentrok, sah saat transaksi selesai).
            DB::table('budget_items')->where('anggaran_kegiatan_id', $this->anggaran)->increment('urutan');
            $this->item(['urutan' => 1, 'uraian' => 'Spanduk kegiatan']);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });

        $this->assertSame(['Spanduk kegiatan', $a, $b], [
            DB::table('budget_items')->where('urutan', 1)->value('uraian'),
            DB::table('budget_items')->where('urutan', 2)->value('id'),
            DB::table('budget_items')->where('urutan', 3)->value('id'),
        ]);
    }

    public function test_rab_tetap_ada_saat_sumber_dana_tidak_dipilih_dan_terhapus_bersama_kegiatan(): void
    {
        $repo = new EloquentKegiatanRepository;
        $kegiatan = $repo->simpan(['nama_kegiatan' => 'Supervisi Akademik', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 6_000_000]]]);
        $this->anggaran = DB::table('anggaran_kegiatan')->where('activity_id', $kegiatan)->value('id');
        $this->item();
        DB::table('termin_pembayaran')->insert(['anggaran_kegiatan_id' => $this->anggaran, 'nomor_termin' => 1, 'nominal' => 25_000]);

        $repo->simpan(['sumber_dana' => [['sumber' => 'Komite', 'pagu' => 2_500_000]]], $kegiatan);
        $this->assertSame([['sumber' => 'Komite', 'pagu' => 2_500_000]], $repo->temukan($kegiatan)['sumber_dana']);
        $this->assertSame([1, 1], [DB::table('budget_items')->where('anggaran_kegiatan_id', $this->anggaran)->count(), DB::table('termin_pembayaran')->where('anggaran_kegiatan_id', $this->anggaran)->count()]);

        DB::table('activities')->delete($kegiatan);
        $this->assertSame([0, 0], [DB::table('budget_items')->where('anggaran_kegiatan_id', $this->anggaran)->count(), DB::table('termin_pembayaran')->count()]);
    }
}
