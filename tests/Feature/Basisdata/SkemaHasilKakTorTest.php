<?php

namespace Tests\Feature\Basisdata;

use App\Contracts\KegiatanRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Support\Dokumen\HasilKakTor;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanOtomatis;
use App\Support\Dokumen\SusunanRab;
use App\Support\Stub\StubKegiatanRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SkemaHasilKakTorTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_28_000039_create_dokumen_bagian_table.php';

    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    protected function setUp(): void
    {
        parent::setUp();
        RujukanDb::siapkan();
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
    }

    private function ditolak(callable $aksi, string $pesan): void
    {
        try {
            DB::transaction(function () use ($aksi) {
                $aksi();
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            });
            $this->fail("Seharusnya ditolak: {$pesan}");
        } catch (QueryException $e) {
            $this->assertStringContainsString($pesan, $e->getMessage());
        } finally {
            DB::statement('SET CONSTRAINTS ALL DEFERRED');
        }
    }

    /** @return array{0: int, 1: list<array<string, mixed>>} Kegiatan (salinan contoh 2) & baris hasil susunannya. */
    private function kegiatanDanHasil(): array
    {
        $contoh = collect(StubKegiatanRepository::contohSemua())->firstWhere('id', 2);
        $id = app(KegiatanRepository::class)->simpan(array_diff_key($contoh, array_flip(['id', 'status', 'dibuat_pada', 'diperbarui_pada', 'riwayat_status'])));
        $kegiatan = app(KegiatanRepository::class)->temukan($id);
        $kak = SusunanKak::dari($kegiatan);

        return [$id, HasilKakTor::bagian($kak, SusunanOtomatis::dari($kak, SusunanRab::dari($kegiatan), $kegiatan, self::LEADING))];
    }

    private function dokumen(int $kegiatan, string $jenis = 'KAK+TOR', array $isi = []): int
    {
        return DB::table('documents')->insertGetId($isi + ['activity_id' => $kegiatan, 'tahun_anggaran' => 2026, 'jenis' => $jenis]);
    }

    /** @param  array<string, mixed>  $bagian */
    private function simpanBagian(int $dokumen, array $bagian, string $jenis = 'KAK+TOR'): void
    {
        DB::table('dokumen_bagian')->insert([
            'document_id' => $dokumen, 'jenis_dokumen' => $jenis, 'nomor' => $bagian['nomor'], 'judul' => $bagian['judul'],
            'isi' => json_encode($bagian['isi'], JSON_UNESCAPED_UNICODE), 'status' => $bagian['status'],
            'kurang' => json_encode($bagian['kurang'], JSON_UNESCAPED_UNICODE), 'periksa' => json_encode($bagian['periksa'], JSON_UNESCAPED_UNICODE),
            'sidik' => $bagian['sidik'],
        ]);
    }

    public function test_hasil_susunan_kak_tersimpan_utuh(): void
    {
        [$id, $bagian] = $this->kegiatanDanHasil();
        $this->assertSame(range(1, HasilKakTor::JUMLAH_BAGIAN), array_column($bagian, 'nomor'));
        $this->assertNotContains('sunting', array_merge(...array_map(fn ($b) => array_merge(...array_map('array_keys', $b['isi'])), $bagian)), 'Penanda layar dibuang.');
        $this->assertSame(['kurang', 'kurang'], [$bagian[3]['status'], $bagian[6]['status']], 'Contoh 2: matrik & jadwal belum lengkap.');
        $this->assertSame('periksa', $bagian[8]['status'], 'Nama penanda tangan belum diisi.');

        $dokumen = $this->dokumen($id, 'KAK+TOR', ['sidik_isi' => HasilKakTor::sidik($bagian), 'disusun_pada' => now()]);
        foreach ($bagian as $b) {
            $this->simpanBagian($dokumen, $b);
        }
        $this->assertSame(9, DB::table('dokumen_bagian')->where('document_id', $dokumen)->count());
        $tersimpan = DB::table('dokumen_bagian')->where('document_id', $dokumen)->orderBy('nomor')->get();
        $this->assertSame($bagian[7]['isi'], json_decode($tersimpan[7]->isi, true), 'Pembiayaan tersimpan apa adanya.');
        $this->assertSame(HasilKakTor::sidik($bagian), HasilKakTor::sidik(array_map(fn ($b) => ['nomor' => (int) $b->nomor, 'sidik' => $b->sidik], $tersimpan->all())));
        $this->assertSame(HasilKakTor::sidik($bagian), HasilKakTor::sidik(array_reverse($bagian)), 'Sidik tidak bergantung urutan masukan.');

        // Set lengkap juga memuat KAK/TOR; dokumen dihapus → bagiannya ikut terhapus.
        $lengkap = $this->dokumen($id, 'KAK');
        $this->simpanBagian($lengkap, $bagian[0], 'KAK');
        DB::table('documents')->where('id', $dokumen)->delete();
        $this->assertSame(1, DB::table('dokumen_bagian')->count());
    }

    public function test_batasan_bagian_dan_dokumen(): void
    {
        [$id, $bagian] = $this->kegiatanDanHasil();
        $dokumen = $this->dokumen($id);
        $anggaran = DB::table('anggaran_kegiatan')->where('activity_id', $id)->where('sumber_dana', 'DIPA')->value('id');
        $rab = $this->dokumen($id, 'RAB', ['anggaran_kegiatan_id' => $anggaran, 'sumber_dana' => 'DIPA']);
        $satu = $bagian[0];
        $this->simpanBagian($dokumen, $satu);

        $this->ditolak(fn () => $this->simpanBagian($dokumen, $satu), 'dokumen_bagian_document_id_nomor_unique');
        $this->ditolak(fn () => $this->simpanBagian($rab, ['nomor' => 2] + $satu, 'RAB'), 'dokumen_bagian_isian_check');
        $this->ditolak(fn () => $this->simpanBagian($rab, ['nomor' => 2] + $satu, 'KAK+TOR'), 'dokumen_bagian_dokumen_foreign');
        foreach ([
            'nomor' => ['nomor' => 10],
            'judul kosong' => ['judul' => '  '],
            'isi kosong' => ['isi' => []],
            'jenis blok asing' => ['isi' => [['jenis' => 'gambar', 'teks' => 'x']]],
            'paragraf tanpa teks' => ['isi' => [['jenis' => 'paragraf', 'teks' => ' ']]],
            'daftar tanpa butir' => ['isi' => [['jenis' => 'daftar', 'butir' => 'satu']]],
            'blok bukan objek' => ['isi' => ['paragraf']],
            'status asing' => ['status' => 'draf'],
            'lengkap tetapi kurang' => ['status' => 'lengkap', 'kurang' => ['tujuan kegiatan']],
            'kurang tanpa daftar' => ['status' => 'kurang', 'kurang' => []],
            'periksa tanpa temuan' => ['status' => 'periksa', 'kurang' => [], 'periksa' => []],
            'daftar kurang bukan teks' => ['status' => 'kurang', 'kurang' => [1]],
            'sidik salah' => ['sidik' => str_repeat('x', 32)],
        ] as $ubah) {
            $this->ditolak(fn () => $this->simpanBagian($dokumen, $ubah + ['nomor' => 2] + $satu), 'dokumen_bagian_isian_check');
        }
        $this->simpanBagian($dokumen, ['nomor' => 2, 'isi' => [['jenis' => 'pagu']], 'status' => 'periksa', 'kurang' => [], 'periksa' => ['RAB DIPA melebihi pagu']] + $satu);

        $this->ditolak(fn () => DB::table('documents')->where('id', $dokumen)->update(['sidik_isi' => md5('x')]), 'documents_sidik_isi_check');
        $this->ditolak(fn () => DB::table('documents')->where('id', $dokumen)->update(['sidik_isi' => 'bukan-md5', 'disusun_pada' => now()]), 'documents_sidik_isi_check');
        DB::table('documents')->where('id', $dokumen)->update(['sidik_isi' => md5('x'), 'disusun_pada' => now()]);
    }

    public function test_migrasi_bisa_dibatalkan(): void
    {
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::statement('SET CONSTRAINTS ALL DEFERRED');
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('dokumen_bagian'));
        $this->assertFalse(Schema::hasColumn('documents', 'sidik_isi'));
        $this->assertNull(DB::selectOne("SELECT to_regproc('isi_bagian_kak_sah') AS f")->f);
        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('dokumen_bagian'));
    }
}
