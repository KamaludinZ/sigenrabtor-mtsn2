<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentRabRepository;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\MekanismePembayaran;
use App\Support\Rab\StatusPagu;
use App\Support\Stub\StubKegiatanRepository;
use App\Support\Stub\StubRab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RabPerSumberViewTest extends TestCase
{
    use RefreshDatabase;

    /** Satu anggaran berisi satu item senilai $total (tanpa item bila 0) dan termin $nominal; mengembalikan id anggaran. */
    private function anggaran(int $total, ?int $pagu, array $nominal = []): int
    {
        $kegiatan = DB::table('activities')->insertGetId(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Kegiatan Uji', 'kode_leading' => '1.6']);
        $id = DB::table('anggaran_kegiatan')->insertGetId(['activity_id' => $kegiatan, 'sumber_dana' => 'DIPA', 'pagu' => $pagu]);
        if ($total > 0) {
            DB::table('budget_items')->insert(['id' => (string) Str::uuid(), 'anggaran_kegiatan_id' => $id, 'urutan' => 1, 'uraian' => 'Paket', 'kategori' => 'lain', 'volume' => 1, 'satuan' => 'paket', 'harga_satuan' => $total]);
        }
        foreach ($nominal as $i => $n) {
            DB::table('termin_pembayaran')->insert(['anggaran_kegiatan_id' => $id, 'nomor_termin' => $i + 1, 'nominal' => $n]);
        }

        return $id;
    }

    private function baris(int $anggaran): object
    {
        return DB::table('rab_per_sumber')->where('anggaran_kegiatan_id', $anggaran)->first();
    }

    public function test_status_pagu_sama_dengan_status_pagu_aplikasi(): void
    {
        foreach (json_decode(file_get_contents(base_path('tests/fixtures/status-pagu.json')), true) as $kasus) {
            $baris = $this->baris($this->anggaran($kasus['total'], $kasus['pagu']));
            $harapan = StatusPagu::hitung($kasus['total'], $kasus['pagu']);

            $this->assertSame([$kasus['total'], $harapan->status, $harapan->selisih], [$baris->total_rab, $baris->status_pagu, $baris->selisih_pagu], json_encode($kasus));
        }
    }

    public function test_status_termin_sama_dengan_mekanisme_pembayaran(): void
    {
        foreach (json_decode(file_get_contents(base_path('tests/fixtures/status-termin.json')), true) as $kasus) {
            $baris = $this->baris($this->anggaran($kasus['total'], null, $kasus['nominal']));
            $harapan = new MekanismePembayaran($kasus['total'], array_map(fn ($n) => ['tanggal' => null, 'nominal' => $n], $kasus['nominal']));

            $this->assertSame([$harapan->status(), count($kasus['nominal']), $harapan->jumlah()], [$baris->status_termin, $baris->jumlah_termin, $baris->total_termin], json_encode($kasus));
        }
    }

    public function test_rangkuman_sama_dengan_anggaran_kegiatan(): void
    {
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(RabRepository::class, EloquentRabRepository::class);
        foreach (StubKegiatanRepository::contohSemua() as $contoh) {
            $id = app(KegiatanRepository::class)->simpan(Arr::except($contoh, ['id', 'status']));
            foreach (StubRab::DAFTAR_CONTOH[$contoh['id']] as $sumber) {
                foreach (StubRab::rincian($contoh['id'], $sumber) as $item) {
                    app(RabRepository::class)->tambahItem($id, $sumber, $item);
                }
                app(RabRepository::class)->simpanTermin($id, $sumber, StubRab::termin($contoh['id'], $sumber));
            }

            foreach (AnggaranKegiatan::untuk(app(KegiatanRepository::class)->temukan($id)) as $a) {
                $baris = DB::table('rab_per_sumber')->where('activity_id', $id)->where('sumber_dana', $a->sumber)->first();
                $this->assertSame(
                    [true, $a->pagu(), $a->total(), $a->jumlahItem(), $a->totalTermin(), $a->statusPagu(), MekanismePembayaran::dari($a)->status(), 2026, '1.6'],
                    [$baris->dipilih, $baris->pagu, $baris->total_rab, $baris->jumlah_item, $baris->total_termin, $baris->status_pagu, $baris->status_termin, $baris->tahun_anggaran, $baris->kode_leading],
                    "{$contoh['id']}:{$a->sumber}",
                );
            }
        }
    }
}
