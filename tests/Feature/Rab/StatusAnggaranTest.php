<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\Daftar;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentRabRepository;
use App\Support\Monitoring\RingkasanAnggaran;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\AnggaranSumber;
use App\Support\Rab\KalkulatorRab;
use App\Support\Rab\StatusAnggaran;
use App\Support\Stub\StubKegiatanRepository;
use App\Support\Stub\StubRab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StatusAnggaranTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_28_000017_create_status_anggaran_kegiatan_view.php';

    /** @return list<array{catatan: string, rab: list<array<string, mixed>>, kondisi: string}> */
    private static function kasus(): array
    {
        return json_decode(file_get_contents(base_path('tests/fixtures/status-anggaran.json')), true);
    }

    /** RAB satu item senilai total (tanpa item bila 0). */
    private static function anggaran(array $rab): AnggaranSumber
    {
        $rincian = $rab['total'] > 0 ? [['kode' => '', 'uraian' => 'Paket', 'kategori' => 'lain', 'volume' => 1, 'satuan' => 'paket', 'harga_satuan' => $rab['total']]] : [];

        return new AnggaranSumber($rab['sumber'], KalkulatorRab::hitung($rincian, $rab['pagu']), dipilih: $rab['dipilih'] ?? true);
    }

    public function test_kondisi_kegiatan_dari_kasus_bersama(): void
    {
        foreach (self::kasus() as $kasus) {
            $status = StatusAnggaran::dariAnggaran(array_map(self::anggaran(...), $kasus['rab']));

            $this->assertSame($kasus['kondisi'], $status->kondisi, $kasus['catatan']);
            $this->assertSame(StatusAnggaran::KONDISI_KEGIATAN[$kasus['kondisi']], $status->label());
            $this->assertSame($kasus['kondisi'] !== 'melebihi', $status->bolehDiajukan(), $kasus['catatan']);
        }
    }

    public function test_kondisi_per_rab_dan_contoh_kegiatan(): void
    {
        $this->assertSame('tanpa_pagu', StatusAnggaran::rab(self::anggaran(['sumber' => 'DIPA', 'pagu' => null, 'total' => 0])), 'Pagu kosong lebih dulu dari RAB kosong.');
        $this->assertSame('rab_kosong', StatusAnggaran::rab(self::anggaran(['sumber' => 'DIPA', 'pagu' => 1_000, 'total' => 0])));
        $this->assertSame('melebihi', StatusAnggaran::rab(self::anggaran(['sumber' => 'DIPA', 'pagu' => 1_000, 'total' => 1_001])));

        $repo = app(KegiatanRepository::class);
        $this->assertSame(['sesuai', ['Komite' => 'sesuai']], [StatusAnggaran::dari($repo->temukan(1))->kondisi, StatusAnggaran::dari($repo->temukan(1))->perSumber]);
        $this->assertSame('sisa', StatusAnggaran::dari($repo->temukan(2))->kondisi);
        $tiga = StatusAnggaran::dari($repo->temukan(3));
        $this->assertSame(['melebihi', ['DIPA' => 'tanpa_pagu', 'Komite' => 'melebihi'], ['Komite'], 'bahaya'], [$tiga->kondisi, $tiga->perSumber, array_keys($tiga->terlampaui), $tiga->nada()]);

        // Data Kegiatan & Monitoring memakai aturan & label yang sama.
        $this->assertSame(StatusAnggaran::KONDISI_RAB, Daftar::STATUS_PAGU);
        $this->assertSame(StatusAnggaran::KONDISI_RAB, RingkasanAnggaran::KONDISI_RAB);
    }

    public function test_view_database_sama_dengan_aturan_aplikasi(): void
    {
        foreach (self::kasus() as $kasus) {
            $kegiatan = DB::table('activities')->insertGetId(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Kegiatan Uji', 'kode_leading' => '1.6']);
            foreach ($kasus['rab'] as $rab) {
                $id = DB::table('anggaran_kegiatan')->insertGetId(['activity_id' => $kegiatan, 'sumber_dana' => $rab['sumber'], 'pagu' => $rab['pagu'], 'dipilih' => $rab['dipilih'] ?? true]);
                if ($rab['total'] > 0) {
                    DB::table('budget_items')->insert(['id' => (string) Str::uuid(), 'anggaran_kegiatan_id' => $id, 'urutan' => 1, 'uraian' => 'Paket', 'kategori' => 'lain', 'volume' => 1, 'satuan' => 'paket', 'harga_satuan' => $rab['total']]);
                }
            }

            $baris = DB::table('status_anggaran_kegiatan')->where('activity_id', $kegiatan)->first();
            $dipilih = array_filter($kasus['rab'], fn (array $r) => $r['dipilih'] ?? true);
            $this->assertSame(
                [$kasus['kondisi'], count($dipilih), array_sum(array_column($dipilih, 'total')), array_sum(array_column($dipilih, 'pagu'))],
                [$baris->kondisi_anggaran, $baris->jumlah_sumber, $baris->total_rab, $baris->pagu],
                $kasus['catatan'],
            );
        }
    }

    public function test_view_untuk_contoh_kegiatan_dan_bisa_dibatalkan(): void
    {
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(RabRepository::class, EloquentRabRepository::class);
        foreach (StubKegiatanRepository::contohSemua() as $idContoh => $contoh) {
            $id = app(KegiatanRepository::class)->simpan(Arr::except($contoh, ['id', 'status']));
            foreach (StubRab::DAFTAR_CONTOH[$idContoh] as $sumber) {
                foreach (StubRab::rincian($idContoh, $sumber) as $item) {
                    app(RabRepository::class)->tambahItem($id, $sumber, $item);
                }
            }

            $kegiatan = app(KegiatanRepository::class)->temukan($id);
            $anggaran = AnggaranKegiatan::untuk($kegiatan);
            $baris = DB::table('status_anggaran_kegiatan')->where('activity_id', $id)->first();
            $this->assertSame(
                [StatusAnggaran::dari($kegiatan)->kondisi, AnggaranKegiatan::gabungan($anggaran)['total'], count(StatusAnggaran::dari($kegiatan)->terlampaui)],
                [$baris->kondisi_anggaran, $baris->total_rab, $baris->jumlah_melebihi],
                $contoh['nama_kegiatan'],
            );
        }

        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertFalse(DB::table('pg_views')->where('viewname', 'status_anggaran_kegiatan')->exists());
        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();
    }
}
