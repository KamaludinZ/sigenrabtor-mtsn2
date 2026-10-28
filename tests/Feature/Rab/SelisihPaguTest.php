<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Support\Monitoring\RingkasanAnggaran;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\SelisihPagu;
use App\Support\Rab\StatusPagu;
use App\Support\Rab\TabelPagu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SelisihPaguTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<array{catatan: string, rab: list<array{0: int|null, 1: int}>, hasil: array<string, mixed>}> */
    private static function kasus(): array
    {
        return json_decode(file_get_contents(base_path('tests/fixtures/selisih-pagu.json')), true);
    }

    public function test_kasus_bersama(): void
    {
        foreach (self::kasus() as $kasus) {
            $hasil = SelisihPagu::gabungan($kasus['rab'])->toArray();
            $harapan = $kasus['hasil'];
            $harapan['persen'] = $harapan['persen'] === null ? null : (float) $harapan['persen'];

            $this->assertSame($harapan, $hasil, $kasus['catatan']);
        }
    }

    public function test_satu_rab_sama_dengan_status_pagu(): void
    {
        foreach ([[5_400_000, 6_000_000], [7_950_000, 7_500_000], [900_000, null], [0, 6_000_000], [10_000_000, 10_000_000]] as [$total, $pagu]) {
            $satu = SelisihPagu::rab($total, $pagu);
            $status = StatusPagu::hitung($total, $pagu);

            $this->assertSame($status->selisih, $satu->selisih);
            $this->assertSame($status->melebihi() ? -$status->selisih : 0, $satu->kelebihan);
            $this->assertSame(TabelPagu::persen($total, $pagu), $satu->persen);
        }
    }

    public function test_dipakai_tabel_pagu_dan_monitoring(): void
    {
        $repo = app(KegiatanRepository::class);
        $tiga = SelisihPagu::dariAnggaran(AnggaranKegiatan::untuk($repo->temukan(3)));
        $this->assertSame([7_500_000, 8_850_000, null, 450_000, 1], [$tiga->pagu, $tiga->total, $tiga->selisih, $tiga->kelebihan, $tiga->tanpaPagu]);
        $this->assertSame(['selisih' => null, 'persen' => null, 'kelebihan' => 450_000], array_intersect_key(TabelPagu::dari(AnggaranKegiatan::untuk($repo->temukan(3)))->jumlah(), array_flip(['selisih', 'persen', 'kelebihan'])));

        $ringkasan = RingkasanAnggaran::dari($repo->semua(), ['tahun' => 2026]);
        $baris = collect($ringkasan->rincianKegiatan())->firstWhere('id', 3);
        $this->assertSame([$tiga->selisih, $tiga->persen, $tiga->kelebihan], [$baris['selisih'], $baris['persen'], $baris['kelebihan']]);
        $komite = $ringkasan->perbandinganSumber()['Komite'];
        $this->assertSame(450_000, $komite['kelebihan']);
        $this->assertSame(['selisih' => null, 'persen' => null], array_intersect_key(RingkasanAnggaran::sel(['pagu' => 0, 'total' => 0, 'rab' => 0, 'melebihi' => 0, 'tanpa_pagu' => 0]), array_flip(['selisih', 'persen'])), 'Tanpa RAB: tidak bisa dibandingkan.');
    }

    public function test_view_database_sama_per_rab(): void
    {
        foreach (self::kasus() as $kasus) {
            foreach ($kasus['rab'] as [$pagu, $total]) {
                $kegiatan = DB::table('activities')->insertGetId(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Kegiatan Uji']);
                $id = DB::table('anggaran_kegiatan')->insertGetId(['activity_id' => $kegiatan, 'sumber_dana' => 'DIPA', 'pagu' => $pagu]);
                if ($total > 0) {
                    DB::table('budget_items')->insert(['id' => (string) Str::uuid(), 'anggaran_kegiatan_id' => $id, 'urutan' => 1, 'uraian' => 'Paket', 'kategori' => 'lain', 'volume' => 1, 'satuan' => 'paket', 'harga_satuan' => $total]);
                }
                $baris = DB::table('monitoring_rab')->where('anggaran_kegiatan_id', $id)->first();
                $harapan = SelisihPagu::rab($total, $pagu);

                $this->assertSame([$harapan->selisih, $harapan->kelebihan], [$baris->selisih_pagu, (int) $baris->kelebihan], $kasus['catatan']);
            }
        }
    }
}
