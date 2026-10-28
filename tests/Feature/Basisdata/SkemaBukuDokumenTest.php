<?php

namespace Tests\Feature\Basisdata;

use App\Contracts\KegiatanRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Support\Dokumen\DaftarIsi;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SkemaBukuDokumenTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_28_000034_create_buku_dokumen_tables.php';

    protected function setUp(): void
    {
        parent::setUp();
        RujukanDb::siapkan();
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
    }

    /** @param  list<array{sumber: string, pagu: int}>  $sumber */
    private function kegiatan(string $nama, ?string $rktm, array $sumber, string $leading = '1.6'): int
    {
        return app(KegiatanRepository::class)->simpan(['nama_kegiatan' => $nama, 'kode_rktm' => $rktm, 'leading' => $leading, 'user_id' => 1, 'tahun_anggaran' => 2026, 'sumber_dana' => $sumber]);
    }

    /** @param  array<string, mixed>  $isi */
    private function buku(array $isi = []): string
    {
        DB::table('buku_dokumen')->insert($isi + ['id' => $id = (string) Str::uuid(), 'tahun_anggaran' => 2026, 'kode_leading' => '1.6', 'sumber_dana' => 'DIPA', 'jumlah_kegiatan' => 0, 'dibuat_oleh' => 'Muslimin, M.Pd.']);

        return $id;
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

    public function test_isi_buku_per_leading_dan_sumber_dana_urut_kode_rktm(): void
    {
        $dipa = ['sumber' => 'DIPA', 'pagu' => 1_000_000];
        $komite = ['sumber' => 'Komite', 'pagu' => 1_000_000];
        $a = $this->kegiatan('Workshop Kurikulum', '6.17.12', [$dipa, $komite]);
        $b = $this->kegiatan('Supervisi Akademik', '6.17.2', [$dipa]);
        $c = $this->kegiatan('Rapat Koordinasi', null, [$dipa]);
        $d = $this->kegiatan('Bakti Sosial', '6.17.2', [$dipa]);
        $this->kegiatan('Kegiatan Leading Lain', '1.1.1', [$dipa], '1.2');
        $lepas = $this->kegiatan('Sumber Dilepas', '6.1.1', [$komite]);
        app(KegiatanRepository::class)->simpan(['sumber_dana' => [$dipa]], $lepas);

        $buku = fn (string $sumber) => DB::table('buku_kegiatan')->where(['tahun_anggaran' => 2026, 'kode_leading' => '1.6', 'sumber_dana' => $sumber])->orderBy('urutan')->get();
        $this->assertSame([$lepas, $d, $b, $a, $c], $buku('DIPA')->pluck('activity_id')->map(fn ($v) => (int) $v)->all(), '6.1.1, 6.17.2 (urut nama) sebelum 6.17.12; tanpa kode di akhir.');
        $this->assertSame([1, 2, 3, 4, 5], $buku('DIPA')->pluck('urutan')->map(fn ($v) => (int) $v)->all());
        $this->assertSame([$a], $buku('Komite')->pluck('activity_id')->map(fn ($v) => (int) $v)->all(), 'Sumber yang tidak dipilih lagi tidak masuk buku.');
        $baris = $buku('DIPA')->first();
        $this->assertSame(['Tim Penjamin Mutu', true, 'DIPA', 1, 'draf'], [$baris->nama_unit, (bool) $baris->leading_aktif, $baris->nama_sumber, (int) $baris->urutan_sumber, $baris->status]);

        // Urutan sama dengan daftar isi buku di aplikasi.
        $semua = app(KegiatanRepository::class)->semua();
        $this->assertSame(array_column(DaftarIsi::baris($semua, '1.6', 'DIPA', 'kode'), 'id'), $buku('DIPA')->pluck('activity_id')->map(fn ($v) => (int) $v)->all());
    }

    public function test_riwayat_buku_menjaga_isi_dan_rujukan(): void
    {
        $kegiatan = [['id' => 2, 'nama' => 'Supervisi Akademik', 'halaman' => 3, 'hasil' => 'draf']];
        $id = $this->buku(['jumlah_kegiatan' => 1, 'kegiatan' => json_encode($kegiatan), 'halaman' => 10, 'ada_draf' => true, 'pdf' => json_encode(['status' => 'proses', 'tahap' => 'konversi', 'persen' => 40, 'pesan' => null])]);
        DB::table('buku_dokumen')->where('id', $id)->update(['pdf' => json_encode(['status' => 'selesai', 'tahap' => 'nomor', 'persen' => 100]), 'file_pdf_path' => 'buku/a.pdf', 'ukuran' => 2048]);

        $this->ditolak(fn () => $this->buku(['jumlah_kegiatan' => 2, 'kegiatan' => json_encode($kegiatan)]), 'buku_dokumen_isian_check');
        $this->ditolak(fn () => $this->buku(['pdf' => json_encode(['status' => 'macet'])]), 'buku_dokumen_isian_check');
        $this->ditolak(fn () => $this->buku(['pdf' => json_encode(['status' => 'proses', 'tahap' => 'cetak'])]), 'buku_dokumen_isian_check');
        $this->ditolak(fn () => $this->buku(['pdf' => json_encode(['status' => 'proses', 'persen' => 120])]), 'buku_dokumen_isian_check');
        $this->ditolak(fn () => $this->buku(['file_pdf_path' => 'buku/b.pdf', 'pdf' => json_encode(['status' => 'proses'])]), 'buku_dokumen_isian_check');
        $this->ditolak(fn () => $this->buku(['ukuran' => 10]), 'buku_dokumen_isian_check');
        $this->ditolak(fn () => $this->buku(['kode_leading' => '9.9']), 'buku_dokumen_kode_leading_foreign');
        $this->ditolak(fn () => $this->buku(['sumber_dana' => 'BOS']), 'buku_dokumen_sumber_dana_foreign');
        $this->ditolak(fn () => $this->buku(['tahun_anggaran' => 2030]), 'buku_dokumen_tahun_anggaran_foreign');
        $this->buku(['kode_leading' => '1.9']);
        $this->ditolak(fn () => DB::table('leadings')->where('kode', '1.9')->delete(), 'buku_dokumen_kode_leading_foreign');

        DB::table('users')->insert(['id' => 99, 'name' => 'Admin Sementara', 'email' => 'sementara@example.com', 'password' => '-', 'role' => 'admin', 'is_active' => true]);
        $olehAdmin = $this->buku(['dibuat_oleh_id' => 99, 'dibuat_oleh' => 'Admin Sementara']);
        DB::table('users')->where('id', 99)->delete();
        $this->assertSame([null, 'Admin Sementara'], [DB::table('buku_dokumen')->where('id', $olehAdmin)->value('dibuat_oleh_id'), DB::table('buku_dokumen')->where('id', $olehAdmin)->value('dibuat_oleh')]);
    }

    public function test_migrasi_bisa_dibatalkan(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('buku_dokumen'));
        $this->assertFalse(Schema::hasView('buku_kegiatan'));
        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertTrue(Schema::hasView('buku_kegiatan'));
    }
}
