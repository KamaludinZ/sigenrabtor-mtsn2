<?php

namespace Tests\Feature\Monitoring;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentRabRepository;
use App\Support\Monitoring\RingkasanAnggaran;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\StatusAnggaran;
use App\Support\Stub\StubKegiatanRepository;
use App\Support\Stub\StubRab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * View monitoring_rab & rekap_kelompok_belanja di PostgreSQL menghasilkan angka yang sama dengan RingkasanAnggaran (PHP)
 * untuk contoh kegiatan yang disimpan di database.
 */
class MonitoringDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_28_000022_create_monitoring_views.php';

    private RingkasanAnggaran $ringkasan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(RabRepository::class, EloquentRabRepository::class);

        $ids = [];
        foreach (StubKegiatanRepository::contohSemua() as $idContoh => $contoh) {
            $ids[$idContoh] = app(KegiatanRepository::class)->simpan(Arr::except($contoh, ['id', 'status']));
            foreach (StubRab::DAFTAR_CONTOH[$idContoh] as $sumber) {
                foreach (StubRab::rincian($idContoh, $sumber) as $item) {
                    app(RabRepository::class)->tambahItem($ids[$idContoh], $sumber, $item);
                }
            }
        }
        // Sumber dana yang tidak dipilih (RAB terlepas) tidak ikut monitoring.
        app(RabRepository::class)->tambahItem($ids[1], 'DIPA', ['kode' => '', 'uraian' => 'Item terlepas', 'kategori' => 'lain', 'volume' => 1, 'satuan' => 'paket', 'harga_satuan' => 99_000]);

        $this->ringkasan = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), ['tahun' => 2026]);
    }

    public function test_per_sumber_dana_dan_kondisi_rab(): void
    {
        $sql = DB::table('monitoring_rab')->where('tahun_anggaran', 2026)->groupBy('sumber_dana')
            ->selectRaw("sumber_dana, COALESCE(SUM(pagu), 0)::bigint AS pagu, SUM(total_rab)::bigint AS total, COUNT(*) AS rab,
                COUNT(*) FILTER (WHERE status_pagu = 'melebihi') AS melebihi, COUNT(*) FILTER (WHERE pagu IS NULL) AS tanpa_pagu, SUM(kelebihan)::bigint AS kelebihan")
            ->get()->keyBy('sumber_dana');
        $perbandingan = $this->ringkasan->perbandinganSumber();

        foreach ($this->ringkasan->perSumber() as $sumber => $angka) {
            $baris = $sql[$sumber];
            $this->assertSame(
                [$angka['pagu'], $angka['total'], $angka['rab'], $angka['melebihi'], $angka['tanpa_pagu'], $perbandingan[$sumber]['kelebihan']],
                [(int) $baris->pagu, (int) $baris->total, (int) $baris->rab, (int) $baris->melebihi, (int) $baris->tanpa_pagu, (int) $baris->kelebihan],
                $sumber,
            );
            $kondisi = DB::table('monitoring_rab')->where('sumber_dana', $sumber)->groupBy('kondisi_rab')->selectRaw('kondisi_rab, COUNT(*) AS n')->pluck('n', 'kondisi_rab')->map(fn ($n) => (int) $n)->all();
            $harapan = array_filter($perbandingan[$sumber]['kondisi']);
            ksort($harapan);
            ksort($kondisi);
            $this->assertSame($harapan, $kondisi, "Kondisi RAB {$sumber}");
        }

        // Kondisi per baris = StatusAnggaran::rab.
        foreach (app(KegiatanRepository::class)->semua() as $k) {
            foreach (AnggaranKegiatan::untuk($k) as $a) {
                $this->assertSame(StatusAnggaran::rab($a), DB::table('monitoring_rab')->where('activity_id', $k['id'])->where('sumber_dana', $a->sumber)->value('kondisi_rab'));
            }
        }
    }

    public function test_per_leading_dan_per_operator(): void
    {
        $leading = DB::table('monitoring_rab')->groupBy('kode_leading')->selectRaw('kode_leading, COALESCE(SUM(pagu), 0)::bigint AS pagu, SUM(total_rab)::bigint AS total, COUNT(*) AS rab')->get()->keyBy('kode_leading');
        foreach ($this->ringkasan->perLeading(false) as $baris) {
            if ($baris['rab'] > 0) {
                $this->assertSame([$baris['pagu'], $baris['total'], $baris['rab']], [(int) $leading[$baris['kode']]->pagu, (int) $leading[$baris['kode']]->total, (int) $leading[$baris['kode']]->rab], $baris['kode']);
            }
        }

        $operator = DB::table('monitoring_rab')->groupBy('user_id')->selectRaw('user_id, SUM(total_rab)::bigint AS total, COUNT(DISTINCT activity_id) AS kegiatan')->get()->keyBy('user_id');
        $akun = [['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading'], ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin']];
        foreach ($this->ringkasan->perOperator($akun) as $baris) {
            $this->assertSame([$baris['total'], $baris['jumlah']], [(int) $operator[$baris['user_id']]->total, (int) $operator[$baris['user_id']]->kegiatan], (string) $baris['nama']);
        }
    }

    public function test_rekap_kelompok_belanja(): void
    {
        $sql = DB::table('rekap_kelompok_belanja')->where('tahun_anggaran', 2026)->groupBy('kategori')
            ->selectRaw('kategori, SUM(total)::bigint AS total, SUM(jumlah_item) AS item, COUNT(DISTINCT activity_id) AS kegiatan')->get()->keyBy('kategori');

        foreach ($this->ringkasan->perKelompok()['kelompok'] as $kelompok) {
            $baris = $sql[$kelompok['kunci']] ?? null;
            $this->assertSame(
                [$kelompok['total'], $kelompok['jumlahItem'], $kelompok['kegiatan']],
                [(int) ($baris->total ?? 0), (int) ($baris->item ?? 0), (int) ($baris->kegiatan ?? 0)],
                $kelompok['kunci'],
            );
        }
    }

    public function test_view_bisa_dibatalkan(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertFalse(DB::table('pg_views')->where('viewname', 'monitoring_rab')->exists());
        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertTrue(DB::table('pg_views')->where('viewname', 'rekap_kelompok_belanja')->exists());
    }
}
