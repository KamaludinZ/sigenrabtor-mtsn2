<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\Rab;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentRabRepository;
use App\Support\Monitoring\RingkasanAnggaran;
use App\Support\Stub\StubKegiatanRepository;
use App\Support\Stub\StubRab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Rekap per kelompok belanja (halaman RAB & Monitoring) dari data PostgreSQL sama dengan agregat database itu sendiri:
 * SUM(jumlah) & COUNT per sumber dana × kategori atas sumber dana yang dipilih.
 */
class RekapKategoriDatabaseTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<int> */
    private array $id = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(RabRepository::class, EloquentRabRepository::class);

        foreach (StubKegiatanRepository::contohSemua() as $contoh) {
            $id = app(KegiatanRepository::class)->simpan(Arr::except($contoh, ['id', 'status']));
            foreach (StubRab::DAFTAR_CONTOH[$contoh['id']] as $sumber) {
                foreach (StubRab::rincian($contoh['id'], $sumber) as $item) {
                    app(RabRepository::class)->tambahItem($id, $sumber, $item);
                }
            }
            $this->id[] = $id;
        }
        // RAB sumber dana yang tidak dipilih tidak ikut rekap.
        app(RabRepository::class)->tambahItem($this->id[0], 'DIPA', ['kode' => '', 'uraian' => 'Item sumber terlepas', 'kategori' => 'lain', 'volume' => 1, 'satuan' => 'paket', 'harga_satuan' => 99_000]);
    }

    /** @return array<string, array<string, array{total: int, item: int, kegiatan: int}>> sumber → kategori → agregat. */
    private function agregat(?int $kegiatan = null): array
    {
        $hasil = [];
        $baris = DB::table('budget_items as i')->join('anggaran_kegiatan as a', 'a.id', '=', 'i.anggaran_kegiatan_id')
            ->where('a.dipilih', true)->when($kegiatan, fn ($q) => $q->where('a.activity_id', $kegiatan))
            ->groupBy('a.sumber_dana', 'i.kategori')
            ->selectRaw('a.sumber_dana, i.kategori, SUM(i.jumlah) AS total, COUNT(*) AS item, COUNT(DISTINCT a.activity_id) AS kegiatan')->get();
        foreach ($baris as $b) {
            $hasil[$b->sumber_dana][$b->kategori] = ['total' => (int) $b->total, 'item' => (int) $b->item, 'kegiatan' => (int) $b->kegiatan];
        }

        return $hasil;
    }

    /** @param  array<string, mixed>  $rekap  Hasil RekapKelompok::susun(). */
    private function dariRekap(array $rekap, bool $denganKegiatan): array
    {
        $hasil = [];
        foreach ($rekap['kelompok'] as $kelompok) {
            foreach ($kelompok['perSumber'] as $sumber => $total) {
                $item = count(array_filter($kelompok['item'], fn ($i) => $i['sumber'] === $sumber));
                if ($item > 0) {
                    $hasil[$sumber][$kelompok['kunci']] = ['total' => $total, 'item' => $item] + ($denganKegiatan
                        ? ['kegiatan' => count(array_unique(array_column(array_filter($kelompok['item'], fn ($i) => $i['sumber'] === $sumber), 'kegiatan_id')))]
                        : []);
                }
            }
        }

        return $hasil;
    }

    public function test_rekap_halaman_rab_sama_dengan_agregat_database(): void
    {
        foreach ($this->id as $id) {
            $rekap = Livewire::test(Rab::class, ['kegiatan' => $id])->instance()->rekap();
            $harapan = array_map(fn (array $kategori) => array_map(fn (array $a) => Arr::except($a, 'kegiatan'), $kategori), $this->agregat($id));

            $this->assertEquals($harapan, $this->dariRekap($rekap, false), "Kegiatan {$id}");
            $this->assertSame((int) DB::table('anggaran_kegiatan')->where('activity_id', $id)->where('dipilih', true)->sum('total_rab'), $rekap['total']);
        }
    }

    public function test_rekap_monitoring_seluruh_kegiatan_sama_dengan_agregat_database(): void
    {
        $rekap = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua())->perKelompok();

        $this->assertEquals($this->agregat(), $this->dariRekap($rekap, true));
        $this->assertSame((int) DB::table('budget_items as i')->join('anggaran_kegiatan as a', 'a.id', '=', 'i.anggaran_kegiatan_id')->where('a.dipilih', true)->sum('i.jumlah'), $rekap['total']);
    }
}
